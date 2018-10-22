<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Channel extends CI_Controller
{
    // ID de canales de pago
    const PAYPAL_ID			= 4;
    const WEBPAY_ID			= 5;
    const BRAINTREE_ID		= 6;
    const ONECLICK_ID		= 8;
    const TUSALDO_ID		= 9;
    const REDCOMPRA_ID		= 10;
    const NO_CHANNELS_ID	= 11;
    const PAYU_ID	        = 12;
    const PAGO46_ID			= 13;
    const CARDINAL_ID		= 14;
    const PAYU_REC_ID		= 15;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('sanitize');
        $this->load->library('encryption');

        $this->load->helper('crypto');
        $this->load->helper('string');

        $this->load->model('transactionv2_model', 'trxv2_model');
        $this->load->model('commercev2_model', '', true);
        $this->load->model('commerceptv2_model', '', true);
        $this->load->model('fieldv2_model', '', true);
    }

    /**
     * List Payments
     *
     * @return string Json
     */
    public function listPayments()
    {
        try {
            $post = $this->sanitize->inputParams(true);
            $oComm = $this->commercev2_model->findByAttr('code', $post->commerceId);

            if (is_null($oComm)) {
                throw new Exception("Commerce does not exist in the system", 400);
            }

            // Si está activo
            if ((int)$oComm->active == 0) {
                throw new Exception('The commmerce is not active', 400);
            }

            // Expirado o no
            $format = 'Y-m-d H:i:s';
            $start  = date($format, strtotime($oComm->contractStartDate));
            $end    = date($format, strtotime($oComm->contractEndDate));
            $now    = date($format, time());

            if (($now < $start) || ($now > $end)) {
                throw new Exception('The commerce is not enabled on the current date', 204);
            }

            if (!isset($post->idCountry) || empty($post->idCountry)) {
                $listPayments = $this->commerceptv2_model->findByAttr('cpt.idCommerce', $oComm->idCommerce);
            } else {
                $listPayments = $this->commerceptv2_model->findByAttrs([
                    'cpt.idCommerce'    => $oComm->idCommerce,
                    'cpt.idCountry'     => $post->idCountry
                ]);
            }

            if (is_null($listPayments)) {
                throw new Exception('No Records Found', 204);
            }

            $response = $this->sanitize->successResponse([
                'totalItems' => count($listPayments),
                'items'      => ( !is_array($listPayments) ) ? [$listPayments] : $listPayments
            ]);
        } catch (Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(),
                $e->getMessage(),
                ['apiVersion' => API_VERSION_2],
                __METHOD__
            );
        }

        $this->sanitize->jsonResponse($response);
    }

    /**
     * Show availables channels for the commerce
     *
     * @return void
     */
    public function showPaymentChannels()
    {
        // URL de error por defecto del motor (por si no llega vista)
        $oTrx           = null;
        $url            = '';
        $showChannels   = false;
        $idTrxEncrypted = 0;
        $amount         = 0;
        $urlError       = '';
        $urlOk          = '';
        $img            = base_url('assets/img/payment_channels').'/';

        try {
            $post = $this->sanitize->inputParams(true);

            $idTrxEncrypted = trim($post->trx);
            $opts           = trim($post->opts); // Optional - availables channels

            if (empty($idTrxEncrypted)) {
                throw new Exception('No se ha podido identificar la transacción', 400);
            }

            $idTrx = decode_url($idTrxEncrypted); // Decrypt idTrx

            $oTrx = $this->trxv2_model->find($idTrx);

            if (is_null($oTrx)) {
                throw new Exception('No se ha pudo determinar la transacción en el sistema', 1001);
            }

            if ($oTrx->idStage > 1) {
                throw new Exception('Transacción inválida', 1001);
            }

            $amount     = $oTrx->amount;
            $urlOk      = $oTrx->urlOk;
            $urlError   = $oTrx->urlError;

            // Valida vigencia de comercio
            $checkCommerce = $this->sanitize->callController(
                base_url('v2/Commerce/validate'),
                (object)['keyCommerce' => $oTrx->idCommerce]
            );

            $checkCommerce = json_decode($checkCommerce);

            if (array_key_exists('error', (array)$checkCommerce)) {
                throw new Exception('Existe un error en el comercio asociado', $checkCommerce->error->code);
            }

            $oComm = $checkCommerce->data;

            // Transacción existe y comercio es válido, así que continúa y evalúa si viene opts,
            // pero es mandatorio el listado de canales respecto al comercio.
            // 1. Si viene, procesa y autoriza los canales enviados
            // 2. Si NO viene, despliega todos los canales asociados al comercio
            $paymentChannelsHtml    = 'No se han encontrado canales de pago para el comercio seleccionado';

            // Support for country. If transaction doesn't have country setted, will find only for commerce ID
            $paymentChannels = [];
            $idCountry = $oTrx->idCountry;
            if(!empty($idCountry)) {
                $paymentChannels = $this->commerceptv2_model->findByAttrs(
                                            'cpt.idCommerce = ' . $oTrx->idCommerce
                                            . ' AND c.idCountry = ' . $idCountry
                                    );
            } else {
                $paymentChannels = $this->commerceptv2_model->findByAttr('cpt.idCommerce', $oTrx->idCommerce);
            }
  
            $paymentChannels = ( count($paymentChannels) == 1 ) ? [$paymentChannels] : $paymentChannels;

            if (!is_null($paymentChannels)) {
                $images = [

                    "".self::WEBPAY_ID      => $img.'webpay.jpg',       // Webpay normal
                    "".self::ONECLICK_ID    => $img.'oneclick.jpg',     // Oneclick
                    "".self::PAYPAL_ID      => $img.'paypal.png',       // Paypal
                    "".self::TUSALDO_ID     => $img.'tusaldo.png',      // Tu saldo
                    "".self::REDCOMPRA_ID   => $img.'cuentarut.jpg',    // Redcompra (es lo mismo que webpay normal)
                    "".self::NO_CHANNELS_ID => $img.'no_channels.png',  // No hay canales disponibles
                    "".self::PAYU_ID        => $img.'payu.png',         // PayU TC
                    "".self::PAGO46_ID      => $img.'pago46.jpg',       // 46Degrees
                    "".self::CARDINAL_ID    => $img.'cardinal.jpg',     // Cardinal
                    "".self::PAYU_REC_ID    => $img.'payu_rec.png'      // Cardinal Recurrencia
                ];

                $module =   '<div class="item">
                                <a href="#" class="payment_type" data-commerce="{COMMERCE_MP}" data-name="{NAME_MP}" data-id="{ID_MP}"><img src="{IMG_MP}" alt="" /></a>
                            </div>';

                // Hay canales para el comercio
                $paymentChannelsHtml    = '';
                $extraFields            = '';
                $showChannels           = true;

                if (!empty($opts)) {
                    // Carga y autoriza canales
                    if (json_decode($opts) != null) {
                        $optsArr = (strlen($opts) == 1) ? [$opts] : json_decode($opts);
                    } elseif (!is_array($opts)) {
                        $optsArr = explode(',', $opts);
                    }

                    foreach ($paymentChannels as $pc) {
                        if (in_array($pc->idPaymentType, $optsArr)) {
                            // Se aplica canal de pago "fantasma", solo para mostrar que
                            // no hay canales reales disponibles.
                            $copy = $module;

                            if ($pc->idPaymentType != self::NO_CHANNELS_ID) {
                                $copy = str_replace("{ID_MP}", $pc->idPaymentType, $copy);
                            } else {
                                // Elimina acción de "canal de pago"
                                $copy = str_replace("#", "javascript:void(0);", $copy);
                                $copy = str_replace('class="payment_type"', "", $copy);
                            }
                            $copy = str_replace("{IMG_MP}", $images["".$pc->idPaymentType], $copy);

                            $extraFields .= $this->_makeFields(
                                                        $pc->idPaymentType,
                                                        $idCountry,
                                                        $oComm->idCommerce
                            );
        
                            $paymentChannelsHtml .= $copy;
                        }
                    }
                    


                } else { // Muestra todos los canales respecto al comercio
                    foreach ($paymentChannels as $pc) {
                        $copy = $module;

                        $copy = str_replace("{ID_MP}", $pc->idPaymentType, $copy);
                        $copy = str_replace("{NAME_MP}", $pc->name, $copy);
                        $copy = str_replace("{IMG_MP}", $images[$pc->idPaymentType], $copy);
                        
                        $extraFields .= $this->_makeFields(
                                                    $pc->idPaymentType,
                                                    $idCountry,
                                                    $oComm->idCommerce
                        );
                  
                        //$copy = str_replace("{EXTRA_FIELDS}", $extraFields, $copy);
                        $copy = str_replace("{COMMERCE_MP}", $oComm->name, $copy);

                        $paymentChannelsHtml .= $copy;
                    }
            
                }

                // Extra fields
                $paymentChannelsHtml = $paymentChannelsHtml.'
                    <div class="modal fade" id="extraInputs" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document" style="text-align:left">'.$extraFields.'</div>
                    </div> 
                ';

            }

            $data = [
                "idTrxEncrypted"    => $idTrxEncrypted,
                "paymentChannels"   => $paymentChannelsHtml,
                "showChannels"      => $showChannels,
                "amount"            => "$".number_format((float)$amount, 0, ",", "."),
                "action"            => base_url("core/processingPaymentAction"),
                "urlOk"             => $urlOk,
                "urlError"          => $urlError,
                "bgColor"           => !is_null($oComm->bgColor) ? "#".$oComm->bgColor : "#".$this->config->item("BgColorDefault"), // fondo blanco por defecto
                "fontColor"         => !is_null($oComm->fontColor) ? "#".$oComm->fontColor : "#".$this->config->item("FontColorDefault"), // fuente gris-oscuro por defecto
                "logo"              => !is_null($oComm->logo) ? $this->config->item("LogosPath").$oComm->logo : null,
                "googleAnalytics"   => !empty($oComm->contactPhone) ? $oComm->contactPhone : null,
                'commerceName'      => $oComm->name
            ];

            $this->load->view('channels/list', $data); // Load Payment Form

        } catch (Exception $e) {
            log_message('error', __METHOD__ ."(". $e->getCode() .") -> ".$e->getMessage());
            $this->_errorView($oTrx, $e->getMessage(), $url);
        }
    }

    /**
     * Create fields structure for payment type + country + commerce
     * 
     * @param $idPaymentType
     * @param $idCountry
     * @param $idCommerce
     */
    private function _makeFields($idPaymentType, $idCountry, $idCommerce) {

        try {

            // Find extra inputs for commerce + country + paymenttype
            $oCommercePt = $this->commerceptv2_model->findByAttrs(
                "cpt.idPaymentType = " . $idPaymentType
                . " AND cpt.idCountry = " . $idCountry
                . " AND cpt.idCommerce = " . $idCommerce
            );
            
            if(empty($oCommercePt)) { throw new Exception("Relation not found", 1001); }
      
            $arrFields = $this->fieldv2_model->findByAttr("cpt.idCommercePt", $oCommercePt->idCommercePt);
            if(empty($arrFields)) { throw new Exception("Fields not found", 1002); }
            $block = '<div class="form-group">
                        <label for="{ID_INPUT}">{LABEL_INPUT}</label>
                        <input type="{TYPE_INPUT}"
                                class="form-control"
                                id="{ID_INPUT}"
                                placeholder="{PLACEHOLDER_INPUT}" {REQUIRED} />
                    </div>';

            // Make HTML
            $html = '';
            
            if(is_array($arrFields)) {
                foreach($arrFields as $oField) {
                    $newBlock = $block;
    
                    $placeHolder = is_null($oField->placeHolder) ? "" : $oField->placeHolder;
                    $required = ($oField->required == 1) ? "required" : "";
    
                    $newBlock = str_replace("{ID_INPUT}", $oField->htmlId, $newBlock);
                    $newBlock = str_replace("{LABEL_INPUT}", $oField->htmlLabel, $newBlock);
                    $newBlock = str_replace("{TYPE_INPUT}", $oField->name, $newBlock);
                    $newBlock = str_replace("{PLACEHOLDER_INPUT}", $placeHolder, $newBlock);
                    $newBlock = str_replace("{REQUIRED}", $required, $newBlock);
    
                    $html .= $newBlock;
                }
            } else {
                $newBlock = $block;
    
                $placeHolder = is_null($arrFields->placeHolder) ? "" : $arrFields->placeHolder;
                $required = ($arrFields->required == 1) ? "required" : "";

                $newBlock = str_replace("{ID_INPUT}", $arrFields->htmlId, $newBlock);
                $newBlock = str_replace("{LABEL_INPUT}", $arrFields->htmlLabel, $newBlock);
                $newBlock = str_replace("{TYPE_INPUT}", $arrFields->name, $newBlock);
                $newBlock = str_replace("{PLACEHOLDER_INPUT}", $placeHolder, $newBlock);
                $newBlock = str_replace("{REQUIRED}", $required, $newBlock);

                $html .= $newBlock;
            }
            
            
            return '
                <div id="frmExtraFields_'.$idPaymentType.'">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Por favor completa los siguientes datos para continuar:</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">'.$html.'</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-dismiss="modal">&iexcl;Listo!</button>
                        </div>
                    </div>
                </div>
            ';

        } catch(Exception $e) {
            return "";
        }
      
    }

    /**
     * Generate Error view for channels
     *
     * @param $oTrx
     * @param $msg
     * @param $url
     */
    private function _errorView($oTrx, $msg, $url)
    {
        if( !is_null($oTrx) ) {
            $commerce                   = $this->commercev2_model->find($oTrx->idCommerce);

            $this->data["bgColor"]      = !is_null($commerce->bgColor) ? "#".$commerce->bgColor : "#".$this->config->item("BgColorDefault");
            $this->data["fontColor"]    = !is_null($commerce->fontColor) ? "#".$commerce->fontColor : "#".$this->config->item("FontColorDefault");
            $this->data["logo"]         = !is_null($commerce->logo) ? $this->config->item("LogosPath").$commerce->logo : NULL;
        } else { // Default
            $this->data["bgColor"]      = "#".$this->config->item("BgColorDefault");
            $this->data["fontColor"]    = "#".$this->config->item("FontColorDefault");
            $this->data["logo"] = NULL;
        }

        $this->data["message"] = $msg;
        $this->data["url"] = $url;

        $this->load->view('error2_view', $this->data);
    }
}