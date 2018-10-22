<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Fourtysix extends MY_Controller
{

    private $urlError = "";

    /**
     * Define pago46 env according to the Codeigniter's env
     *
     * @var string
     */
    //private $env = ENVIRONMENT == 'development' ? 'sandbox' : 'production';

    /**
     * Timeout Trx (minutes)
     *
     * @var int
     */
    private $timeout;

    /**
     * Payment type code
     *
     * @var string
     */
    private $paymentCode = 'P46';

	/**
	 * Payment Type Data
	 *
	 * @var
	 */
    private $paymentType;

	/**
	 * Stages
	 *
	 * @var
	 */
    private $stages;

    const NEW_ORDER				        = 46;
	const FAILED_NEW_ORDER			    = 47;
	const NOK_NEW_ORDER_PRICE		    = 48;
    const NOK_NEW_ORDER_TRX             = 49;
    
    const ORDER_INCORRECT_PRICE         = 51;
    const ORDER_NOT_FOUND               = 52;

    // awaiting_assignment, awaiting_payment, cancelled, expired, successful
    const ORDER_AWAITING_ASSIGNMENT     = 69;   // awaiting_assignment
    const ORDER_AWAITING_PAYMENT        = 53;   // awaiting_payment
    const ORDER_CANCELLED               = 54;   // cancelled
    const ORDER_EXPIRED                 = 55;   // expired
    const ORDER_SUCCESSFUL              = 56;   // successful


    public function __construct()
    {
        parent::__construct();
        $this->load->library('fourtysixlib');
        $this->load->library('sanitize');
        $this->load->library('encryption');

        $this->load->model('core_model', '', true);
        $this->load->model('Fortysix_model', 'fs', true);
        $this->load->model('Stage_model', 'stage', true);
        $this->load->model('Payment_type_model', 'pt', true);
        $this->load->model('Commerceptv2_model', 'commercept', true);
        $this->load->model('Transactionv2_model', 'trxv2', true);

        $this->load->helper('url');
        $this->load->helper('crypto');

        //$this->fourtysixlib->setEnv($this->env);
        $this->fourtysixlib->setEnv($this->config->item("pago46_env"));
        $this->urlError = base_url()."";
        $this->setVars();
    }

	public function index() {
        echo "what are you looking for?";
    }
	
	/**
	 * Set Common Variables
	 *
	 * @return void
	 */
    private function setVars()
    {
	    // Get PaymentType
        $this->paymentType = $this->pt->findbyAttr('codPaymentTypeExternal', $this->paymentCode);
        $this->timeout = $this->config->item('pago46_timeout');

        // Set stages
        $this->stages = [
            "awaiting_assignment"   => self::ORDER_AWAITING_ASSIGNMENT,
            "awaiting_payment"      => self::ORDER_AWAITING_PAYMENT,
            "cancelled"             => self::ORDER_CANCELLED,
            "expired"               => self::ORDER_EXPIRED,
            "successful"            => self::ORDER_SUCCESSFUL
        ];

    }


    /**
	 * Start transaction (new order)
	 *
	 * @return void
	 */
    public function startTrx()
    {
        $post = $this->sanitize->inputParams(true);
        $redirect = "";

        try {
            
            $checkParams = $this->startTrxRequiredParams($post);
            if (!$checkParams['status']) {
                throw new Exception($checkParams['msg'], $checkParams['code']);
            }

            // Get Trx
            $trx = $this->trxv2->findByAttr('idTrx', $post->idTrx);
            $idTrx = $post->idTrx;

            $this->commercept->findByAttrs(
                ['idCommerce', $trx->idCommerce],
                ['idPaymentType', $this->paymentType->idPaymentType]
            );

            // Get CommercePT
            $cpt = $this->commercept->findByAttr('cpt.idPaymentType', $this->paymentType->idPaymentType);

            if (empty($cpt)) {
                throw new Exception('Invalid Commerce Payment Type', 400);
            }

            // Start new order in 46Degrees
            // Create details
            $toBd = new stdClass();
            $toBd->idTrx = $idTrx;
            $toBd->creationDate = date("Y-m-d H:i:s");

            $idFortySixDegreesTrx = $this->fs->initTrx($toBd);
            if(is_null($idFortySixDegreesTrx)) {
                $this->core_model->updateStageTrx($idTrx, self::FAILED_NEW_ORDER);
                throw new Exception("Internal error, transaction couldn't be completed", 501);
            }

            $this->core_model->updateStageTrx($idTrx, self::NEW_ORDER);

            // Set dynamic keys in function of product
            $this->fourtysixlib->setMerchant([
                'key' => $cpt->key,
                'secret' => $cpt->secret
            ]);
            
            $this->sanitize->generateLog(__METHOD__, 'Initializing Pago46 Trx');

            // Call 46 degrees lib in order to create new order
            $newTrx = $this->fourtysixlib->newOrder([
                'currency'          => 'CLP',
                'description'       => 'description',
                'merchant_order_id' => $trx->trx,
                'notify_url'        => base_url('v2/Fourtysix/notify'), // Internal Notify
                'price'             => $trx->amount,
                'return_url'        => base_url('v2/Fourtysix/voucher/'.encode_url($idTrx)),
                'timeout'           => $this->timeout
            ]);

            $this->sanitize->generateLog(__METHOD__, 'Pago46 Trx: ' . print_r($newTrx, true));

            if (!isset($newTrx->id)) { // Doesn't exist trx
                $this->core_model->updateStageTrx($idTrx, self::FAILED_NEW_ORDER);

                // 46 degrees return details
                if(!empty($newTrx)) {
                    $o = [
                        "details" => $newTrx->detail
                    ];
                    $res2Bd = $this->fs->updateTrx($idFortySixDegreesTrx, (object)$o);
                }
                
                throw new Exception('Error in Pago46 Gateway response', 501);
            }
            
            // If new order was created, we will check transaction's attributes
            // price and trx
            if(floatval($newTrx->price) != floatval($trx->amount)) {
                $this->core_model->updateStageTrx($idTrx, self::NOK_NEW_ORDER_PRICE);
                throw new Exception('Invalid transaction data', 501);
            }
            if($newTrx->merchant_order_id != $trx->trx) {
                $this->core_model->updateStageTrx($idTrx, self::NOK_NEW_ORDER_TRX);
                throw new Exception('Invalid transaction data', 501);
            }

            // Update 46Degrees response
            $o = [
                "id" => $newTrx->id,
                "description" => $newTrx->description,
                "redirectUrl" => $newTrx->redirect_url,
                "returnUrl" => $newTrx->return_url,
                "status" => $newTrx->status,
                "creationDateOrder" => $newTrx->creation_date
            ];
            $update = $this->fs->updateTrx($idFortySixDegreesTrx, (object)$o);

            // Check for response
            if(!$update) {
                $this->core_model->updateStageTrx($idTrx, self::FAILED_NEW_ORDER);
                throw new Exception("Internal error, transaction couldn't be completed", 501);
            }
            if($newTrx->status != "awaiting_assignment") {
                $this->core_model->updateStageTrx($idTrx, self::FAILED_NEW_ORDER);
                throw new Exception("Transaction was rejected by Pago46", 501);
            }

            // OK
            $this->core_model->updateStageTrx($idTrx, self::ORDER_AWAITING_ASSIGNMENT);
            $response = $this->sanitize->successResponse($newTrx);
            
            $this->sanitize->jsonResponse($response);
   
        } catch (Exception $e) {

            $this->sanitize->generateLog(__METHOD__, $e->getMessage());
            
            $data = [
                'logo'      =>  false,
                'message'   => "El proceso ha terminado inesperadamente: " . $e->getMessage()
            ];
    
            $this->load->view('transaction/unexpected', $data);

        }

    }

    /**
	 * Asyncronic response from 46 degrees
	 *
	 * @return void
	 */
    public function notify()
    {

        // Receive data from 46Pagos
        $post = $this->sanitize->inputParams(true);
        $oTrx = (object)[];
        $isOk = false;
        $description = "";

        try {
            
            if(empty($post)) { throw new Exception("No response from Pago46 gateway (notify)", 1001); }

            // Check for variables
            if(!isset($post->notification_id) || !isset($post->merchant_id) || !isset($post->date)) {
                throw new Exception("Response is incomplete or not valid", 1002);
            }

            $this->sanitize->generateLog(__METHOD__, 'Pago46 Notify: ' . print_r($post, true));

            // Call for transaction status
            // Find secret from key received. It's only way to get credentials because at this time engine
            // doesn't know which product is the transaction owner
            $oCommercePayment = $this->commercept->findByAttr("cpt.key", $post->merchant_id);
            if(empty($oCommercePayment)) { throw new Exception("Merchant key doesn't exist in the system", 1003); }

            // Set merchant credentials
            $credentials = [
                'key' => $post->merchant_id,
                'secret' => $oCommercePayment->secret
            ];
            $this->sanitize->generateLog(__METHOD__, 'Searching with credentials: ' . print_r($credentials, true));
            $this->fourtysixlib->setMerchant($credentials);
            
            // Order details
            $this->sanitize->generateLog(__METHOD__, 'Notification ID: ' . $post->notification_id);
            $oOrder = $this->fourtysixlib->getOrderByNotificationID($post->notification_id);
            $this->sanitize->generateLog(__METHOD__, 'Order details: ' . print_r($oOrder, true));
            if(empty($oOrder)) { throw new Exception("Couldn't find transaction details", 1004); }

            // Error getting transaction details
            if(!isset($oOrder->id)) { throw new Exception($oOrder->detail, 1005); }

            // Get Trx (internal)
            $oTrx = $this->trxv2->findByAttr('trx', $oOrder->merchant_order_id);
            if(empty($oTrx)) { throw new Exception("Couldn't find internal transaction (".$oOrder->merchant_order_id.")", 1006); }
            
            // At this point, we can update transaction status because it was found
            $idTrx = $oTrx->idTrx;

            // Check data integrity
            if(floatval($oTrx->amount) != floatval($oOrder->price)) {
                $this->core_model->updateStageTrx($idTrx, self::ORDER_INCORRECT_PRICE);
                throw new Exception("Transaction price do not match. Operation aborted.", 1007);
            }
            
            // Pago46 object
            $oPago46Trx = $this->fs->getByIdTrx($idTrx);
            if(empty($oPago46Trx)) {
                // ORDER_NOT_FOUND
                $this->core_model->updateStageTrx($idTrx, self::ORDER_NOT_FOUND);
                throw new Exception("Couldn't find Pago46 transaction (idTrx = ".$idTrx.")", 1008);
            }

            // Update notification details
            $o = [
                "notificationId" => $post->notification_id,
                "date" => $post->date,
                "status" => $oOrder->status
            ];
            $update = $this->fs->updateTrx($oPago46Trx->idFortySixDegreesTrx, (object)$o);

            if($oOrder->status != "successful") {
                // ORDER_NOT_FOUND
                $this->core_model->updateStageTrx($idTrx, $this->stages[$oOrder->status]);
                throw new Exception("Transaction notification failed or not ended", 1009);
            }

            // OK
            $this->core_model->updateStageTrx($idTrx, self::ORDER_SUCCESSFUL);
            $isOk = true;

        } catch (Exception $e) {

            //http_response_code(500);
            $description = $e->getMessage();
            $this->sanitize->generateLog(__METHOD__, $description);
            
        }


        // -------------------- NOTIFICA OK AL COMERCIO --------------------
        if(!empty($oTrx)) {
            $oNotifyRes = $this->_notify3rdParty(
                                    $oTrx->urlNotify,   // notify 3rd party (commerce)
                                    ($isOk) ? 1 : 0,    // 0 NOK, 1 OK
                                    $oTrx->codExternal, // trx 3rd party (commerce)
                                    $description
                                );
        }
        
        // -----------------------------------------------------------------
        http_response_code(200);
        echo "Engine Notified";

    }


    /**
	 * Result 
	 *
	 * @return void
	 */
    public function voucher($res = null) {

        try {

            // Trx can´t be null
            if(is_null($res)) { throw new Exception("Couldn't detect transaction identifier", 1001); }

            // Decode trx and find it
            $idTrx = decode_url($res);

            $oTrx = $this->trxv2->findByAttr('idTrx', $idTrx);
            if(empty($oTrx)) { throw new Exception("Couldn't find internal transaction (".$idTrx.")", 1002); }

            $oPago46Trx = $this->fs->getByIdTrx($idTrx);
            if(empty($oPago46Trx)) {
                // ORDER_NOT_FOUND
                //this->core_model->updateStageTrx($idTrx, self::ORDER_NOT_FOUND);
                throw new Exception("Couldn't find Pago46 transaction (idTrx = ".$idTrx.")", 1003);
            }

            // Transaction details
            $oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
            $data = [
                "buyOrder"      => $idTrx,
                "bgColor"       => !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault"),
                "fontColor"     => !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault"),
                "logo"          => !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL,
                "amount"        => '$'.number_format(floatval($oTrx->amount), 0, ',', '.'),
                "urlOk"         => $oTrx->urlOk,
                "creationDate"  => date("d/m/Y H:i:s", strtotime($oTrx->creationDate)),
                "status"        => strtoupper($oPago46Trx->status),
                "commerceName"  => $oCommerce->name
            ];
            
            // Check if trx was successful or not
            if($oTrx->idStage != self::ORDER_SUCCESSFUL) {
                // Get information from stage
                $oStage = $this->stage->find($oTrx->idStage);
                throw new Exception($oStage->description, 1004);
            }

            $this->load->view('pago46/success', $data);

        } catch(Exception $e) {

            $this->sanitize->generateLog(__METHOD__, $e->getMessage());

            $data = [
                'logo'      =>  false,
                'message'   => $e->getMessage()
            ];
    
            $this->load->view('transaction/unexpected', $data);
        }
        
    }

    /**
     * Validate required params for startTrx
     *
     * @param object $params
     *
     * @return array
     */
    private function startTrxRequiredParams($params)
    {
        // Check if params exists
        if (!isset($params->idTrx) || !$this->sanitize->validateParams($params->idTrx, false, 'string')) {
            return ['status' => false, 'msg' => 'Missing ID Trk', 'code' => 400];
        } elseif (!isset($params->amount) || !$this->sanitize->validateParams($params->amount, false, 'string')) {
            return ['status' => false, 'msg' => 'Missing Amount', 'code' => 400];
        }

        // Check if params isn't fake
        $trx = $this->trxv2->findByAttr('idTrx', $params->idTrx);
        $permittedTrxStages = [1];

        if (is_null($trx)) {
            return ['status' => false, 'msg' => 'Trx doesn\'t exist', 'code' => 400];
        } /*elseif (!in_array($trx->idStage, $permittedTrxStages)) {
            return ['status' => false, 'msg' => 'Invalid Trx Stage', 'code' => 400];
        }*/

        return ['status' => true];
    }

}
