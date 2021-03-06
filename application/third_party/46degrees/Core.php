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
    const PAYU_ID			= 12;
	const PAGO46            = 13;
	
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
        $this->load->model('webpay_model', '', TRUE);
        $this->load->model('operator_model', '', TRUE);
        $this->load->model('payment_type_model', '', TRUE);
        $this->load->model('core_model', '', TRUE);

	    $this->load->library('sanitize');

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
     * Muestra los canales de pago disponibles para el comercio
     */
    public function showPaymentChannels() {

        // URL de error por defecto del motor (por si no llega vista)
        $oTrx = NULL;
        $url = "";
        $showChannels = FALSE;
        $idTrxEncrypted = 0;
        $amount = 0;
        $urlError = "";
        $urlOk = "";
        $img = base_url()."assets/img/payment_channels/";

        try {

            $idTrxEncrypted = trim($this->input->post("trx")); // TRX encriptada
            $opts = trim($this->input->post("opts")); // NO es obligatorio, canales concretos enviados por el comercio

            if(empty($idTrxEncrypted)) throw new Exception("No se ha podido identificar la transacción", 1000);

            // Viene la transacción (idTrx), así que la desencripta y verifica si existe
            $idTrx = decode_url($idTrxEncrypted);
            $oTrx = $this->core_model->getTrxById($idTrx);
            if(is_null($oTrx)) throw new Exception("No se ha pudo determinar la transacción en el sistema", 1001);

            $amount = $oTrx->amount;
            $urlOk = $oTrx->urlOk;
            $urlError = $oTrx->urlError;

            // Valida vigencia de comercio
            $oComm = $this->_isCommerceValid($oTrx->idCommerce, TRUE); // Busca por idCommerce
            if(!$oComm->isValid) throw new Exception($oComm->message, 1002);

            // Transacción existe y comercio es válido, así que continúa y evalúa si viene opts,
            // pero es mandatorio el listado de canales respecto al comercio.
            // 1. Si viene, procesa y autoriza los canales enviados
            // 2. Si NO viene, despliega todos los canales asociados al comercio
            $paymentChannelsHtml = "No se han encontrado canales de pago para el comercio seleccionado";
            $paymentChannels = $this->core_model->listPaymentMethodsByIdCommerce($oTrx->idCommerce);

            if(!is_null($paymentChannels)) {


                $images = array(
                    "".self::WEBPAY_ID => $img."webpay.png", 			// Webpay normal
                    "".self::ONECLICK_ID => $img."oneclick.png", 		// Oneclick
                    "".self::PAYPAL_ID => $img."paypal.png", 			// Paypal
                    "".self::TUSALDO_ID => $img."tusaldo.png", 			// Tu saldo
                    "".self::REDCOMPRA_ID => $img."cuentarut.png", 		// Redcompra (es lo mismo que webpay normal)
                    "".self::NO_CHANNELS_ID => $img."no_channels.png",	// No hay canales disponibles
                    "".self::PAYU_ID => $img."payu.png"					// PayU
                );

                $module = '<div class="item"><a href="#" class="payment_type" data-id="{ID_MP}"><img src="{IMG_MP}" alt="" /></a></div>';

                // Hay canales para el comercio
                $paymentChannelsHtml = "";
                $showChannels = TRUE;

                if(!empty($opts)) {

                    // Carga y autoriza canales
                    $optsArr = json_decode($opts);
                    foreach($paymentChannels as $pc) {
                        if(in_array($pc->idPaymentType, $optsArr)) {

                            // Se aplica canal de pago "fantasma", solo para mostrar que
                            // no hay canales reales disponibles.
                            $copy = $module;

                            if($pc->idPaymentType != self::NO_CHANNELS_ID) {
                                $copy = str_replace("{ID_MP}", $pc->idPaymentType, $copy);
                            } else {
                                // Elimina acción de "canal de pago"
                                $copy = str_replace("#", "javascript:void(0);", $copy);
                                $copy = str_replace('class="payment_type"', "", $copy);
                            }
                            $copy = str_replace("{IMG_MP}", $images["".$pc->idPaymentType], $copy);
                            $paymentChannelsHtml .= $copy;

                        }
                    }

                } else {

                    // Muestra todos los canales respecto al comercio
                    foreach($paymentChannels as $pc) {
                        $copy = $module;
                        $copy = str_replace("{ID_MP}", $pc->idPaymentType, $copy);
                        $copy = str_replace("{IMG_MP}", $images[$pc->idPaymentType], $copy);
                        $paymentChannelsHtml .= $copy;
                    }

                }

            }

            // Carga vista con formulario de pago
            $objComm = $oComm->o;
            $data = array(
                "idTrxEncrypted" => $idTrxEncrypted,
                "paymentChannels" => $paymentChannelsHtml,
                "showChannels" => $showChannels,
                "amount" => "$".number_format((float)$amount, 0, ",", "."),
                "action" => base_url()."core/processingPaymentAction",
                "urlOk" => $urlOk,
                "urlError" => $urlError,
                "bgColor" => !is_null($objComm->bgColor) ? "#".$objComm->bgColor : "#".$this->config->item("BgColorDefault"), // fondo blanco por defecto
                "fontColor" => !is_null($objComm->fontColor) ? "#".$objComm->fontColor : "#".$this->config->item("FontColorDefault"), // fuente gris-oscuro por defecto
                "logo" => !is_null($objComm->logo) ? $this->config->item("LogosPath").$objComm->logo : NULL
            );

            $this->load->view("list_payment_channels_view", $data);

        } catch(Exception $e) {
            log_message("error", __METHOD__ ."(". $e->getCode() .") -> ".$e->getMessage());
            $this->_errorView($oTrx, $e->getMessage(), $url);
        }

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

            if (empty($tokenOld)) {
                throw new Exception('No se pudo identificar la transacción', 1000);
            }

            if (empty($option)) {
                throw new Exception('No se pudo identificar la opción de pago', 1001);
            }

            $idTrx = decode_url($tokenOld); // Decode and check trx

            $oTrx = $this->core_model->getTrxById($idTrx); // Get Trx

            if (is_null($oTrx)) {
                throw new Exception('No se ha pudo determinar la transacción en el sistema', 1002);
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

            // Procesa pago respecto a selección de canal
            switch ((int)$option) {
                case self::WEBPAY_ID:
                case self::REDCOMPRA_ID:

                    // WEBPAY PLUS

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

                    $this->_doPost(base_url()."webpayplus/initTransactionV2", (array)$obj, FALSE); // sin retorno
                    break;
                case self::ONECLICK_ID: // Oneclick Process
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
					$this->pago46([
						'trx' => $oTrx
					]);
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

	private function pago46($opts)
	{
		$trx = (array)$opts['trx'];
		echo $this->sanitize->callController(base_url('v2/FourtySix/startTrx'), $trx);

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
     * Método inicial que recibe los parámetros core para la transacción.
     * Recibirá:
     * - IDUserWP
     * - IDProducto
     * - IDCobro
     * - Amount
     * - UrlOk
     * - UrlError
     * - UrlNotify
     * - CommerceID // código que se le designa previamente al comercio
     * Retornará:
     * - Trx			: TRX generado por el sistema
     * - ErrNumber		: 0 sin error, <> 0 = error
     * - ErrMessage		: Glosa descriptiva
     * - UrlFrmPayment	: URL del formulario de método de pago
     */
    public function initTransaction() {
        $this->_initTransactionGeneral(); // mantiene lógica anterior
    }

    /**
     * Nuevo método de inicio de transacción, que además de lo anterior, deberá recibir el medio de pago seleccionado
     * desde landing universal y redireccionar o lo que corresponda al canal respectivo de manera automática
     * Ahora el motor gestiona automáticamente el despliegue inicial del ambiente de canal de pago
     * Ya NO administra el formulario de recepción de datos
     */
    public function initTransaction2() {

        $res = $this->_initTransactionGeneral(TRUE); // mantiene lógica anterior

        try {

            if($res->errNumber != 0) throw new Exception($res->errMessage, 1000);

            // Si no hay error, vuelve a verificar la trx
            // Recibe el TRX codificado y verifica que exista en el sistema
            $trx = decode_url($res->trx);
            $oTrx = $this->core_model->getTrx($trx);
            if(is_null($oTrx)) throw new Exception("La transacción proporcionada no existe en el sistema", 1002);

            // Con la TRX válida, determina que canal de pago invocar
            switch($oTrx->idPaymentType) {

                case 1: // PatPass

                    $TRXing = FALSE; // flag para cuando se inicia la transacción

                    try {

                        $oTrxWp = new stdClass();
                        $TRXing = TRUE;

                        // Interpreta el monto
                        $amount = $oTrx->amount;

                        // INicio de transacción (múltiples acciones sobre base datos)
                        $this->core_model->inicioTrx();

                        // Pasa a Stage de inicio la trx para webpay
                        $upd = new stdClass();
                        $upd->idStage = parent::NUEVA_TRX_WP;
                        $res = $this->core_model->updateTrx($oTrx->idTrx, $upd);
                        if(!$res) throw new Exception("No se pudo actualizar la transacción", 1005);

                        // Crea registro en la tabla de PatPass
                        $oTrxWp->idTrx = $oTrx->idTrx;
                        $oTrxWp->sessionId = $trx.date("YmdHis");
                        $oTrxWp->amount = $amount;
                        $oTrxWp->buyOrder = "WP".str_replace(".","",microtime(TRUE));
                        $oTrxWp->cardHolderId = $this->session->userdata("ur");
                        $oTrxWp->cardHolderName = $this->session->userdata("un");
                        $oTrxWp->cardHolderLastName1 = $this->session->userdata("uln1");
                        $oTrxWp->cardHolderLastName2 = $this->session->userdata("uln2");
                        $oTrxWp->cardHolderMail = $this->session->userdata("um");
                        $oTrxWp->cellPhoneNumber = $this->session->userdata("ucpn");
                        $oTrxWp->expirationDate = date("Y-m-d", strtotime(parent::EXP_DATE_WP));
                        $oTrxWp->creationDate = date("Y-m-d H:i:s");

                        // Destruye session
                        $this->session->unset_userdata("ur");
                        $this->session->unset_userdata("un");
                        $this->session->unset_userdata("uln1");
                        $this->session->unset_userdata("uln2");
                        $this->session->unset_userdata("um");
                        $this->session->unset_userdata("ucpn");

                        // SOLO PARA DESARROLLO
                        // En producción debe tomar el RUT y solo dejarle el guión
                        $sId = "";
                        if(ENVIRONMENT == "development") {
                            $sId = "335456675433";
                        } else {
                            $sId = $oTrxWp->cardHolderId;
                            // Elimina los puntos del RUT para generar correctamente el serviceId
                            $sId = str_replace(".","",$sId);
                            // Agrega prefijo dependiendo del producto
                            // Busca prefijo en bd
                            $pt = $this->core_model->getCommById($oTrx->idCommerce);
                            if(is_null($pt)) throw new Exception("No se pudo determinar el comercio vinculado", 1005);

                            $sId = $pt->prefix.$sId;
                        }

                        $oTrxWp->serviceId = $sId;

                        // Inserta registro
                        $trxWpBd = $this->webpay_model->initTrx($oTrxWp);
                        if(is_null($trxWpBd)) throw new Exception("No se pudo almacenar la información para WebPay", 1006);

                        // Llegado este punto, todo ok y hace commit
                        $this->core_model->commitTrx();
                        $TRXing = FALSE;

                        // *************************************************************
                        // Invoca a la librería de WebPay para intentar hacer el PatPass
                        // *************************************************************
                        log_message("debug", "oTrxWp(_startTrxWebpay) -> ".print_r($oTrxWp, TRUE));
                        $webpay = $this->webpaylib->initTransaction($oTrxWp);
                        if(is_null($webpay)) {

                            $this->core_model->updateStageTrx($oTrx->idTrx, parent::FALLO_INIT_TRX_WP);
                            log_message("error", "No se pudo iniciar la transacción con Transbank");

                            $data["buyOrder"] = $oTrxWp->buyOrder;
                            $data["errorUrl"] = $oTrx->urlError;

                            $this->load->view("webpay/reject", $data);

                            return;
                        }

                        // Recibe el token y la url a donde hacer POST con este
                        $token = $webpay->token;
                        $urlX = $webpay->url;

                        $this->core_model->updateStageTrx($oTrx->idTrx, parent::POST_TOKEN_WP);

                        $upd = new stdClass();
                        $upd->token = $token;
                        $res = $this->webpay_model->updateTrx($trxWpBd, $upd);
                        if(!$res) throw new Exception("No se pudo actualizar la información del token", 1005);

                        $this->_postToken($token, $urlX);

                    } catch(Exception $e) {
                        if($TRXing) $this->core_model->rollbackTrx();
                        $this->_error($e->getMessage());
                    }

                    break;

                case 4:

                    // Envía data inicial a módulo PayPal para procesar pago
                    $service = base_url()."paypalnvp/doPayPalNvp2";
                    $curl = curl_init($service);

                    $post["trx"] = $trx;

                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($curl, CURLOPT_POST, TRUE);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $post);

                    $exec = json_decode(curl_exec($curl));
                    if($exec->errNumber == 0) {

                        // -------------------------------------------
                        // STEP 2: Redirección de cliente a PayPal para autorización
                        // -------------------------------------------
                        // Almacena en session el TRX de manera temporal para poder derivar frente a error
                        $this->session->set_userdata("trx", $post["trx"]);
                        $uri = str_replace("{TOKEN}", $exec->token, $this->config->item("PayPalAuthorization"));
                        $data["url"] = $uri;

                        $this->load->view("paypal/post_token", $data);
                    }

                    break;

                case 5:

                    break;
            }

        } catch(Exception $e) {
            echo $e->getMessage();
        }

    }

    /**
     * Hace una limpieza del string recibido, para permitir correcto envío de datos
     * al ambiente de Transbank
     */
    private function _cleanInput($str, $onlyNumbers = NULL) {

        if(is_null($onlyNumbers)) {
            $str = str_replace("-", " ", $str); // interpreta guión como espacio
            $str = str_replace("_", " ", $str);
            $str = preg_replace("/[^A-Za-z[:space:]]/", '', $str);
        } else {
            // solo números
            $str = preg_replace("/[^0-9]/", '', $str);
        }

        return $str;
    }

    /**
     * Lógica inicial abstraída en método genérico para manejar las N opciones de integración
     */
    private function _initTransactionGeneral($new = NULL) {

        $salida = new stdClass();
        $salida->errNumber = 0;
        $salida->errMessage = "";
        $salida->trx = NULL;
        $salida->urlFrmPago = NULL;

        try {

            // Todos los campos obligatorios
            $IDUserExternal = trim($this->input->post("IDUserExternal"));
            $IDApp = trim($this->input->post("IDApp"));
            $IDPlan = trim($this->input->post("IDPlan"));
            $IDCountry = trim($this->input->post("IDCountry"));
            $UrlOk = trim($this->input->post("UrlOk"));
            $UrlError = trim($this->input->post("UrlError"));
            $UrlNotify = trim($this->input->post("UrlNotify"));
            $UrlImg = trim($this->input->post("UrlImg"));
            $CommerceID = trim($this->input->post("CommerceID")); // code
            $CodigoAnalytics = trim($this->input->post("CodigoAnalytics"));

            $amount = NULL;

            // Verifica si está integrándose con lógica nueva
            if($new === TRUE) {
                // Toma el canal de pago seleccionado en el landing universal
                $idPaymentType = trim($this->input->post("PaymentType"));
                $withTrial = trim($this->input->post("TrialEnabled"));
                // Valida que el tipo de pago exista en el motor
                $oPaymentType = $this->payment_type_model->getById($idPaymentType);
                $OldFlow = trim($this->input->post("OldFlow"));

                if(is_null($oPaymentType)) throw new Exception("El canal de pago seleccionado no existe en el sistema", 1000);
                if(is_null($OldFlow)) throw new Exception("No se ha definido el tipo de flujo a utilizar", 1000);

                // Si el tipo es válido, verifica si es PatPass, porque requiere más parámetros por la naturaleza
                // de los datos requeridos para la integración  de este canal
                if((int)$idPaymentType == 1) {

                    $UserRut = trim($this->input->post("UserRut"));
                    $UserName = $this->_cleanInput(trim($this->input->post("UserName")));
                    $UserLastName1 = $this->_cleanInput(trim($this->input->post("UserLastName1")));
                    $UserLastName2 = $this->_cleanInput(trim($this->input->post("UserLastName2")));
                    $UserMail = trim($this->input->post("UserMail"));
                    $UserCellPhoneNumber = $this->_cleanInput(trim($this->input->post("UserCellPhoneNumber")), TRUE); // solo numeros

                    if(trim($UserRut) == ""
                        || trim($UserName) == ""
                        || trim($UserLastName1) == ""
                        || trim($UserLastName2) == ""
                        || trim($UserMail) == ""
                        || trim($UserCellPhoneNumber) == "") throw new Exception("No han sido recibidos todos los parámetros para PatPass", 1003); // que el valor del param no venga vacío

                    // Deja en session los valores para tomarlos en proceso posterior
                    $session = array(
                        "ur" => $UserRut,
                        "un" => $UserName,
                        "uln1" => $UserLastName1,
                        "uln2" => $UserLastName2,
                        "um" => $UserMail,
                        "ucpn" => $UserCellPhoneNumber
                    );
                    $this->session->set_userdata($session);
                    // $this->session->unset_userdata('token_tmp');

                } else if((int)$idPaymentType == 4 && (int)$withTrial == 1) {

                    // Verifica si es PayPal y si viene con Trial
                    $TrialAmount = trim($this->input->post("TrialAmount"));
                    $TrialPeriod = trim($this->input->post("TrialPeriod"));
                    $TrialFrecuency = trim($this->input->post("TrialFrecuency"));
                    $TrialTotalCycles = trim($this->input->post("TrialTotalCycles"));

                    if(trim($TrialAmount) == ""
                        || trim($TrialPeriod) == ""
                        || trim($TrialFrecuency) == ""
                        || trim($TrialTotalCycles) == "") throw new Exception("No han sido recibidos todos los parámetros para PatPass con período Trial activado", 1003);

                }

                // Ahora recibe el monto también
                $amount = trim($this->input->post("Amount"));
                if($amount == "") throw new Exception("No se ha definido el monto a procesar", 1003);
            }

            $mess = "No se han definido todos los atributos necesarios";
            if($IDUserExternal == "") throw new Exception($mess." {IDUserExternal}", 1000);
            if($IDApp == "") throw new Exception($mess." {IDApp}", 1000);
            if($IDPlan == "") throw new Exception($mess." {IDPlan}", 1000);
            if($IDCountry == "") throw new Exception($mess." {IDCountry}", 1000);
            if($UrlOk == "") throw new Exception($mess." {UrlOk}", 1000);
            if($UrlError == "") throw new Exception($mess." {UrlError}", 1000);
            if($UrlNotify == "") throw new Exception($mess." {UrlNotify}", 1000);
            if($CommerceID == "") throw new Exception($mess." {CommerceID}", 1000);
            if($CodigoAnalytics == "") throw new Exception($mess." {CodigoAnalytics}", 1000);

            // Toma el commerceId y evalúa si este es válido o no
            $validComm = $this->_isCommerceValid($CommerceID);
            if(!$validComm->isValid) throw new Exception($validComm->message, 1001);

            // Genera nuevo TRX
            $o = new stdClass();
            $o->idStage = parent::NUEVA_TRX;
            $o->idCommerce = $validComm->o->idCommerce;
            $o->trx = random_string("alnum", parent::MAX_N_TRX_WP);
            $o->amount = $amount;
            $o->codAnalytics = $CodigoAnalytics;
            $o->idUserExternal = $IDUserExternal;
            $o->idApp = $IDApp;
            $o->idPlan = $IDPlan;
            $o->idCountry = $IDCountry;
            $o->urlOk = $UrlOk;
            $o->urlError = $UrlError;
            $o->urlNotify = $UrlNotify;
            $o->urlImg = $UrlImg;
            //$o->oldFlow = $OldFlow;
            $o->creationDate = date("Y-m-d H:i:s");
            // Si es nueva implementación, agrega el tipo de pago de inmediato
            if($new === TRUE) {

                $o->idPaymentType = $idPaymentType;
                $o->trialEnabled = $withTrial;
                $o->oldFlow = $OldFlow;

                if((int)$idPaymentType == 4 && (int)$withTrial == 1) {

                    $o->trialAmount = $TrialAmount;
                    $o->trialPeriod = $TrialPeriod;
                    $o->trialFrecuency = $TrialFrecuency;
                    $o->trialTotalCycles = $TrialTotalCycles;

                }
            } else {
                $o->oldFlow = 1; // obviamente siempre será old
            }

            log_message("debug", print_r($o, TRUE)); // log de parámetros iniciales

            // CREA NUEVA TRANSACCIÓN EN EL MOTOR
            $idTrx = $this->core_model->newTrx($o);
            if(is_null($idTrx)) throw new Exception("No se pudo iniciar la transacción en el sistema", 1002);

            // Responde OK con lo generado
            // El TRX lo devuelve encriptado
            $salida->errMessage = "OK";
            $salida->urlFrmPago = base_url()."core/startTrx/?trx={TRX}&comm={COMM}";
            $salida->trx = encode_url($o->trx);

        } catch(Exception $e) {
            $salida->errNumber = $e->getCode();
            $salida->errMessage = $e->getMessage();
            log_message("error", $salida->errMessage);
        }

        // La salida del método dependerá de la integración
        if($new === TRUE) {

            return $salida;

        } else {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($salida));
        }



    }


    /**
     * Levanta formulario de método de pago
     *
     * @return	void
     */
    public function startTrx() {
        header("X-Frame-Options: ALLOW-ALL");
        header('Access-Control-Allow-Origin: *');
        try {

            // Recibe el TRX codificado y verifica que exista en el sistema
            // Es enviado por GET para facilitar el envío y recepción
            $trxGet = trim($this->input->get("trx"));
            $trx = decode_url($trxGet);
            $commerceId = trim($this->input->get("comm"));

            // Revisa nuevamente, verificando que exista el trx y el commerceId
            $oComm = $this->_isCommerceValid($commerceId);
            if(!$oComm->isValid) throw new Exception($oComm->message, 1001);

            $oTrx = $this->core_model->getTrx($trx);
            if(is_null($oTrx)) throw new Exception("La transacción proporcionada no existe", 1002);

            // Llegado este punto, los valores recibidos son válidos y con la tupla busca
            // los métodos de pagos desde el CRM
            $paymentList = $this->_getPaymentList($oTrx->idUserExternal,
                $oTrx->idApp,
                $oTrx->idPlan,
                $oTrx->idCountry);

            log_message("debug", "MÉTODOS DE PAGO DESDE JSON -> ".print_r($paymentList, TRUE));

            // Selecciona los métodos relacionados al trx en proceso
            $pL = array();
            $pLAmount = array();
            if(count($paymentList) == 0) throw new Exception("No se ha obtenido ningún método de pago", 1003);

            // Pasa el MedioPago como índice para poder comparar
            foreach($paymentList as $oPL)
                $pL[$oPL->MedioPago] = $oPL;

            $plSystem = $this->core_model->getAllPaymentType(); // MP en el sistema


            /* DEBE SER CORREGIDO E IMPLEMENTADO CON LÓGICA CORRECTA DE NUEVAS ENTIDADES
             * Y FLUJOS NUEVOS DE PAGO. CAMBIO HARD-CODE POR REQUERIMIENTO DE ZANA */
            $zana = "100006";
            if($zana == $oComm->o->code) {

                // 2,99USD, $660 USD/CLP 06/03/2017
                $zanaIdPayments = array(1, 3, 4);
                $zanaPaymentsType = array("TC" => 1990,
                    "MT" => 250,
                    "PP" => 2.99);

                $plStruct = new stdClass();
                $plStruct->Pais = "CL";
                $plStruct->IdCobro = "32112";
                $plStruct->Monto = "";
                $plStruct->MedioPago = "";
                $plStruct->IdPlan = "3";
                $plStruct->Moneda = "CLP";
                $plStruct->Principal = "0";
                $plStruct->IdGroup = "307";

                $plSystemTmp = array();
                $ii = 0;
                foreach($plSystem as $plsys) {
                    if(in_array((int)$plsys->idPaymentType, $zanaIdPayments)) {
                        $plSystemTmp[] = $plsys;
                    }
                }
                $plSystem = $plSystemTmp;

                $paymentListTmp = array();
                foreach($zanaPaymentsType as $key => $val) {
                    $o = clone $plStruct;
                    $o->Monto = $val;
                    $o->MedioPago = $key;

                    $paymentListTmp[] = $o;
                }
                $paymentList = $paymentListTmp;

                $pL = array();
                foreach($paymentList as $oPL)
                    $pL[$oPL->MedioPago] = $oPL;

            }


            $l = count($plSystem);
            if($l == 0) throw new Exception("No hay ningún método de pago disponible en el sistema", 1005);

            $idGroup = 0;
            for($i=$l-1;$i>=0;$i--) {
                $pls = $plSystem[$i];
                if(array_key_exists($pls->codPaymentTypeExternal, $pL)) {
                    $fromJson = $pL[$pls->codPaymentTypeExternal];
                    $m = $fromJson->Monto;
                    $pls->amount = encode_url($m); // le agrega el monto y codifica por seguridad
                    $pls->amountFormatted = $this->_formatAmount($m);

                    $pls->description = str_replace("{VALOR}", $pls->amountFormatted, $pls->description);

                    // Busca el idGroup del pago que es por operador
                    if($idGroup == 0 && $fromJson->MedioPago == "MT") {
                        $idGroup = $fromJson->IdGroup;
                    }

                    continue;
                }
                // Si no existe, lo remueve del arreglo de opciones de método de pago
                $l--;
                unset($plSystem[$i]);
            }

            // Reindexa índices
            $plSystem = array_values($plSystem);

            // Verifica que existan métodos de pago relacionados
            $l = count($plSystem); // nuevo largo
            //echo $l;
            if($l == 0) throw new Exception("No hay ningún método de pago relacionado a los obtenidos", 1006);

            // Si hay métodos de pagos disponible, se trae los campos del primero de la lista
            $initialFields = NULL;
            $initialFieldsHtml = array();
            $ini = $plSystem[0];



            $initialFields =  $this->core_model->getFieldsByPayment($ini->idPaymentType);
            // Convierte los campos a formato HTML
            if(!is_null($initialFields)) {
                foreach($initialFields as $iniF) {
                    // Trae las clases seteadas a cada field
                    $cs = $this->core_model->getClassesByIdField($iniF->idField);
                    $classes = array();
                    if(!is_null($cs)) {
                        foreach($cs as $oo) {
                            $classes[] = $oo->name;
                        }
                    }
                    $iniF->classes = $classes;
                    $iniF->fieldHTML = $this->_translate2Html($iniF);
                    $initialFieldsHtml[] = $iniF;
                }
            }

            // Paso de variables a vista
            $defaultImg = base_url()."assets/img/logo_3g.png";
            $o = new stdClass();
            $o->trx = $trxGet;
            $o->logo = is_null($oTrx->urlImg) || empty($oTrx->urlImg) ? $defaultImg : $oTrx->urlImg;
            $o->codAnalytics = $oTrx->codAnalytics;

            $urlOperator = $this->config->item("OperatorServicePath");
            $urlOperator = str_replace("{ID_GROUP}", $idGroup, $urlOperator);
            $urlOperator = str_replace("{TRX}", $o->trx, $urlOperator);
            $urlOperator = str_replace("{COUNTRY}", strtolower($oTrx->idCountry), $urlOperator);

            $data["data"] = $o;
            $data["action"] = base_url()."core/startTrxAction";
            $data["cancelUrl"] = $oTrx->urlError;
            $data["actionPt"] = base_url()."core/fieldsByPaymentAction";
            $data["actionOperator"] = $urlOperator;
            $data["actionOperatorCheck"] = base_url()."core/checkOperatorTrx";
            $data["okStatusOpe"] = parent::OK_OPE;
            $data["errStatusOpe"] = parent::ERR_OPE;
            $data["paymentTypes"] = $plSystem;
            $data["initialFields"] = $initialFieldsHtml;

            $this->load->view("start_transaction", $data);

        } catch(Exception $e) {
            $this->_error($e->getMessage());
        }


    }


    /**
     * RECIBE LA INFORMACIÓN POR POST
     * Realiza la llamada a los métodos correspondientes dependiendo del método de pago seleccionado
     * Con esto agrego una capa más, aportando mayor seguridad a la transacción, dejando como privados los métodos concretos
     */
    public function startTrxAction() {

        // Obtengo todo el POST con XSS filtering
        $post = $this->input->post(NULL, TRUE);

        // Tomo el valor del método de pago para llamar método correspondiente
        $paymentType = (int)trim($post["PT"]);
        switch($paymentType) {
            case 1:
                $this->_startTrxWebpay($post);
                break;
            case 4:
                // Envía data inicial a módulo PayPal para procesar pago
                $service = base_url()."paypalnvp/doPayPalNvp";
                $curl = curl_init($service);

                curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $post);

                $exec = json_decode(curl_exec($curl));
                if($exec->errNumber == 0) {
                    // -------------------------------------------
                    // STEP 2: Redirección de cliente a PayPal para autorización
                    // -------------------------------------------
                    // Almacena en session el TRX de manera temporal para poder derivar frente a error
                    $this->session->set_userdata("trx", $post["trx"]);
                    redirect(str_replace("{TOKEN}", $exec->token, $this->config->item("PayPalAuthorization")));
                }

                break;
            case 5:

                $service = base_url()."webpayplus/initTransaction";
                $curl = curl_init($service);

                curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $post);

                print_r(curl_exec($curl)); exit;

                $exec = json_decode();

                if($exec->errNumber == 0) {

                }

                break;
            default:
                break;
        }

    }


    /**
     * Lógica para pagar por WEBPAY
     */
    private function _startTrxWebpay($post) {

        $serviceUrl = base_url().'pe3g/initTrx';
        $TRXing = FALSE; // flag para cuando se inicia la transacción
        $fieldsRequired = array(
            "txtCardHolderId",
            "txtCardHolderName",
            "txtCardHolderLastName1",
            "txtCardHolderLastName2",
            "txtCardHolderMail",
            "txtCellPhoneNumber",
            "trx"
        );

        try {

            $oTrxWp = new stdClass();
            $TRXing = TRUE;

            // Verifica que venga toda la información en el POST
            if(count($post) == 0) throw new Exception("No se ha recibido ninguna información por parte del comercio", 1000);

            foreach($fieldsRequired as $f) {
                if(!$this->_hasKey($f, $post)) throw new Exception("No se ha definido el parámetro ".$f, 1002); // que el param venga
                if(trim($post[$f]) == "") throw new Exception("Todos los campos son obligatorios", 1003); // que el valor del param no venga vacío
            }

            // Interpreta el monto
            $ptPost = $post["PT"];
            $amount = decode_url($post["am_".$ptPost]);
            $trx = decode_url(trim($post[$fieldsRequired[6]]));

            // Actualiza valores del trx y genera registro de webpay
            // Vuelve a verificar la trx
            $oTrx = $this->core_model->getTrx($trx);
            if(is_null($oTrx)) throw new Exception("La transacción proporcionada no existe", 1004);

            // Verifica que el RUT, Email y Celular ya no existan
            // Reserva el RUT con formato para el servicio
            $rut = trim($post[$fieldsRequired[0]]); // NN.NNN.NNN-N
            $rutCleaned = str_replace(".", "", $rut);
            $rutCleaned = str_replace("-", "", $rutCleaned);

            //$ruts = $this->webpay_model->getTrxByField("cardHolderId", $rutCleaned);
            //if(!is_null($ruts)) throw new Exception("El <b>rut</b> ya existe en el sistema", 1010);

            //$emails = $this->webpay_model->getTrxByField("cardHolderMail", trim($post[$fieldsRequired[4]]));
            //if(!is_null($emails)) throw new Exception("El <b>e-mail</b> ya existe en el sistema", 1011);

            //$cels = $this->webpay_model->getTrxByField("cellPhoneNumber", trim($post[$fieldsRequired[5]]));
            //if(!is_null($cels)) throw new Exception("El <b>celular</b> ingresado ya existe en el sistema", 1012);

            $this->core_model->inicioTrx();

            // Pasa a Stage de inicio la trx para webpay
            $upd = new stdClass();
            $upd->idStage = parent::NUEVA_TRX_WP;
            $upd->amount = $amount;
            $upd->idPaymentType = $ptPost;
            $res = $this->core_model->updateTrx($oTrx->idTrx, $upd);
            if(!$res) throw new Exception("No se pudo actualizar la transacción", 1005);

            // Crea registro en la tabla de patpass
            $oTrxWp->idTrx = $oTrx->idTrx;
            $oTrxWp->sessionId = $trx.date("YmdHis");
            $oTrxWp->amount = $amount;
            $oTrxWp->buyOrder = "WP".str_replace(".","",microtime(TRUE));
            //$oTrxWp->buyOrder = "WP14629756468951";
            $oTrxWp->cardHolderId = $rutCleaned;
            $oTrxWp->cardHolderName = trim($post[$fieldsRequired[1]]);
            $oTrxWp->cardHolderLastName1 = trim($post[$fieldsRequired[2]]);
            $oTrxWp->cardHolderLastName2 = trim($post[$fieldsRequired[3]]);
            $oTrxWp->cardHolderMail = trim($post[$fieldsRequired[4]]);
            $oTrxWp->cellPhoneNumber = trim($post[$fieldsRequired[5]]);
            $oTrxWp->expirationDate = date("Y-m-d", strtotime(parent::EXP_DATE_WP));
            $oTrxWp->creationDate = date("Y-m-d H:i:s");

            // SOLO PARA DESARROLLO
            // En producción debe tomar el RUT y solo dejarle el guión
            $sId = "";
            if(ENVIRONMENT == "development") {
                $sId = "335456675433";
            } else {
                $sId = $oTrxWp->cardHolderId;
                $guion = "-";
                // Agrega prefijo dependiendo del producto
                // Busca prefijo en bd
                $pt = $this->core_model->getCommById($oTrx->idCommerce);
                if(is_null($pt)) throw new Exception("No se pudo determinar el comercio vinculado", 1005);

                $sId = $pt->prefix.$sId;
                /*
                $pos = strpos($sId, $guion); // verifica que venga guión
                if($pos == FALSE) {
                    // Si no encuentra guión, se lo agrega
                    $sId = substr_replace($sId, $guion, strlen($sId)-1, 0);
                }*/
            }

            $oTrxWp->serviceId = $sId;


            $trxWpBd = $this->webpay_model->initTrx($oTrxWp);
            if(is_null($trxWpBd)) throw new Exception("No se pudo almacenar la información para WebPay", 1006);

            // Llegado este punto, todo ok y hace commit
            $this->core_model->commitTrx();
            $TRXing = FALSE;
            // Devuelve el rut con formato para que sea procesado correctamente por el servicio
            $oTrxWp->cardHolderId = $rut;

            // Invoca a la librería de WebPay para intentar hacer el PatPass
            log_message("debug", "oTrxWp(_startTrxWebpay) -> ".print_r($oTrxWp, TRUE));
            $webpay = $this->webpaylib->initTransaction($oTrxWp);
            if(is_null($webpay)) {
                $this->core_model->updateStageTrx($oTrx->idTrx, parent::FALLO_INIT_TRX_WP);
                log_message("error", "No se pudo iniciar la transacción con Transbank");

                $data["buyOrder"] = $oTrxWp->buyOrder;
                $data["errorUrl"] = $oTrx->urlError;

                $this->load->view("webpay/reject", $data);

                return;
            }

            // Recibe el token y la url a donde hacer POST con este
            //log_message("debug", "(CORE) initTransaction (response)".print_r($webpay, TRUE));
            $token = $webpay->token;
            $urlX = $webpay->url;

            $this->core_model->updateStageTrx($oTrx->idTrx, parent::POST_TOKEN_WP);

            $upd = new stdClass();
            $upd->token = $token;
            $res = $this->webpay_model->updateTrx($trxWpBd, $upd);
            if(!$res) throw new Exception("No se pudo actualizar la información del token", 1005);

            $this->_postToken($token, $urlX);

        } catch(Exception $e) {
            if($TRXing) $this->core_model->rollbackTrx();
            $this->_error($e->getMessage());
        }

    }

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


    /**
     * Método VITAL, es donde WebPay notifica a nosotros el comercio que la trx está autorizada
     */
    public function notify() {

        try {

            // Recibe el token a través de token_ws y verifica que efectivamente exista
            $token = $this->input->post("token_ws");
            if(empty($token)) throw new Exception("No se ha podido determinar la transacción", 1000);

            $oTrxWP = $this->webpay_model->getTrxByToken($token);
            if(is_null($oTrxWP)) throw new Exception("La transacción en proceso no existe", 1001);

            $oTrx = $this->core_model->getTrxById($oTrxWP->idTrx);
            if(is_null($oTrx)) throw new Exception("No existe la transacción en el sistema", 1000);

            // Cambia de estado el trx
            $this->core_model->updateStageTrx($oTrxWP->idTrx, parent::RETORNA_TOKEN_WP);

            // Luego de validar a token_ws, se invoca a getTransactionResult() de webpay para verificar
            // el resultado de la transacción
            $o = new stdClass();
            $o->token_ws = $token;
            $getTransactionResultResponse = $this->webpaylib->getTransactionResultWp($o);

            // Falló validación de respuesta desde Transbank
            if(is_null($getTransactionResultResponse)) {
                $this->core_model->updateStageTrx($oTrxWP->idTrx, parent::FALLO_GETTRXRES_WP);
                $this->reject($token);
                return;
            }

            // Valida que la data resultado es igual a la almacenada (consistencia)
            $transactionResultOutput = $getTransactionResultResponse->return;

            $res = new stdClass();

            $typeDef = "XX";

            // Pasa a stage de validación consistencia
            $this->core_model->updateStageTrx($oTrxWP->idTrx, parent::VALIDA_CON_TRX_WP);

            $paymentTypeCode = $transactionResultOutput->detailOutput->paymentTypeCode;
            $responseCode = $transactionResultOutput->detailOutput->responseCode;
            $vci = $transactionResultOutput->VCI;
            // Busca los ID correspondientes
            $oPtc = $this->webpay_model->getTypeXXByCode("ptc", $paymentTypeCode);
            $oRc = $this->webpay_model->getTypeXXByCode("rc", $responseCode);
            $oVci = $this->webpay_model->getTypeXXByCode("vci", $vci);
            if(is_null($oPtc)) $oPtc = $this->webpay_model->getTypeXXByCode("ptc", $typeDef);
            if(is_null($oRc)) $oRc = $this->webpay_model->getTypeXXByCode("rc", $typeDef); // enviará info al notify
            if(is_null($oVci)) $oVci = $this->webpay_model->getTypeXXByCode("vci", $typeDef);

            // Valores a actualizar
            $res->idWPPaymentTypeCode = $oPtc->idWPPaymentTypeCode;
            $res->idWPResponseCode = $oRc->idWPResponseCode;
            $res->idWPVci = $oVci->idWPVci;
            $res->accountingDate = $transactionResultOutput->accountingDate;
            $res->cardNumber = $transactionResultOutput->cardDetail->cardNumber;
            $res->cardExpirationDate = $transactionResultOutput->cardDetail->cardExpirationDate;
            $res->authorizationCode = $transactionResultOutput->detailOutput->authorizationCode;
            $res->sharesNumber = $transactionResultOutput->detailOutput->sharesNumber;
            $res->transactionDate = $transactionResultOutput->transactionDate;

            // Data que se debe validar contra lo registrado
            $amount = $transactionResultOutput->detailOutput->amount;
            $buyOrder = $transactionResultOutput->buyOrder;
            //$commerceCode = $transactionResultOutput->detailOutput->commerceCode;
            $sessionId = $transactionResultOutput->sessionId;

            if($amount != $oTrxWP->amount) throw new Exception("Los montos no coinciden", 1003);
            if($buyOrder != $oTrxWP->buyOrder) throw new Exception("La orden de compra no coincide con la transacción", 1004);
            //if($commerceCode != $oTrxWP->commerceCode) throw new Exception("Los montos no coinciden", 1003);
            if($sessionId != $oTrxWP->sessionId) throw new Exception("La sesión no coincide con la transacción", 1005);

            // Luego de notificado al comercio, se vuelve a informar a Transbank de la recepción de la información
            // Con error o algo, devuelve vacío
            // Se debe validar la duplicidad de la orden de compra. Si la transacción se encuentra pagada, no debe invocar al método
            // acknowledgeTransaction para generar la reversa correspondiente, en caso contrario, continuar flujo normal
            // $oTrxWP->buyOrder
            $acknowledgeTransaction = NULL;
            $oBuyOrder = $this->webpay_model->getTrxByField("buyOrder", $oTrxWP->buyOrder);
            if(!is_null($oBuyOrder)) {
                log_message("error", "La orden de compra ".$oTrxWP->buyOrder." ya existe en el sistema");
            } else {
                $acknowledgeTransaction = $this->webpaylib->acknowledgeTransactionWp($o);
            }

            // Falló validación de respuesta desde Transbank
            if(is_null($acknowledgeTransaction)) {
                $this->core_model->updateStageTrx($oTrxWP->idTrx, parent::FALLO_GETTRXRES_WP);
                $this->reject($token);
                return;
            }

            // Almacena/actualiza los valores de la respuesta
            // *****************************************************************
            $oo = $this->webpay_model->updateTrx($oTrxWP->idWPTrxPatPass, $res);
            // *****************************************************************

            if($acknowledgeTransaction) {

                // 08-11-2016, Validaciones extras para integridad de transacción
                // Se agrega validación para verificar la integridad del código de autorización de Transbank
                $stop = TRUE;
                if(!is_null($res->authorizationCode)) {
                    if($res->authorizationCode != "00000") {
                        // Además, se verifica que el idWPResponseCode sea siempre 1
                        if((int)$res->idWPResponseCode == 1)
                            $stop = FALSE;
                    }
                }
                if($stop) {
                    $this->core_model->updateStageTrx($oTrxWP->idTrx, parent::FALLO_GETTRXRES_WP);
                    $this->reject($token);
                    return;
                }


                // **************************************************
                // NOTIFICA AL COMERCIO A TRAVÉS DE URL PROPORCIONADA
                // **************************************************
                $oNotify = new stdClass();
                $oNotify->result = ((int)$oRc->code != 0) ? 0 : 1;
                $oNotify->message = $oRc->description;
                $oNotify->idUserExternal = $oTrx->idUserExternal;
                $oNotify->idApp = $oTrx->idApp;
                $oNotify->idPlan = $oTrx->idPlan;
                $oNotify->idCountry = $oTrx->idCountry;

                $oNotifyRes = $this->_notify($oTrx->urlNotify, $oNotify);

                if($oNotifyRes) {
                    $this->core_model->updateStageTrx($oTrxWP->idTrx, parent::OK_ALL);
                } else {
                    $this->core_model->updateStageTrx($oTrxWP->idTrx, parent::OK_NO_RESP_NOTIFY);
                }

                // Envía nuevamente token por POST a Webpay para mostrar el voucher
                $this->_postToken($token, $transactionResultOutput->urlRedirection);
            } else {
                // No se obtuvo respuesta
                throw new Exception("No se pudo informar la respuesta de recepción a Transbank", 1006);
            }

            //$urlRedirection = $transactionResultOutput->urlRedirection;

        } catch(Exception $e) {
            log_message("error", $e->getMessage());
        }

        $this->load->view("webpay/notify");

    }

    /**
     * Notify para OPERADOR
     */
    public function notifyOperator() {

        $oNotify = new stdClass();
        $idTrx = 0;
        $prefix = "OPE";
        $amount = 250;

        try {
            // Recibe el trx y el ani
            $trx = $this->input->get("trx");
            $ani = $this->input->get("ani");
            if(empty($trx)) throw new Exception("No se ha podido determinar la transacción", 1000);
            if(empty($ani)) throw new Exception("No se ha podido determinar el ani del usuario", 1001);

            // Busca si existe el trx
            $trx = decode_url($trx);
            $oTrx = $this->core_model->getTrx($trx);
            if(is_null($oTrx)) throw new Exception("No existe la transacción en el sistema", 1003);

            // Actualiza valores TRX
            // Información método pago operador
            $oOpe = $this->payment_type_model->getByPrefix($prefix);
            if(is_null($oOpe)) throw new Exception("No se pudo identificar el método de pago operador", 1005);

            $upd = new stdClass();
            $upd->amount = $amount;
            $upd->idPaymentType = $oOpe->idPaymentType;
            $res = $this->core_model->updateTrx($oTrx->idTrx, $upd);

            // Guarda el ani devuelto en la tabla correspondiente
            $idTrx = $oTrx->idTrx;
            $opeTrx = new stdClass();
            $opeTrx->idTrx = $idTrx;
            $opeTrx->ani = $ani;
            $opeTrx->creationDate = date("Y-m-d H:i:s");
            $idOpeTrx = $this->operator_model->initTrx($opeTrx);
            if(is_null($idOpeTrx)) throw new Exception("No se pudo registrar el ani del usuario", 1004);

            // **************************************************
            // NOTIFICA AL COMERCIO A TRAVÉS DE URL PROPORCIONADA
            // **************************************************
            $oNotify->result = 1;
            $oNotify->message = "Proceso satisfactorio";
            $oNotify->idUserExternal = $oTrx->idUserExternal;
            $oNotify->idApp = $oTrx->idApp;
            $oNotify->idPlan = $oTrx->idPlan;
            $oNotify->idCountry = $oTrx->idCountry;


            $ini = microtime(TRUE);
            $oNotifyRes = $this->_notify($oTrx->urlNotify, $oNotify);
            $fin = microtime(TRUE);
            $executionTime = ($fin-$ini);
            log_message("debug", "PROCESO notifyOperator -> ".$executionTime);
            log_message("debug", "RESPONSE operator -> ".print_r($oNotifyRes, TRUE));

            if($oNotifyRes) {
                $this->core_model->updateStageTrx($idTrx, parent::OK_OPE);
            } else {
                $this->core_model->updateStageTrx($idTrx, parent::ERR_OPE);
            }

        } catch(Exception $e) {
            if($idTrx != 0) $this->core_model->updateStageTrx($idTrx, parent::ERR_OPE);
            log_message("error", $e->getMessage());
        }


    }

    public function checkOperatorTrx() {

        $trx = decode_url($this->input->post("trx"));
        $salida = new stdClass();
        $salida->status = 0;
        $salida->okUrl = "";
        $salida->errUrl = "";

        if($trx != "") {
            $oTrx = $this->core_model->getTrx($trx);
            if(!is_null($oTrx)) {
                // Verifica estado
                $salida->status = (int)$oTrx->idStage;
                $salida->okUrl = $oTrx->urlOk;
                $salida->errUrl = $oTrx->urlError;
            }
        }

        echo json_encode($salida);
    }



    /**
     * --- NOTIFICACIÓN AL COMERCIO POR URL ENVIADA POR ESTE ---
     */
    private function _notify($service, $params) {

        // Configura cabeceras
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json'
        );

        $curl = curl_init($service);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

        $exec = curl_exec($curl);

        curl_close($curl);

        log_message("debug", $exec);

        return $exec;

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

    /**
     * Recibe tupla de búsqueda y va al CRM a buscar los métodos de pago disponibles con sus montos
     */
    private function _getPaymentList($idUserExternal, $idApp, $idPlan, $idCountry) {

        $curl = curl_init($this->config->item("CRMServicePath"));
        $out = array(); // array de salida con los valores que hay match

        $post = array(
            "IDUserExternal"	=> $idUserExternal,
            "IDApp"				=> $idApp,
            "IDPlan"			=> $idPlan,
            "IDCountry"			=> $idCountry
        );

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);

        $exec = curl_exec($curl);
        $asArrList = json_decode($exec);
        $l = count($asArrList);
        if($l>0) {
            foreach($asArrList as $o) {
                if($o->Pais == $idCountry && $o->IdPlan == $idPlan) {
                    $out[] = $o;
                }
            }
        }

        return $out;

    }




    /**
     * Obtiene los campos relacionados al método de pago seleccionado
     *
     * @param	$idPaymentType	El ID del PaymentType (tipo de pago)
     *
     * @return	ArrayList Listado de campos relacionados al método de pago
     */

    public function fieldsByPaymentAction($idPaymentType = NULL) {

        $salida = new stdClass();
        $salida->err_number = 0;
        $salida->message = "";

        try {

            // Recibe id
            $idPT = is_null($idPaymentType) ? $this->input->post("idPaymentType") : $idPaymentType;

            // Busca data
            $fields = $this->core_model->getFieldsByPayment($idPT);
            $fieldsHtml = array();
            if(!is_null($fields)) {
                foreach($fields as $f) {

                    $cs = $this->core_model->getClassesByIdField($f->idField);
                    $classes = array();
                    if(!is_null($cs)) {
                        foreach($cs as $oo) {
                            $classes[] = $oo->name;
                        }
                    }

                    $f->classes = $classes;
                    $f->fieldHTML = $this->_translate2Html($f);
                    $fieldsHtml[] = $f;
                }
            }

            $salida->o = $fieldsHtml;

        } catch(Exception $e) {
            $salida->err_number = $e->getCode();
            $salida->message = $e->getMessage();
        }

        echo json_encode($salida);

    }

    /**
     * Validaciones básicas para tarjeta de crédito
     *
     * @param	$idPaymentType	El ID del PaymentType (tipo de pago)
     *
     * @return	boolean
     */

    public function isValidCreditCard($ccNum, $ccMonth, $ccYear) {

        $salida = new stdClass();
        $salida->err_number = 0;
        $salida->message = "";

        try {

            if(!card_number_valid($ccNum)) throw new Exception("El número de tarjeta no es válido", 1000);
            if(!card_expiry_valid($ccMonth, $ccYear)) throw new Exception("La tarjeta de crédito ha expirado", 1001);

        } catch(Exception $e) {
            $salida->err_number = $e->getCode();
            $salida->message = $e->getMessage();
        }

        echo json_encode($salida);

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
     * (SOLO PARA PRUEBAS) Despliega resultado
     */
    public function status($err = "ok") {
        $msj = "";
        if($err == "ok") $msj = "Soy una página de resultado OK del comercio";
        else $msj = "Página ERROR del comercio";

        $data["message"] = $msj;

        $this->load->view("status_result", $data);
    }
    public function ok() {
        $this->status("ok");
    }
    public function error() {
        $this->status("error");
    }




    /**
     * Recibe el Field y lo "convierte" a tag HTML
     *
     * @param	$o		Objeto que será interpretado a HTML
     *
     * @return	string	Cadena en formato HTML
     */
    private function _translate2Html($o) {

        $res = '';

        switch(strtoupper($o->nameFt)) {
            case "TEXTFIELD":

                $pattern = '.{min,max}';
                $title = '';
                $type = 'text';
                $addClasses = '';
                if(!is_null($o->classes)) {
                    if(in_array("cEmail", $o->classes)) $type = 'email';
                    if(in_array("cNumber", $o->classes)) $type = 'number';
                    $addClasses = empty($o->classes) ? '' : 'class="'.implode(" ", $o->classes).'"';
                }

                $min = !is_null($o->minLength) ? $o->minLength : '';
                $max = !is_null($o->maxLength) ? $o->maxLength : '';
                $pattern = str_replace('min', $min, $pattern);
                $pattern = str_replace('max', $max, $pattern);

                $required = (int)$o->required == 1 ? ' required ' : ' ';

                $patternStr = ($min == '' && $max == '') ? ' ' : ' pattern="'.$pattern.'" ';

                $addClass =
                $res = '<input id="'.$o->htmlId.'" name="'.$o->htmlId.'"'.$required.'type="'.$type.'"'.'placeholder="'.$o->htmlLabel.'"'.$addClasses.' />';

                break;
            case "LISTBOX":
                break;
            case "COMBOBOX":
                break;
            default:
                break;
        }

        return $res;
    }

    private function _hasKey($key, $arr) {
        return array_key_exists($key, $arr);
    }

    private function _formatAmount($val) {
        return "$".number_format((float)$val,0,",",".");
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