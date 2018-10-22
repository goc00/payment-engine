<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| ------------------
| Webpay-Engine v1.0
| ------------------
| Autor: Gastón Orellana
| Descripción: Administra los formularios y procesos para las transacciones
| Fecha creación: 19/04/2016
*/

class Core extends MY_Controller {


    // Estados de transacción (solo core)
    const NEW_TRX			= 1; // Nueva transacción

    // Extras
    const MAX_CHARS_TRX		= 18; // Número de caracteres

    // ID de canales de pago
    const PAYPAL_ID			= 4;
    const WEBPAY_ID			= 5;
    const BRAINTREE_ID		= 6;
    const ONECLICK_ID		= 8;
    const TUSALDO_ID		= 9;
    const REDCOMPRA_ID		= 10;
    const NO_CHANNELS_ID	= 11;
    const PAYU_ID	        = 12;
    const PAGO46            = 13;
    const CARDINAL_ID       = 14;
    const PAYU_REC_ID       = 15;
	
	const CLOSED_BY_SYSTEM	= 37;

    /**
     * Uri to process onclick payment
     *
     * @var string
     */
    private $uriOneclickInitTrx;

    public function __construct() {
        parent::__construct();

        $this->load->helper('string');
        $this->load->helper('creditcard');
        $this->load->helper('crypto');
        $this->load->helper('url');

        $this->load->library('encryption');
        $this->load->library('webpaylib');
        $this->load->library('sanitize');

        $this->load->model('webpay_model', '', TRUE);
        $this->load->model('operator_model', '', TRUE);
        $this->load->model('payment_type_model', '', TRUE);
        $this->load->model('core_model', '', TRUE);
        $this->load->model('commerceptv2_model', '', TRUE);
        $this->load->model('fieldv2_model', '', TRUE);

        $this->setDefaultUrl(); // Set Urls
    }

    public function index() {
        echo "what are you looking for?";
    }

    /**
     * Set Default Urls
     *
     * @return void
     */
    private function setDefaultUrl()
    {
        $this->uriOneclickInitTrx = base_url('oneclick/doInscriptionAction');
    }


    /**
     * Flujo para el inicio de transacción.
     * Crea la transacción de manera efectiva en el motor de pagos
     * y despliega las opciones de pagos que envía el comercio o, de
     * no recibir nada, despliega todos los canales configurados
     * Información sensible la guarda encriptada por seguridad
     */
    public function startTransaction() {

        $salida = new stdClass();
        $salida->code = -1;
        $salida->message = "";
        $salida->result = NULL;

        $msgRequiredError = "No se ha detectado el parámetro [PARAM]";

        $paramsRequired = array("idUserExternal",
            "codExternal",
            "urlOk",
            "urlError",
            "urlNotify",
            "commerceID",
            "amount");

        try {

            // Recibe los parámetros por POST
            $post = $this->input->post(NULL, TRUE);

            // Valida que venga toda la información requerida
            // Setea de inmediato las URLs de respuesta
            if(empty($post)) throw new Exception("No se ha recibido ningún dato desde el origen", 1000); // que vengan datos desde el origen
            $post =	(object)$post;
            $l = count($paramsRequired);
            for($i=0;$i<$l;$i++) {
                $key = $paramsRequired[$i];
                if(empty($post->$key)) throw new Exception(str_replace("PARAM", $key, $msgRequiredError), 1001);
            }

            $idUserExternal = trim($this->input->post("idUserExternal"));
            $codExternal = trim($this->input->post("codExternal"));
            $urlOk = trim($this->input->post("urlOk"));
            $urlError = trim($this->input->post("urlError"));
            $urlNotify = trim($this->input->post("urlNotify"));
            $commerceID	= trim($this->input->post("commerceID"));
            $amount	= trim($this->input->post("amount"));

            // Toma el commerceId y evalúa si este es válido o no
            $oComm = $this->_isCommerceValid($commerceID);
            if(!$oComm->isValid) throw new Exception($oComm->message, 1002);

            // Genera nuevo TRX
            $o = new stdClass();
            $o->idStage = self::NEW_TRX;
            $o->idCommerce = $oComm->o->idCommerce;
            $o->trx = random_string("alnum", self::MAX_CHARS_TRX);
            $o->amount = $amount;
            $o->idUserExternal = $idUserExternal;
            $o->codExternal = $codExternal;
            $o->urlOk = $urlOk;
            $o->urlError = $urlError;
            $o->urlNotify = $urlNotify;
            $o->idCountry = $oCountry->idCountry;   // 19-02-2018
            $o->creationDate = date("Y-m-d H:i:s");

            log_message("debug", print_r($o, TRUE)); // log de parámetros iniciales

            // CREA NUEVA TRANSACCIÓN EN EL MOTOR
            $idTrx = $this->core_model->newTrx($o);
            if(is_null($idTrx)) throw new Exception("No se pudo iniciar la transacción en el sistema", 1003);

            // OK. El idTrx lo devuelve encriptado y como resultado
            $salida->code = 0;
            $salida->result = encode_url($idTrx);

        } catch(Exception $e) {
            log_message("error", __METHOD__ . "(".$salida->code.") -> ".$salida->message);
            $salida->code = $e->getCode();
            $salida->message = $e->getMessage();
        }


        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($salida));

    }


    /**
     * Process the payment respect to the selected channel.
     * Receive transaction involved and start the whole process
     *
     * @uses $_POST['token'] trx token
     * @uses $_POST['option'] channel
     *
     * @return void
     */
    public function processingPaymentAction()
    {
        $url    = '';
        $oTrx   = null;

        try {
            $tokenOld   = trim($this->input->post('token')); // idTrx encriptado
            $option     = trim($this->input->post('option'));
            
            // Data encoded
            //$post = $this->sanitize->inputParams(true);
			$post = (object)$_POST;

            if (empty($tokenOld)) {
                throw new Exception('No se pudo identificar la transacción', 1000);
            }

            if (empty($option)) {
                throw new Exception('No se pudo identificar la opción de pago', 1001);
            }

            $idTrx = decode_url($tokenOld); // Decode and check trx

            $oTrx = $this->core_model->getTrxById($idTrx); // Get Trx

            if (is_null($oTrx)) {
                throw new Exception('No se pudo determinar la transacción en el sistema', 1002);
            }

            // Valida vigencia de comercio
			$idCommerce = $oTrx->idCommerce;
            $oComm = $this->_isCommerceValid($idCommerce, TRUE); // Busca por idCommerce

            if (!$oComm->isValid) {
                throw new Exception($oComm->message, 1003);
            }
            
			// Will check if exist more trxs AFTER this. If we find them, the current trx
			// will be closed because engine only can process one trx for security reasons.
			$idUserExternal = $oTrx->idUserExternal;
			$trxs = $this->core_model->getTrxsByUserAndComm($idUserExternal, $idCommerce);
			
			if(!is_null($trxs)) {
				// Trxs found
				// Check if last trx is higher than current
				$oTrxLast = NULL;
				if(is_array($trxs)) {
					$oTrxLast = $trxs[0];
				} else {
					$oTrxLast = $trxs;
				}
				if($oTrxLast->idTrx > $idTrx) {
					// Stop current transaction
					$info = new stdClass();
                    $info->idStage = self::CLOSED_BY_SYSTEM;
                    $this->core_model->updateTrx($idTrx, $info); // close current trx
					
					throw new Exception('Se ha detectado un proceso de compra con mayor vigencia al actual', 1009);
				}
			}

            // Find extra fields if it applies
            $oCommercePt = $this->commerceptv2_model->findByAttrs(
                "cpt.idPaymentType = " . $option
                . " AND cpt.idCountry = " . $oTrx->idCountry
                . " AND cpt.idCommerce = " . $idCommerce
            );
            
            $extraFields = [];
            if(!empty($oCommercePt)) {
                $arrFields = $this->fieldv2_model->findByAttr("cpt.idCommercePt", $oCommercePt->idCommercePt);
                if(!empty($arrFields)) {

                    if(is_array($arrFields)) {
                        foreach($arrFields as $oField) {
                            
                            $id = $oField->htmlId;
                        
                            if((int)$oField->required == 1) {
                                if(!isset($post->$id)) {
                                    throw new Exception('No se han detectado los parámetros necesarios para continuar', 1010);
                                }
                                if(empty($post->$id)) {
                                    throw new Exception('El campo '.$oField->htmlLabel.' es requerido', 1010);
                                }
                            }
                            // Add values to extra fields array
                            // Check for type
                            $inputValue = urldecode($post->$id);
                            if($oField->name == "email" && !filter_var($inputValue, FILTER_VALIDATE_EMAIL)) {
                                throw new Exception('La dirección de correo no es válida', 1010);
                            }
                            $extraFields[$id] = $inputValue;
                        
                        }
                    } else {

                        $id = $arrFields->htmlId;
                        if((int)$arrFields->required == 1) {
                            if(!isset($post->$id)) {
                                throw new Exception('No se han detectado los parámetros necesarios para continuar', 1010);
                            }
                            if(empty($post->$id)) {
                                throw new Exception('El campo '.$arrFields->htmlLabel.' es requerido', 1010);
                            }
                        }
                        // Add values to extra fields array
                        // Check for type
                        $inputValue = urldecode($post->$id);
                        if($arrFields->name == "email" && !filter_var($inputValue, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception('La dirección de correo no es válida', 1010);
                        }
                        $extraFields[$id] = $inputValue;

                    }

                }
            }
      
            


            // Procesa pago respecto a selección de canal
            switch ((int)$option) {
                case self::WEBPAY_ID:
                case self::REDCOMPRA_ID:
                    // -----------
                    // WEBPAY PLUS
                    // -----------
                    // Asocia tipo de pago a la transacción
                    $info = new stdClass();
                    $info->idPaymentType = ((int)$option == self::WEBPAY_ID) ? self::WEBPAY_ID : self::REDCOMPRA_ID;
                    $this->core_model->updateTrx($idTrx, $info);

                    // Inicio de transacción en Webpay
                    $obj = array(
                        "idTrx"           	=> $idTrx,
                        "sessionId"			=> $oTrx->trx.date("YmdHis"),
                        "amount"			=> $oTrx->amount
                    );

                    $this->_doPost(base_url()."webpayplus/initTransactionV2", $obj, FALSE); // sin retorno
                    break;

                case self::ONECLICK_ID: // Oneclick Process
                    // --------
                    // ONECLICK
                    // --------
                    $info = new stdClass();                         // Associate payment's type to the transaction
                    $info->idPaymentType = self::ONECLICK_ID;       // Set id from the constant
                    $this->core_model->updateTrx($idTrx, $info);    // Update TRX

                    $obj = [ // Verify if the user already has an enrollment
                        'idCommerce'		=> $idCommerce,
                        'idUserExternal'	=> $idUserExternal
                    ];

                    $oUserOneclick = $this->_doPost(
                        base_url()."oneclick/getDetailsByUserExtAndComm",
                        $obj
                    );
				
                    if (empty($oUserOneclick)) { // Check if exist response
                        throw new Exception('No se pudo obtener información de Oneclick', 1006);
                    }

                    if ($oUserOneclick->code != 0) { // Check error
                        throw new Exception($oUserOneclick->message, 1007);
                    }

                    // Cuenta que posee información del ENRROLAMIENTO, NO es la transacción vigente
                    $account = $oUserOneclick->result;

                    if (is_null($account)) {
                        // La cuenta no existe para el usuario, así que solicita el proceso de enrrolamiento
                        // DEBE mostrar primero, el término y condiciones para la inscripción en Oneclick
                        // Envía la trx al action de oneclick, que deberá iniciar proceso de inscripción
                        $obj = array(
                            "token" => $tokenOld // va encriptado
                        );
                        $obj['selection'] = 'ok'; // bypass ok
                        $this->_doPost($this->uriOneclickInitTrx, $obj, FALSE);
                    } else {
                        // Ya existe enrrolamiento, así que comienza cargo
                        // Valida la existencia del tbkUser, nunca debería suceder, pero por seguridad se valida
                        if (empty($account->tbkUser)) {
                            throw new Exception('No se pudo determinar la cuenta de cargo', 1008);
                        }

                        $obj = [
                            'idTrx'         => $idTrx, // transacción activa, NO la cuenta
                            'codExternal'   => $oTrx->codExternal,
                            'username'      => $idUserExternal,
                            'tbkUser'       => $account->tbkUser,
                            'amount'        => $oTrx->amount,
                            'description'   => $oComm->o->description,
                            'urlOk'         => $oTrx->urlOk,
                            'urlError'      => $oTrx->urlError,
                            'urlNotify'     => $oTrx->urlNotify
                        ];

                        $this->_doPost(base_url()."oneclick/authorize", $obj, FALSE);
                    }
                    break;
                case self::PAGO46:

                    // ------
                    // PAGO46
                    // ------

                    // Update channel payment
                    $info = [
                        "idPaymentType" => self::PAGO46
                    ];
                    $this->core_model->updateTrx($idTrx, (object)$info); 
                    
                    // Start transaction in 46Degrees
                    // Passing false as third parameters to prevent response and redirect
                    $res = $this->sanitize->callController(base_url('v2/Fourtysix/startTrx'), $oTrx);
 
                    if(!empty($res)) {
                        //log_message("debug", "StartTrx Pago46 : " . print_r($res, true));
                        $oJson = json_decode($res);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new Exception('Error en el formato de respuesta: ' . json_last_error_msg(), 1001);
                        }
                        redirect($oJson->data->redirect_url);
                    }
                    break;

                case self::PAYU_ID:

                    // ----
                    // PAYU 
                    // ----
                    // These payment types require extra fields. If they are not setted
                    // transaction will be terminated.
                    if(empty($extraFields)) {
                        throw new Exception("Can't find required fields. Transaction aborted.", 1001);
                    }
                    // Add extra fields
                    foreach($extraFields as $key => $value) { $oTrx->$key = $value; }

                    // Update channel payment
                    $info = [
                        "idPaymentType" => self::PAYU_ID
                    ];
                    $this->core_model->updateTrx($idTrx, (object)$info); 

                    $this->sanitize->callController(base_url('v2/Payuprovider/startTrx'), $oTrx, FALSE);
                    break;
                
                /*
                 *   -----------------------
                 *   PayU recurrence payment
                 *   -----------------------
                 *   Need extra fields to process transaction. If they are not setted
                 *   transaction will be terminated.
                 */
                case self::PAYU_REC_ID:

                    // Update channel payment
                    $info = ["idPaymentType" => self::PAYU_REC_ID];
                    $this->core_model->updateTrx($idTrx, (object)$info); 
                    
                    $obj = [
                        "trx"               => $oTrx->trx,
                        "description"       => $oComm->o->name." Recurrencia",
                        "prefix"            => $oComm->o->prefix,
                        "idUserExternal"    => $oTrx->idUserExternal,
                        "amount"            => $oTrx->amount,
                        "idTrx"             => $oTrx->idTrx,
                        "urlOk"             => $oTrx->urlOk,
                        "comm"              => $oComm
                    ];
                    
                    // Get "fullName", "email", "cardNumber", "expMonth", "expYear"
                    if(empty($extraFields)) { throw new Exception("Can't find required fields. Transaction aborted.", 1001); }
                    foreach($extraFields as $key => $value) { $obj[$key] = $value; } // Add extra fields

                    // Create plan ()
                    // "fullName", "email", "cardNumber", "expMonth", "expYear"
                    $this->sanitize->callController(base_url('v2/Payurecurrence/subscription'), (object)$obj, FALSE);
                    /*if (!array_key_exists('data', (array)$res)) {
                        throw new Exception('PayU transaction has failed: ' . $res->error->message, 500);
                    }*/
                    break;
                
                case self::CARDINAL_ID:
                    if(empty($extraFields)) {
                        throw new Exception("Can't find required fields. Transaction aborted.", 1001);
                    }
                    // Add extra fields
                    foreach($extraFields as $key => $value) { $oTrx->$key = $value; }

                    $info = [
                        "idPaymentType" => self::CARDINAL_ID
                    ];
                    $this->core_model->updateTrx($idTrx, (object)$info); 

                    $this->sanitize->callController(base_url('v2/Cardinalprovider/startTrx'), $oTrx, FALSE);
                    break;

                case self::TUSALDO_ID:

                    // Contra Tu Saldo
                    $res = $this->services->makePaymentTuSaldo($ooTrx->trx,
                        $option,
                        $urlOk,
                        $urlError,
                        $oUser->ani);

                    $res = json_decode($res);

                    if($res->code == 0) {
                        //$ok = str_replace("{TRX}", $ooTrx->trx, $urlOk);
                        redirect($urlOk);
                    } else {
                        redirect($urlError);
                    }
                    break;

                case self::NO_CHANNELS_ID:

                    // No hay métodos de pago disposibles
                    break;

                default:
                    throw new Exception("Lo sentimos, pero el método de pago seleccionado aún no se encuentra disponible", 1003);
                    break;
            }
        } catch(Exception $e) {
            log_message("error", __METHOD__ ."(". $e->getCode() .") -> ".$e->getMessage());
            $this->_errorView($oTrx, $e->getMessage(), $url);
        }
    }



    /**
     * Vista de error del motor
     */
    private function _errorView($oTrx, $msg, $url) {

        if(!is_null($oTrx)) {

            $oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
            $this->data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
            $this->data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
            $this->data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;

        } else {

            // Por defecto
            $this->data["bgColor"] = "#".$this->config->item("BgColorDefault");
            $this->data["fontColor"] = "#".$this->config->item("FontColorDefault");
            $this->data["logo"] = NULL;

        }

        $this->data["message"] = $msg;
        $this->data["url"] = $url;

        $this->load->view("error2_view", $this->data);
    }

    public function encodeData() {

        $salida = new stdClass();
        $salida->code = -1;
        $salida->message = "";
        $salida->result = NULL;

        $msgRequiredError = "No se ha detectado el parámetro [PARAM]";

        $paramsRequired = array("data");

        try {

            // Recibe los parámetros por POST
            $post = $this->input->post(NULL, TRUE);

            // Valida que venga toda la información requerida
            // Setea de inmediato las URLs de respuesta
            if(empty($post)) throw new Exception("No se ha recibido ningún dato desde el origen", 1000); // que vengan datos desde el origen
            $post =	(object)$post;
            $l = count($paramsRequired);
            for($i=0;$i<$l;$i++) {
                $key = $paramsRequired[$i];
                if(empty($post->$key)) throw new Exception(str_replace("PARAM", $key, $msgRequiredError), 1001);
            }

            $data = trim($this->input->post("data"));

            // OK. El idTrx lo devuelve encriptado y como resultado
            $salida->code = 0;
            $salida->result = encode_url($data);

        } catch(Exception $e) {
            log_message("error", __METHOD__ . "(".$salida->code.") -> ".$salida->message);
            $salida->code = $e->getCode();
            $salida->message = $e->getMessage();
        }


        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($salida));

    }

    public function decodeData() {

        $salida = new stdClass();
        $salida->code = -1;
        $salida->message = "";
        $salida->result = NULL;

        $msgRequiredError = "No se ha detectado el parámetro [PARAM]";

        try {

            // Recibe los parámetros por POST
            //$post = $this->input->post(NULL, TRUE);

            $data = $this->input->post("data");
            $data = "8d1dc62313adf5cd013484e4090671921c1b0c02460a12fd87d8d2463dc11d87719512ede2ab6008ce7d51e664ae74533c4b84894e5bec213b63fac610c3e2beROYYU7JdzS6tpsC1cYMnfFhM9Hq0Iilkrah2rAH9PFo-";

            // OK. El idTrx lo devuelve encriptado y como resultado
            $salida->code = 0;
            $salida->result = decode_url($data);

        } catch(Exception $e) {
            log_message("error", __METHOD__ . "(".$salida->code.") -> ".$salida->message);
            $salida->code = $e->getCode();
            $salida->message = $e->getMessage();
        }


        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($salida));

    }

    // ----------- LÓGICA ANTIGUA, DEBE SER DEPRECADA ------------

    /**
     * Hace envío del token a url por POST, a través de variable token_ws
     */
    private function _postToken($token, $url) {

        try {

            $data["token"] = $token;
            $data["url"] = $url;

            // Dejar token en session por si se anula

            $s = array(
                "token_tmp" => $token,
                "token_new_flow" => $token
            );
            $this->session->set_userdata($s);

            $this->load->view("webpay/post_token", $data);

        } catch(Exception $e) {
            $this->_error($e->getMessage());
        }

    }


    // -------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Verifica la validez del comercio
     */
    private function _isCommerceValid($code, $isIdCommerce = FALSE) {

        $salida = new stdClass();
        $salida->isValid = TRUE;
        $salida->message = "no action";
        $salida->o = NULL;
        $format = "Y-m-d H:i:s";

        try {

            $oComm = NULL;
            if($isIdCommerce) {
                // Busca por idCommerce
                $oComm = $this->core_model->getCommById($code);
            } else {
                $oComm = $this->core_model->getCommerceByCode($code);
            }

            if(is_null($oComm)) throw new Exception("El comercio proporcionado no existe en el sistema", 1000);

            // Si está activo
            if((int)$oComm->active == 0) throw new Exception("El comercio no se encuentra activo", 1001);

            // Expirado o no
            $fechaIni = date($format, strtotime($oComm->contractStartDate));
            $fechaFin = date($format, strtotime($oComm->contractEndDate));
            $now = date($format, time());
            if(($now < $fechaIni) || ($now > $fechaFin)) throw new Exception("El comercio no se encuentra disponible", 1002);

            $salida->o = $oComm;

        } catch(Exception $e) {
            $salida->isValid = FALSE;
            $salida->message = $e->getMessage();
        }

        return $salida;
    }

    
   

    public function CheckUptime() {

        $curl = curl_init(base_url()."core/IsUptime");
        $params = new stdClass();
        $params->ok = "1";
        $data_string = json_encode((array)$params);
        //print_r($data_string); exit;

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

        $exec = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($exec);
        /*print_r("exec: " . $exec)."<br />";
        print_r("exec2: " . $response);
        exit;*/

        $out = $response->ok == 1 ? "OK" : "ERROR";

        echo $out;
    }
    public function IsUptime() {
        $post = file_get_contents("php://input");
        echo $post;
    }


    /**
     * Levanta vista de error con mensaje
     */
    private function _error($msj) {
        log_message("error", $msj);
        $data["message"] = $msj;
        $this->load->view("error", $data);
    }

}