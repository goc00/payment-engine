<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| ----------------------------
| Khipu v1.0
| ----------------------------
| Autor: Gastón Orellana
| Descripción: Opera todos los flujos para la implementación con Khipu
| Fecha creación: 23/01/2017
|
| ---------------
| Modificaciones:
| ---------------
| v1.0: 23-01-2017, GOC
| Creación de controlador para flujos de Khipu
*/

class Khipu extends MY_Controller {

	private $controller = "";

	public function __construct() {
		parent::__construct();
		$this->load->helper('string');
		$this->load->helper('creditcard');
		$this->load->helper('crypto');
		$this->load->helper('url');
		$this->load->library('encryption');
		$this->load->library('funciones');
		$this->load->model('webpay_model', '', TRUE);
		$this->load->model('operator_model', '', TRUE);
		$this->load->model('payment_type_model', '', TRUE);
		$this->load->model('core_model', '', TRUE);
		
		$this->controller = base_url().'khipu/';
	}
	
	
	/**
	 * Prueba de flujo nuevo para Webpay normal
	 */
	public function initTransactionTest() {
		
		$service = $this->controller."initTransactionNewFlow";
		$curl = curl_init($service);
		
		$post = array(
			"IDUserExternal"	=> 1,
			"IDApp"				=> 1,
			"IDPlan"			=> 3,
			"IDCountry"			=> "CL",
			"UrlOk"           	=> $this->controller."result/ok",
			"UrlError"			=> $this->controller."result/error",
			"UrlNotify"			=> $this->controller."notify",
			"CommerceID"		=> 1234, // comercio de prueba
			"CodigoAnalytics"	=> "XX-99999999-9",
			"PaymentType"		=> 5,
			"Amount"			=> 100,
			"OldFlow"			=> 0
		);
		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, FALSE); // NO espera respuesta
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
		
		$exec = curl_exec($curl);
	}
	
	
	/**
	 * Genera el hash para firmar las peticiones a la API de Khipu
	 */
	public function generateHash() {
		
		$receiver_id = $this->config->item("KhipuReceiverId");
		$secret = $this->config->item("KhipuSecret");
		$method = 'POST';
		$url = $this->config->item("KhipuEndpoint");

		$params = array('subject' => 'ejemplo de compra',
						'amount' => '1000',
						'currency' => 'CLP'
					);

		$keys = array_keys($params);
		sort($keys);

		$toSign = "$method&" . rawurlencode($url);
		foreach ($keys as $key) {
				$toSign .= "&" . rawurlencode($key) . "=" . rawurlencode($params[$key]);
		}
		$hash = hash_hmac('sha256', $toSign , $secret);
		$value = "$receiver_id:$hash";
		print "$value\n";
		
	}

	
	/**
	 * Flujo nuevo para Webpay pago normal
	 * Con este se puede invocar "one shot" la petición, no pasando
	 * por el formulario de pago del motor.
	 */
	public function initTransactionNewFlow() {
		
		$salida = new stdClass();
		$salida->errCode = 0;
		$salida->errMessage = "";
		// URLs por defecto, por si no alcanza a setear con datos de usuario
		$urlError = $this->controller."result/error";
		
		try {
			
			$msgRequiredError = "No se ha detectado el parámetro [PARAM]";
			$format = "Y-m-d H:i:s";
			
			$paramsRequired = array("IDUserExternal",
			                        "IDApp",
			                        "IDPlan",
			                        "IDCountry",
			                        "UrlOk",
			                        "UrlError",
			                        "UrlNotify",
			                        "CommerceID",
			                        "CodigoAnalytics",
			                        "PaymentType",
			                        "Amount",
			                        "OldFlow");
			
			// Recibe los parámetros por POST
			$post = $this->input->post(NULL, TRUE);

			// Valida que venga toda la información requerida
			// Setea de inmediato las URLs de respuesta
			if(empty($post)) throw new Exception("No se ha recibido ningún dato desde el origen", 1000); // que vengan datos desde el origen
			$post =	(object)$post;
			$l = count($paramsRequired);
			for($i=0;$i<$l;$i++) {
				$key = $paramsRequired[$i];
				if(!isset($post->$key)) throw new Exception(str_replace("PARAM", $key, $msgRequiredError), 1001);
			}
			$urlError = $post->UrlError;
			
			
			// Valida que el comercio exista y posea contrato activo
			$oComm = $this->core_model->getCommerceByCode($post->CommerceID);
			if(is_null($oComm)) throw new Exception("El comercio proporcionado no existe en el sistema", 1002);
			// Si está activo
			if((int)$oComm->active == 0) throw new Exception("El comercio no se encuentra activo", 1002);
			// Expirado o no
			$fechaIni = date($format, strtotime($oComm->contractStartDate));
			$fechaFin = date($format, strtotime($oComm->contractEndDate));
			$now = date($format, time());
			if(($now < $fechaIni) || ($now > $fechaFin)) throw new Exception("El comercio no se encuentra disponible", 1002);
			
			
			// *** CREACIÓN DE TRANSACCIÓN ***
			$o = new stdClass();
			
			$o->idStage = parent::NUEVA_TRX;
			
			$o->trx = random_string("alnum", parent::MAX_N_TRX_WP);

			$o->idUserExternal = $post->IDUserExternal;
			$o->idApp = $post->IDApp;
			$o->idPlan = $post->IDPlan;
			$o->idCountry = $post->IDCountry;
			$o->urlOk = $post->UrlOk;
			$o->urlError = $post->UrlError;
			$o->urlNotify = $post->UrlNotify;
			$o->idCommerce = $oComm->idCommerce;
			$o->codAnalytics = $post->CodigoAnalytics;
			$o->idPaymentType = $post->PaymentType;
			$o->amount = $post->Amount;
			$o->oldFlow = $post->OldFlow;
			$o->creationDate = date("Y-m-d H:i:s");
			
			log_message("debug", print_r($o, TRUE)); // log de parámetros iniciales
			
			// CREA NUEVA TRANSACCIÓN EN EL MOTOR
			$idTrx = $this->core_model->newTrx($o);
			if(is_null($idTrx)) throw new Exception("No se pudo iniciar la transacción en el sistema", 1003);
			
			// Responde OK con lo generado, inicializa proceso con Transbank
			$oTrxWp = new stdClass();
			$oTrxWp->idTrx = $idTrx;
			$oTrxWp->sessionId = $o->trx.date("YmdHis");
			$oTrxWp->amount = $o->amount;
			$oTrxWp->buyOrder = "WPP".str_replace(".","",microtime(TRUE));
			//$oTrxWp->buyOrder = "WPP1475763617485";
			$oTrxWp->creationDate = date("Y-m-d H:i:s");
			
			// Crea registro en la tabla de Webpay pago normal
			$trxWpBd = $this->webpay_model->initTrx($oTrxWp);
			if(is_null($trxWpBd)) throw new Exception("No se pudo almacenar la información para WebPay. ".print_r($oTrxWp, TRUE), 1004);
			
			// Llegado este punto, todo ok y hace commit
			//$this->core_model->commitTrx();
			//$TRXing = FALSE;

			log_message("debug", print_r($oTrxWp, TRUE));
			
			// ---------------------------------------------------------------------
			// Invoca a la librería de WebPay para intentar hacer el initTransaction
			// ---------------------------------------------------------------------
			$this->webpaylib->setIsWebpayNormal(TRUE);
			$webpay = $this->webpaylib->initTransaction($oTrxWp);
			if(is_null($webpay)) {
				
				$this->core_model->updateStageTrx($idTrx, parent::FALLO_INIT_TRX_WP);
				throw new Exception("No se pudo inicializar la comunicación con Transbank. ", 1005);

			}
				
			// Recibe el token y la url a donde hacer POST con este
			//log_message("debug", "(CORE) initTransaction (response)".print_r($webpay, TRUE));
			$token = $webpay->token;
			$urlX = $webpay->url;
			
			$this->core_model->updateStageTrx($idTrx, parent::POST_TOKEN_WP);
			
			$upd = new stdClass();
			$upd->token = $token;
			$res = $this->webpay_model->updateTrx($trxWpBd, $upd);
			if(!$res) throw new Exception("No se pudo actualizar la información del token", 1006);
			
			$this->_postToken($token, $urlX);

			
		} catch(Exception $e) {
			log_message("error", "Webpayplus, error (".$e->getCode().") -> ".$e->getMessage());
			redirect($urlError);
		}
		
	}
	
	
	
	/**
	 * Lógica para pagar por WEBPAY PLUS
	 */
	public function initTransaction() {
		
		// Obtengo todo el POST con XSS filtering
		$post = $this->input->post(NULL, TRUE);

		$TRXing = FALSE; // flag para cuando se inicia la transacción (control para potencial rollback en caso de error)

		try {
			
			$oTrxWp = new stdClass();
			$TRXing = TRUE;
			
			// Verifica que venga toda la información en el POST
			if(count($post) == 0) throw new Exception("No se ha recibido ninguna información por parte del comercio", 1000);
			
			// Interpreta el monto
			$ptPost = $post["PT"];
			$amount = decode_url($post["am_".$ptPost]);
			$trx = decode_url(trim($post["trx"]));

			// Actualiza valores del trx y genera registro de webpay
			// Vuelve a verificar la trx
			$oTrx = $this->core_model->getTrx($trx);
			if(is_null($oTrx)) throw new Exception("La transacción proporcionada no existe", 1004);

			// INICIO TRANSACCIÓN
			$this->core_model->inicioTrx();
			
			// Pasa a Stage de inicio la trx para webpay
			$upd = new stdClass();
			$upd->idStage = parent::NEW_TRX_WPP;
			$upd->amount = $amount;
			$upd->idPaymentType = $ptPost;
			$res = $this->core_model->updateTrx($oTrx->idTrx, $upd);
			if(!$res) throw new Exception("No se pudo actualizar la transacción", 1005);
			
			// Genera objetos para registrar en BD y enviar a initTransaction de WebPay
			$oTrxWp->idTrx = $oTrx->idTrx;
			$oTrxWp->sessionId = $trx.date("YmdHis");
			$oTrxWp->amount = $amount;
			$oTrxWp->buyOrder = "WPP".str_replace(".","",microtime(TRUE));
			//$oTrxWp->buyOrder = "WPP1475763617485";
			$oTrxWp->creationDate = date("Y-m-d H:i:s");
			
			// Crea registro en la tabla de Webpay pago normal
			$trxWpBd = $this->webpay_model->initTrx($oTrxWp);
			if(is_null($trxWpBd)) throw new Exception("No se pudo almacenar la información para WebPay", 1006);
			
			// Llegado este punto, todo ok y hace commit
			$this->core_model->commitTrx();
			$TRXing = FALSE;
		
			
			log_message("debug", print_r($oTrxWp, TRUE));
			// ---------------------------------------------------------------------
			// Invoca a la librería de WebPay para intentar hacer el initTransaction
			// ---------------------------------------------------------------------
			$this->webpaylib->setIsWebpayNormal(TRUE);
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
			$this->webpaylib->setIsWebpayNormal(TRUE);
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
				$this->webpaylib->setIsWebpayNormal(TRUE);
				$acknowledgeTransaction = $this->webpaylib->acknowledgeTransactionWp($o);
			}
			
			// Falló validación de respuesta desde Transbank
			if(is_null($acknowledgeTransaction)) {
				$this->core_model->updateStageTrx($oTrxWP->idTrx, parent::FALLO_GETTRXRES_WP);
				$this->reject($token);
				return;
			}
			
			// Almacena/actualiza los valores de la respuesta
			$oo = $this->webpay_model->updateTrx($oTrxWP->idWPTrxPatPass, $res);
			
			if($acknowledgeTransaction) {
				
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
				
				$oNotifyRes = $this->funciones->doPost($oTrx->urlNotify, $oNotify);
				
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
	 * Cuando la transacción no es válida (no se puede certificar contra Transbank)
	*/
	public function reject($token) {
		$oTrxWP = $this->webpay_model->getTrxByToken($token);
		$oTrx = $this->core_model->getTrxById($oTrxWP->idTrx);
		
		$data["buyOrder"] = $oTrxWP->buyOrder;
		$data["errorUrl"] = $oTrx->urlError;
		
		$this->load->view("webpay/reject", $data);
	}
	
	
	/**
	 * Página de resultado de Webpay
	 * Según documentación (v1.8) de Transbank, este página recibe el resultado del
	 * flujo de anulación (botón "Anular")
	 */
	public function voucher() {

		$token = $this->input->post("token_ws");
		$anulacion = $this->input->post("TBK_TOKEN");
		$anulado = FALSE;
	
		// Si no llega el token, se considera como error porque el proceso de anulación, ahora corre como
		// un flujo determinado y a través de una excepción
		if(!empty($anulacion)) {
			
			// Se verifica que la transacción haya sido efectivamente anulada.
			// Debe retornar una excepción
			
			$o = new stdClass();
			$o->token_ws = $anulacion;
			$this->webpaylib->setIsWebpayNormal(TRUE);
			$getTransactionResultResponse = $this->webpaylib->getTransactionResultWp($o);
			
			// Transacción anulada
			if(isset($getTransactionResultResponse->anulado)) {
				
				$buyOrder = $_POST["TBK_ORDEN_COMPRA"];
				$oTrxWP = $this->webpay_model->getTrxByBuyOrder($buyOrder);
				$oTrx = $this->core_model->getTrxById($oTrxWP->idTrx);
				$this->core_model->updateStageTrx($oTrxWP->idTrx, parent::ANULADO_TRX_WP);
				
				$data["buyOrder"] = $buyOrder;
				$data["error"] = 1;
				$data["msj"] = "La transacción ha sido anulada";
				$data["returnUrl"] = $oTrx->urlOk;
				$data["errorUrl"] = $oTrx->urlError;
				$this->load->view("webpay/voucher_normal", $data);
				
				return;
			}
	
		}
		
		
		if(empty($token)) {
			// Trx anulada, se marca y se busca con el token de session
			$token = $this->session->userdata("token_tmp");
			$this->session->unset_userdata('token_tmp'); // la destruye inmediatamente
			$anulado = TRUE;
		}
		
		$oTrxWP = $this->webpay_model->getTrxByToken($token);
		$oTrx = $this->core_model->getTrxById($oTrxWP->idTrx);
		$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
		$ptc = $this->webpay_model->getPTById($oTrxWP->idWPPaymentTypeCode);
		if($anulado) $this->core_model->updateStageTrx($oTrxWP->idTrx, parent::ANULADO_TRX_WP);
		
		// Busca la información en la TRX para enviarlo a la URL de éxito del comercio
		$data["buyOrder"] = $oTrxWP->buyOrder;
		
		// Evalúa la respuesta
		$msj = "";
		$error = 0;
		if((int)$oTrxWP->idWPResponseCode != parent::TRX_OK) {
			$suffix = $anulado ? " ha sido anulada" : " ha fracasado";
			$msj = "La transacción".$suffix;
			//$msj = "Transacción Rechazada";
			$error = 1;
		} else {
		
			$arr = explode("T", $oTrxWP->transactionDate);
			$hora = explode(".", $arr[1]);
			$str2 = $arr[0]." ".$hora[0];
			
			$data["amount"] = "$".number_format((float)$oTrxWP->amount, 0, ",", ".");
			$data["currency"] = (int)$oTrxWP->ufFlag == 1 ? "UF" : "CLP";
			$data["authorizationCode"] = $oTrxWP->authorizationCode;
			$data["transactionDate"] = $str2;
			//$data["paymentType"] = $ptc->description." (".$ptc->code.")";
			$data["paymentType"] = "Crédito";
			
			$sharesNumber = array(
				"VD" => "00",
				"VN" => "00",
				"VC" => $oTrxWP->sharesNumber,
				"SI" => 3,
				"S2" => 2,
				"NC" => $oTrxWP->sharesNumber
			);
			$sharesType = array(
				"VD" => "Venta Débito",
				"VN" => "Sin Cuotas",
				"VC" => "Cuotas Normales",
				"SI" => "Sin Interés",
				"S2" => "Sin Interés",
				"NC" => "Sin Interés"
			);
			
			$data["sharesType"] = $sharesType[$ptc->code];
			$data["sharesNumber"] = $sharesNumber[$ptc->code];
			$data["cardNumber"] = "XXXXXXXXXXXX".$oTrxWP->cardNumber;
			
			$msj = "¡Transacción Realizada Satisfactoriamente!";
		}
		$data["error"] = $error;
		$data["msj"] = $msj;		
		
		$data["returnUrl"] = $oTrx->urlOk;
		$data["errorUrl"] = $oTrx->urlError;
		$data["description"] = $oCommerce->description;
	
		$this->load->view("webpay/voucher_normal", $data);
	}
	
	public function result($status) {
		echo "El proceso ha culminado en $status";
	}
	
	
	/**
	 * Hace envío del token a url por POST, a través de variable token_ws
	*/
	private function _postToken($token, $url) {
		
		try {
			
			$data["token"] = $token;
			$data["url"] = $url;
			
			// Dejar token en session por si se anula
			$this->session->set_userdata("token_tmp", $token);
			
			$this->load->view("webpay/post_token", $data);
			
		} catch(Exception $e) {
			$this->_error($e->getMessage());
		}
	
	}
	private function _hasKey($key, $arr) {
		return array_key_exists($key, $arr);
	}
	
	private function _formatAmount($val) {
		return "$".number_format((float)$val,0,",",".");
	}
	
}
