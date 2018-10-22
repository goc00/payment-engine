<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Braintree extends MY_Controller {
	
	public function __construct() {
		parent::__construct();
		
		date_default_timezone_set('America/Santiago');
		
		$this->load->helper('string');
		$this->load->helper('creditcard');
		$this->load->helper('crypto');
		$this->load->helper('url');
		
		$this->load->library('encryption');
		$this->load->library('funciones');

		$this->load->model('payment_type_model', '', TRUE);
		$this->load->model('core_model', '', TRUE);
		$this->load->model('braintree_model', '', TRUE);
	}
	
	public function index() {
		echo "what are you looking for?";
	}
	
	// --------------------------------
	
	
	public function startTrxTest() {
		
		$service = base_url()."braintree/startTrx";
		$curl = curl_init($service);
		
		$post = array(
			"IDUserExternal"	=> 1,
			"IDApp"				=> 1,
			"IDPlan"			=> 3,
			"IDCountry"			=> "CL",
			"UrlOk"           	=> base_url()."braintree/ok",
			"UrlError"			=> base_url()."braintree/error",
			"UrlNotify"			=> base_url()."braintree/notify",
			"Amount"			=> 10,
			"CommerceID"		=> 1234,
			"Json"				=> 0
		);
		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, FALSE); // no devuelve resultado
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		
		$exec = curl_exec($curl);
		//$curlRes = json_decode($exec);
		log_message("debug", print_r($exec, TRUE));
		
		/*if($curlRes->errNumber == 0) {
			// Genera un link con lo recién generado
			$link = $curlRes->urlFrmPago;
			$link = str_replace("{TRX}", $curlRes->trx, $link);
			$link = str_replace("{COMM}", $post["CommerceID"], $link);
			echo '<a href="'.$link.'">Ir a formulario de pago</a>';
		} else {
			echo $curlRes->errMessage;
		}*/
		
	}
	
	
	public function ok() { echo "Proceso OK"; }
	public function error() { echo "Falló el proceso"; }
	public function notify() { echo "notificando"; }
	
	/**
	 * Inicia proceso en BrainTree
	 */
	public function startTrx() {

		$s = new stdClass();
		$s->errNumber = 0;
		$s->errMessage = "";
		$Json = "";
		
		try {
			
			$mess = "No se han definido todos los atributos necesarios";
			$format = "Y-m-d H:i:s";
			
			// Genero transacción en el motor de pagos
			// Recibo parámetros iniciales y setea por defecto
			
			// Valida el comercio en el sistema
			$commerceID = trim($this->input->post("CommerceID"));
			if(empty($commerceID)) throw new Exception("No se ha enviado ningún CommerceID", 1000);
			
			$oComm = $this->core_model->getCommerceByCode($commerceID);
			if(is_null($oComm)) throw new Exception("El comercio proporcionado no existe en el sistema", 1000);
			
			// Si está activo
			if((int)$oComm->active == 0) throw new Exception("El comercio no se encuentra activo", 1000);
			
			// Expirado o no
			$fechaIni = date($format, strtotime($oComm->contractStartDate));
			$fechaFin = date($format, strtotime($oComm->contractEndDate));
			$now = date($format, time());
			if(($now < $fechaIni) || ($now > $fechaFin)) throw new Exception("El comercio no se encuentra disponible", 1000);
			
			// Parámetros
			$IDUserExternal = trim($this->input->post("IDUserExternal"));
			$IDApp = trim($this->input->post("IDApp"));
			$IDPlan = trim($this->input->post("IDPlan"));
			$IDCountry = trim($this->input->post("IDCountry"));
			$UrlOk = trim($this->input->post("UrlOk"));
			$UrlError = trim($this->input->post("UrlError"));
			$UrlNotify = trim($this->input->post("UrlNotify"));
			$Amount = trim($this->input->post("Amount"));
			$Json = trim($this->input->post("Json"));

			if($IDUserExternal == "") throw new Exception($mess." {IDUserExternal}", 1000);
			if($IDApp == "") throw new Exception($mess." {IDApp}", 1000);
			if($IDPlan == "") throw new Exception($mess." {IDPlan}", 1000);
			if($IDCountry == "") throw new Exception($mess." {IDCountry}", 1000);
			if($UrlOk == "") throw new Exception($mess." {UrlOk}", 1000);
			if($UrlError == "") throw new Exception($mess." {UrlError}", 1000);
			if($UrlNotify == "") throw new Exception($mess." {UrlNotify}", 1000);
			if($Amount == "") throw new Exception($mess." {Amount}", 1000);
			if($Json == "") throw new Exception($mess." {Json}", 1000);
			
			// Creación de objeto TRX
			$oTrx = new stdClass();
			$oTrx->idStage = parent::NEW_TRX_BT;
			$oTrx->idCommerce = $oComm->idCommerce;
			$oTrx->idPaymentType = parent::ID_BRAINTREE;
			$oTrx->trx = random_string("alnum", parent::MAX_N_TRX_WP);
			$oTrx->amount = $Amount;
			$oTrx->idUserExternal = $IDUserExternal;
			$oTrx->idApp = $IDApp;
			$oTrx->idPlan = $IDPlan;
			$oTrx->idCountry = $IDCountry;
			$oTrx->urlOk = $UrlOk;
			$oTrx->urlError = $UrlError;
			$oTrx->urlNotify = $UrlNotify;
			$oTrx->oldFlow = 0;	// flujo nuevo
			$oTrx->creationDate = date("Y-m-d H:i:s");
			
			$idTrx = $this->core_model->newTrx($oTrx);
			if(is_null($idTrx)) throw new Exception("No se pudo iniciar la transacción en el sistema", 1001);
		
			// Crea el token para poder utilizarlo contra el JS SDK de BrainTree
			$s->token = $this->braintreelib->createClientToken();
			
			if(empty($s->token)) throw new Exception("No se pudo generar el token para la transacción con BrainTree", 1000);
			
			$urlPayload = base_url()."braintree/processingPayLoad";
			
			$s->trx = $oTrx->trx;
			$s->payload = $urlPayload;
	
		} catch(Exception $e) {
			$s->errMessage = $e->getMessage();
			log_message("error", "Error en doBrainTree(). Mensaje: ".$s->errMessage);
		}
		
		
		if((int)$Json == 1) {
		
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode($s));
				
		} else {
			
			//print_r((array)$s);
			
			$this->load->view("braintree/start", (array)$s);
			
		}
		
				
		
		
	}
	
	/**
	 * Procesa el payload (lo que viene luego del tokenize)
	 */
	public function processingPayLoad() {
		
		$oTrx = new stdClass();
		//$email = "";
		
		try {
			
			$msg = "No se han definido todos los atributos necesarios --> '{ATTR}'";
			
			// Recibe parámetros por POST
			$description = trim($this->input->post("description_txt"));
			$cardType = trim($this->input->post("cardType_txt"));
			$lastTwo = trim($this->input->post("lastTwo_txt"));
			$nonce = trim($this->input->post("nonce_txt"));
			$type = trim($this->input->post("type_txt"));
			$trx = trim($this->input->post("trx_txt"));
			//$email = trim($this->input->post("email_txt"));
			
			if(empty($description)) throw new Exception(str_replace("{ATTR}", "description", $msg), 1000);
			if(empty($cardType)) throw new Exception(str_replace("{ATTR}", "cardType", $msg), 1000);
			if(empty($lastTwo)) throw new Exception(str_replace("{ATTR}", "lastTwo", $msg), 1000);
			if(empty($nonce)) throw new Exception(str_replace("{ATTR}", "nonce", $msg), 1000);
			if(empty($type)) throw new Exception(str_replace("{ATTR}", "type", $msg), 1000);
			if(empty($trx)) throw new Exception(str_replace("{ATTR}", "trx", $msg), 1000);
			//if(empty($email)) throw new Exception(str_replace("{ATTR}", "email", $msg), 1000);
			
			// oTrx en función del trx
			$oTrx = $this->core_model->getTrx($trx);
			if(empty($oTrx)) throw new Exception("No se pudo identificar la transacción suministrada", 1001);
			
			// Cambio de estado de la transacción
			$this->core_model->updateStageTrx($oTrx->idTrx, parent::PROCESSING_PAYLOAD);
			
			// ---------- TRX BRAINTREE ----------
			$res = $this->braintreelib->sale($oTrx->amount, $nonce);
			// ---------- TRX BRAINTREE ----------
			
			// Procesa respuesta del servicio
			$errNumber = NULL;
			$errMsg = NULL;
			$errMore = NULL;
			if(empty($res)) throw new Exception("No hubo respuesta desde el servicio de BrainTree", 1002);

			// Resuelve según el estado
			$success = (boolean)$res->success;
			if(!$success) {
				
				// Se generó error, interpreta
				// Todo viene desde la documentación oficial
				// https://developers.braintreepayments.com/reference/response/transaction/php#result-object
				
				if(!empty($res->transaction)) {
					
					$oTransaction = $res->transaction;
					
					switch($oTransaction->status) {
						
						case "processor_declined": // Processor declined
							$errNumber = $oTransaction->processorResponseCode;
							$errMsg = $oTransaction->processorResponseText;
							$errMore = $oTransaction->additionalProcessorResponse;
							break;
						
						case "settlement_declined": // Processor settlement declined
							$errNumber = $oTransaction->processorSettlementResponseCode;
							$errMsg = $oTransaction->processorSettlementResponseText;
							break;
						
						case "gateway_rejected": // Gateway Rejection
							$errMsg = $oTransaction->gatewayRejectionReason;
							break;
						
					}
					
				} else {
					// Validation errors
					// No hay objeto Transaction
					//throw new Exception(print_r($res->errors->deepAll(), TRUE), 1003);
					//$errMsg = print_r($res->errors->deepAll(), TRUE);
					$errMsg = $res->message;
				}
				
			}
		
			
			// Objeto para almacenar respuesta de braintree
			$oTransaction = $res->transaction;
			
			$o = new stdClass();
			$o->idTrx = $oTrx->idTrx;
			$o->nonce = $nonce;
			$o->success = (int)$success;
			$o->errNumber = $errNumber;
			$o->errMsg = $errMsg;
			$o->errMore = $errMore;
			$o->riskDataId = !empty($oTransaction->riskData) ? $oTransaction->riskData->id : NULL;
			$o->riskDataDecision = !empty($oTransaction->riskData) ? $oTransaction->riskData->decision : NULL;
			$o->creationDate = date("Y-m-d H:i:s");

			
			// Me aseguro de tener todos los elementos 
			if($success) {
				if(!empty($res->transaction)) {

					$o->id = $oTransaction->id;
					$o->status = $oTransaction->status;
					$o->type = $oTransaction->type;
					$o->currencyIsoCode = $oTransaction->currencyIsoCode;
					$o->amount = $oTransaction->amount;
					$o->merchantAccountId = $oTransaction->merchantAccountId;
					$o->createdAt = $oTransaction->createdAt->format('Y-m-d H:i:s');
					$o->paymentInstrumentType = $oTransaction->paymentInstrumentType;
					$o->avsErrorResponseCode = $oTransaction->avsErrorResponseCode;
					$o->avsPostalCodeResponseCode = $oTransaction->avsPostalCodeResponseCode;
					$o->avsStreetAddressResponseCode = $oTransaction->avsStreetAddressResponseCode;
					$o->cvvResponseCode = $oTransaction->cvvResponseCode;
					$o->processorAuthorizationCode = $oTransaction->processorAuthorizationCode;
					$o->processorResponseCode = $oTransaction->processorResponseCode;
					$o->processorResponseText = $oTransaction->processorResponseText;
					
					if($o->paymentInstrumentType == "credit_card") {
						// Si es de este tipo, se puede obtener
						$creditCardDetails = $oTransaction->creditCardDetails;
						
						$o->bin = $creditCardDetails->bin;
						$o->last4 = $creditCardDetails->last4;
						$o->cardType = $creditCardDetails->cardType;
						$o->expirationMonth = $creditCardDetails->expirationMonth;
						$o->expirationYear = $creditCardDetails->expirationYear;
						$o->customerLocation = $creditCardDetails->customerLocation;
						$o->imageUrl = $creditCardDetails->imageUrl;
						$o->expirationDate = $creditCardDetails->expirationDate;
						$o->maskedNumber = $creditCardDetails->maskedNumber;
					
					}
					
					
				}
			}
			
			/*
			stdClass Object
			(
				[idTrx] => 605
				[success] => 1
				[errNumber] => 
				[errMsg] => 
				[errMore] => 
				[riskDataId] => 
				[riskDataDecision] => 
				[id] => 9w7vsg6r
				[status] => submitted_for_settlement
				[type] => sale
				[currencyIsoCode] => USD
				[amount] => 10.00
				[merchantAccountId] => 3gmotion
				[createdAt] => 2016-12-01 23:55:24
				[paymentInstrumentType] => credit_card
				[avsErrorResponseCode] => 
				[avsPostalCodeResponseCode] => M
				[avsStreetAddressResponseCode] => I
				[cvvResponseCode] => M
				[processorAuthorizationCode] => 3T1DM8
				[processorResponseCode] => 1000
				[processorResponseText] => Approved
				[bin] => 411111
				[last4] => 1111
				[cardType] => Visa
				[expirationMonth] => 11
				[expirationYear] => 2019
				[customerLocation] => US
				[imageUrl] => https://assets.braintreegateway.com/payment_method_logo/visa.png?environment=sandbox
				[expirationDate] => 11/2019
				[maskedNumber] => 411111******1111
			)
			*/
			
			
			$res = $this->braintree_model->initTrx($o);
			if(!is_null($res)) {

				// Notifica a comercio
				$oNotify = new stdClass();
				$oNotify->result = 1;
				$oNotify->message = "Proceso finalizado satisfactoriamente";
				
				$notify = $oTrx->urlNotify;
				$resNotify = $this->funciones->doPost($notify, $oNotify);
				
				if($resNotify) redirect($oTrx->urlOk);
				else redirect($oTrx->urlError);
					
			} else {
				
				// *** Buscar si se puede hacer un rollback de inmediato ***
				throw new Exception("Falló el registro en la base de datos", 1002);
			}
			
			
		} catch(Exception $e) {
			log_message("error", "Error en processingPayLoad(). Mensaje: ".$e->getMessage());
			if(isset($oTrx->urlError)) redirect($oTrx->urlError);
		}

	}
	
	
	public function updateNotify() {
		header('Access-Control-Allow-Origin: *');  
		
		$s = new stdClass();
		$s->errCode = 0;
		$s->errMessage = "";
		
		try {
			
			$initial = trim($this->input->post("initial"));
			$trx = trim($this->input->post("trx"));
			$email = trim($this->input->post("email"));
			
			//$json = '{"initial":1,"trx":"GxdqCnFRT9vuQgAELO","email":"prueba@prueba.com"}';
			
			/*$initial = 1;
			$trx = "GxdqCnFRT9vuQgAELO";
			$email = "prueba@prueba.com";*/
			
			//$o = json_decode($json);
			
			$o = new stdClass();
			$o->initial = $initial;
			$o->trx = $trx;
			$o->email = $email;
			
			log_message("debug", "Parámetros updateNotify() -> ".print_r($o, TRUE));
			
			if(empty($o)) throw new Exception("No se ha podido obtener información requerida", 1000);
			if(!isset($o->initial)) throw new Exception("No se ha encontrado el campo {initial}", 1000);
			if(!isset($o->trx)) throw new Exception("No se ha encontrado el campo {trx}", 1000);
			
			// Busca la transacción
			$oTrx = $this->core_model->getTrx($o->trx);
			if(is_null($oTrx)) throw new Exception("No se ha podido identificar la transacción", 1001);
			
			// Genera params a partir del json recibido
			$sumar = ((int)$o->initial == 1) ? "?" : "&";
			$urlNotify = $oTrx->urlNotify.$sumar;
			
			foreach($o as $key => $value) {
				// Considera solo las llaves
				if($key != "initial" && $key != "trx") {
					$urlNotify .= $key."=".$value."&";
				}
			}
			$urlNotify = substr($urlNotify,0,strlen($urlNotify)-1);
			
			// Actualiza el notify de la transacción
			$upd = new stdClass();
			$upd->urlNotify = $urlNotify;
			$res = $this->core_model->updateTrx($oTrx->idTrx, $upd);
			
			if(!$res) throw new Exception("No se pudo actualizar la información de la transacción", 1002);
			
			$s->errMessage = "OK";
			
		} catch(Exception $e) {
			log_message("error", "Error en updateNotify(). Mensaje: ".$e->getMessage());
			$s->errCode = $e->getCode();
			$s->errMessage = $e->getMessage();
		}
		
		
		$this->output
				->set_content_type('application/json')
				->set_output(json_encode($s));
		
	}
	
	
	// --------------------------------------------

	private function _doAction($token, $url, $postdata, $action = "post") {
		
		$conCurl = FALSE;
		$curl = NULL;
		
		try {
			
			//$action = strtolower($action);
			
			$curl = curl_init($url);
			$conCurl = TRUE;
			$headers = array(
				'Authorization: Bearer '.$token,
				'Accept: application/json',
				'Content-Type: application/json'	
			);
			
			curl_setopt($curl, CURLOPT_POST, ($action == "post") ? true : false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			if($action == "post") curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
			
			$response = curl_exec($curl); // llega como json
			
			if(empty($response)) {
				throw new Exception(curl_error($curl), 1001);
			} else {
				$info = curl_getinfo($curl);
				if($info['http_code'] != 200 && $info['http_code'] != 201) {
					throw new Exception("HTTP_CODE = ".$info['http_code'].", raw response: ".$response, 1002);
				}
			}
			
			// Resultado queda como objeto
			return json_decode($response, TRUE);
			
		} catch(Exception $e) {
			if($conCurl) curl_close($curl);
			log_message("error", "Error en POST -> cód: ".$e->getCode().", mensaje: ".$e->getMessage());
		}
		
		return NULL;
	
	}
}
