<?php
/**
 * PayU Recurrence
 *
 * Available for Brazil, Mexico, Perú and Colombia only
 *
 * @author     Gastón Orellana <gorellana@digevo.com>
 * @version    1.0
 */
defined('BASEPATH') OR exit('No direct script access allowed');

//require_once(APPPATH.'libraries/payu/lib/PayU.php');
require_once(APPPATH.'libraries/REST_Controller.php');

class Payurecurrence extends REST_Controller {
    
    const CURRENCY = "COP";

    public function __construct() {
        parent::__construct();

        $this->load->library('sanitize');
        $this->load->library('encryption');

        $this->load->helper('creditcard');
        $this->load->helper('crypto');
        $this->load->helper('url');

        $this->load->model('payu_rec_model', '', true);
        $this->load->model('transactionv2_model', '', true);
        $this->load->model("core_model", "", true);
    }

    /**
     * Process requests in PayU Gateway
     *
     * @param string $endpoint
     * @param object|array $params
     * @param boolean $transfer
     * @return void
     */
    private function _toDo($endpoint, $params, $transfer = TRUE) {
        $curl = curl_init($endpoint);
        $dataString = (is_array($params)) ? json_encode($params) : json_encode((array)$params);

        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'Accept-language: es',
            'Content-Length: '.strlen($dataString),
            'Authorization: Basic ' . base64_encode($this->config->item("payu_api_login").":".$this->config->item("payu_api_key"))
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, $transfer);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);

        $exec = curl_exec($curl);
        curl_close($curl);

        return $exec;
    }

    /**
     * Check params
     */
    private function _checkRequiredParams($obj, $required) {

        foreach($required as $attr) {
            if(!isset($obj->$attr)) { $this->_throwException("The field $attr is required", 204); }
            if(is_null($obj->$attr)) { $this->_throwException("The field $attr is not set", 204); }
            if(is_string($obj->$attr) && trim($obj->$attr) == "") { $this->_throwException("The field $attr is not set", 204); }
        }

    }
    
    /**
     * Create plan for user
     * 
     * @method POST
     */
    public function plan_post() {

        $response = "";

        try {
            
            $service = $this->config->item("payu_api_url")."/plans";
            $required = ["description", "idUserExternal", "amount", "idTrx"];

            log_message("debug", "Parameters received PayU Recurrence (plan:post()): " . print_r($this->post(), TRUE));
            if($this->input->method(TRUE) != "POST") $this->_throwException("Incorrect request method", 409);
            if (empty($this->post())) { $this->_throwException("Empty request", 204); }

            // Check params
            $post = (object)$this->post();
            $this->_checkRequiredParams($post, $required);

            // Invoke PayU gateway
            $o = (object)[
                "accountId" => $this->config->item("payu_account_id"),
                "planCode" => "$post->prefix-plan-$post->idUserExternal",
                "description" => $post->description,
                "interval" => $this->config->item("payu_rec_PLAN_INTERVAL"),
                "intervalCount" => $this->config->item("payu_rec_PLAN_INTERVAL_COUNT"),
                "maxPaymentsAllowed" => $this->config->item("payu_rec_PLAN_MAX_PAYMENTS_ALLOWED"),
                "paymentAttemptsDelay" => $this->config->item("payu_rec_PLAN_PAYMENT_ATTEMPTS_DELAY"),
                "additionalValues" => [
                    (object)["name" => "PLAN_VALUE", "value" => $post->amount, "currency" => self::CURRENCY],
                    (object)["name" => "PLAN_TAX", "value" => "0", "currency" =>self::CURRENCY],
                    (object)["name" => "PLAN_TAX_RETURN_BASE", "value" => "0", "currency" =>self::CURRENCY]
                ]
            ];

            // Create trx in payurectrx table
            $bd = (object)[
                "idTrx"                 => $post->idTrx,
                "accountId"             => $o->accountId,
                "planCode"              => $o->planCode,
                "description"           => $o->description,
                "interval"              => $o->interval,
                "intervalCount"         => $o->intervalCount,
                "maxPaymentsAllowed"    => $o->maxPaymentsAllowed,
                "paymentAttemptsDelay"  => $o->paymentAttemptsDelay,
                "planValue"             => $post->amount,
                "planTax"               => "0",
                "planTaxReturnBase"     => "0",
                "creationDate"          => date("Y-m-d H:i:s")
            ];
            $idPayURecTrx = $this->payu_rec_model->initTrx($bd);
            if(is_null($idPayURecTrx)) { $this->_throwException("Couldn't start PayU transaction", 409);  }

            log_message("debug", "Request plan:post(): " . print_r($o, TRUE));
            $action = json_decode($this->_toDo($service, $o));
            log_message("debug", "Response plan:post(): " . print_r($action, TRUE));
            if(empty($action)) { $this->_throwException("No response from PayU Gateway", 500); }

            // Update transaction with the response
            $obj = NULL;
            if(!isset($action->id)) {
                $this->payu_rec_model->updateTrx($idPayURecTrx,
                                                (object)[
                                                    "type" => $action->type,
                                                    "description" => $action->description
                                                ]
                                            );
                $this->_throwException($action->description, 500);
            } else {
                $this->payu_rec_model->updateTrx($idPayURecTrx,
                                                (object)[
                                                    "id" => $action->id,
                                                    "maxPaymentAttempts" => $action->maxPaymentAttempts,
                                                    "maxPendingPayments" => $action->maxPendingPayments,
                                                    "trialDays" => $action->trialDays
                                                ]
                                            );
            }

            // At this point payu response should be ok
            $response = $this->sanitize->successResponse($action);

        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }
        
        $this->sanitize->jsonResponse($response);
    }


    /**
     * Relation between payments plan, payer and credit card.
     * Create subscription in PayU
     * 
     * @method POST
     */
    public function subscription_post() {

        $response = "";

        try {
            
            $service = $this->config->item("payu_api_url")."/subscriptions";
            $required = ["trx", "fullName", "email", "cardNumber", "expMonth", "expYear", "description",
                        "idUserExternal", "amount", "idTrx", "urlOk", "comm", "document"];

            log_message("debug", "Parameters received PayU Recurrence (subscriptions:post()): " . print_r($this->post(), TRUE));
            if($this->input->method(TRUE) != "POST") $this->_throwException("Incorrect request method", 409);
            if (empty($this->post())) { $this->_throwException("Empty request", 204); }

            // Check params
            $post = (object)$this->post();
            $this->_checkRequiredParams($post, $required);


            // Valid card number before send it
            $cc = card_number_valid($post->cardNumber);
            if(!$cc) { $this->_throwException("The credit card number is not valid", 409); }
            if(!card_expiry_valid($post->expMonth, $post->expYear)) { $this->_throwException("The credit card expiration date is not valid", 409); }
            
            $ccClean = card_number_clean($post->cardNumber);

            $request = (object)[
                "immediatePayment" => TRUE,       // true: instante charge, false: next days or trialDays (> 0) later
                "extra1" => $post->trx, // internal trx
                "customer" => (object)[
                    "fullName" => $post->fullName,
                    "email" => $post->email,
                    "creditCards" => [
                        (object)[
                            "name" => $post->fullName,
                            "document" => $post->document,  // Customer's identity document number (RUT)
                            "number" => $ccClean,
                            "expMonth" => $post->expMonth, // 01
                            "expYear" => $post->expYear, // 2018
                            "type" => strtoupper(detect($ccClean)),
                            "address" => (object)[
                                /*"line1" => "Address Name",
                                "city" => "City Name",
                                "country" => "CO",
                                "phone" => "300300300"*/
                            ]
                        ]
                    ]
                ],
                "plan" => (object)[
                    "planCode" => "$post->prefix-plan-$post->idUserExternal",
                    "description" => $post->description,
                    "accountId" => $this->config->item("payu_account_id"),
                    "intervalCount" => $this->config->item("payu_rec_PLAN_INTERVAL_COUNT"),
                    "interval" => $this->config->item("payu_rec_PLAN_INTERVAL"),
                    "maxPaymentsAllowed" => $this->config->item("payu_rec_PLAN_MAX_PAYMENTS_ALLOWED"),
                    //"maxPaymentAttempts" => "3", // number of retries when charge fails
                    "paymentAttemptsDelay" => $this->config->item("payu_rec_PLAN_PAYMENT_ATTEMPTS_DELAY"),
                    //"maxPendingPayments" => "1", // max retries of failed payments before it been cancelled
                    "additionalValues" => [
                        (object)["name" => "PLAN_VALUE", "value" => $post->amount, "currency" => self::CURRENCY],
                        (object)["name" => "PLAN_TAX", "value" => "0", "currency" => self::CURRENCY],
                        (object)["name" => "PLAN_TAX_RETURN_BASE", "value" => "0", "currency" => self::CURRENCY]
                    ]
                ],
                "notifyUrl" => base_url("v2/Payurecurrence/notify")
            ];

            // Save trx in PayURec table
            // Create trx in payurectrx table
            $oPlan = $request->plan;
            $oCustomer = $request->customer;
            $oCreditCard = $oCustomer->creditCards[0];
            $bd = (object)[
                "idTrx"                 => $post->idTrx,
                "extra1"                => $request->extra1, // trx
                "accountId"             => $oPlan->accountId,
                "planCode"              => $oPlan->planCode,
                "description"           => $oPlan->description,
                "interval"              => $oPlan->interval,
                "intervalCount"         => $oPlan->intervalCount,
                "maxPaymentsAllowed"    => $oPlan->maxPaymentsAllowed,
                "paymentAttemptsDelay"  => $oPlan->paymentAttemptsDelay,
                "planValue"             => $oPlan->additionalValues[0]->value,
                "planTax"               => $oPlan->additionalValues[1]->value,
                "planTaxReturnBase"     => $oPlan->additionalValues[2]->value,
                "fullName"              => $oCustomer->fullName,
                "email"                 => $oCustomer->email,
                "document"              => $oCreditCard->document,
                "ccNumber"              => encode_url($oCreditCard->number), // encrypt data
                "ccExpMonth"            => $oCreditCard->expMonth,
                "ccExpYear"             => $oCreditCard->expYear,
                "ccType"                => $oCreditCard->type,
                "creationDate"          => date("Y-m-d H:i:s")
            ];
            

            // If transaction exists, send to voucher
            $oPayUTrx = $this->payu_rec_model->findByAttr("planCode", $bd->planCode);
            //echo print_r($oPayUTrx); exit;
            if(!empty($oPayUTrx)) {
                $this->_postToken($oPayUTrx->extra1);
                return;
            }

            $idPayURecTrx = $this->payu_rec_model->initTrx($bd);
            if(is_null($idPayURecTrx)) { $this->_throwException("Couldn't start PayU transaction", 409);  }

            log_message("debug", "Request subscription:post(): " . print_r($request, TRUE));
            $action = json_decode($this->_toDo($service, $request));
            log_message("debug", "Response subscription:post(): " . print_r($action, TRUE));
            if(empty($action)) { $this->_throwException("No response from PayU Gateway", 500); }

            // Check PayU response
            if(isset($action->id)) {
                // OK
                $this->payu_rec_model->updateTrx($idPayURecTrx,
                                                (object)[
                                                    "subscriptionId" => $action->id,
                                                    "planId" => $action->plan->id,
                                                    "customerId" => $action->customer->id,
                                                    "ccToken" => $action->customer->creditCards[0]->token,
                                                    "quantity" => $action->quantity,
                                                    "installments" => $action->installments,
                                                    "currentPeriodStart" => $action->currentPeriodStart,
                                                    "currentPeriodEnd" => $action->currentPeriodEnd
                                                ]
                                            );
            } else {
                // Errors, these can be as string or list of strings
                $idPayURecTrx_error = $this->payu_rec_model->addError((object)[
                        "idPayURecTrx" => $idPayURecTrx,
                        "type" => $action->type,
                        "creationDate" => date("Y-m-d H:i:s")
                    ]
                );
                
                if(isset($action->errorList)) {
                    // More than 1 error, so it results in array
                    $lst = $action->errorList;
                    foreach($lst as $msg) {
                        $this->payu_rec_model->addErrorDetail((object)[
                                "idPayURecTrx_error" => $idPayURecTrx_error,
                                "message" => $msg,
                                "creationDate" => date("Y-m-d H:i:s")
                            ]
                        );
                    }

                } else {
                    
                    $this->payu_rec_model->addErrorDetail((object)[
                                                "idPayURecTrx_error" => $idPayURecTrx_error,
                                                "message" => $action->description,
                                                "creationDate" => date("Y-m-d H:i:s")
                                            ]
                                        );                                            
                }

                $this->_throwException("Error in PayU Recurrence response", 500);
            }

            // At this point payu response should be ok
            $this->_postToken($post->trx);
            return;

        } catch(Exception $e) {
            /*$response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );*/
            $this->sanitize->generateLog(__METHOD__, $e->getMessage(), 'error');
            
            $pass = [
                'logo'      =>  false,
                'message'   => "Ha ocurrido un problema en la transacción: " . $e->getMessage()
            ];

            $this->load->view('transaction/unexpected', $pass);
        }
        
        //$this->sanitize->jsonResponse($response);
    }

    /**
     * Process transaction response
     * Receive trx encrypted
     */
    public function voucher_post() {

        try {

            // Get transaction
            $post = $this->post();
            if(empty($post)) { $this->_throwException("Empty transaction", 1001); }
            
            $trx = decode_url($post["trx"]);
            $oTrx = $this->transactionv2_model->findByAttr('trx', $trx);
            if(empty($oTrx)) { $this->_throwException("Transaction $trx not found", 1002); }

            $idTrx = $oTrx->idTrx;

            // Subscription details
            $oPayURecTrx = $this->payu_rec_model->findByAttr("extra1", $trx);
            //print_r($oPayURecTrx); exit;
            
            // Commerce
            $oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);

            // Pass data
            $cc = decode_url($oPayURecTrx->ccNumber);
            $data = [
                //"subscriptionId" => $oPayURecTrx->subscriptionId,
                "buyOrder"      => $trx,
                "bgColor"       => !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault"),
                "fontColor"     => !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault"),
                "logo"          => !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL,
                "amount"        => '$'.number_format(floatval($oTrx->amount), 0, ',', '.'),
                "urlOk"         => $oTrx->urlOk,
                "creationDate"  => $oTrx->creationDate,
                "currency"      => self::CURRENCY,
                "paymentMethod" => $oPayURecTrx->ccType,
                "description"   => $oPayURecTrx->description,
                "cardNumber"    => "XXXX-XXXX-XXXX-".substr($cc,strlen($cc)-4,4),
                "expDate"       => $oPayURecTrx->ccExpMonth."/".$oPayURecTrx->ccExpYear
            ];
            
            $this->load->view('payu/success_rec', $data);

        } catch(Exception $e) {
            $this->sanitize->generateLog(__METHOD__, $e->getMessage(), 'error');
            
            $pass = [
                'logo'      =>  false,
                'message'   => "Ha ocurrido un problema en la transacción: " . $e->getMessage()
            ];

            $this->load->view('transaction/unexpected', $pass);
        }
        
    }





    public function notify_post() {
        log_message("debug", "NOTIFY POR POST " . print_r($this->post(), TRUE));
    }

    public function notify_get() {
        log_message("debug", "NOTIFY POR GET " . print_r($this->get(), TRUE));
    }

   
    // ----------------------------------------------------------------------------------------------


    private function checkFormat($post)
    {
        $installments = (isset($post['installments_number'])) ? $post['installments_number'] : null;

        if ( !is_null($installments) && ($installments < 1 || $installments > 32) )
            return false;

        return true;
    }

    /**
	 * Consume initial transaction in order to turn on
	 */
	private function _postToken($trx) {
        $this->load->view("payu/post_token_rec", 
                                [
                                    "trx" => encode_url($trx),
                                    "go" => base_url("v2/payurecurrence/voucher")
                                ]
                        );
	}


    /**
     * Return a JSON
     *
     * @param $resp
     */
    private function jsonResponse($resp)
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($resp));
    }

    /**
     * Launch exception and log message
     */
    private function _throwException($msg, $code) {
        $this->sanitize->generateLog(__METHOD__, $msg);
        throw new Exception($msg, $code);
    }
}