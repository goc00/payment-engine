<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Paypalnvp extends MY_Controller {
	
	public function __construct() {
		parent::__construct();
		$this->load->helper('string');
		$this->load->helper('creditcard');
		$this->load->helper('crypto');
		$this->load->library('encryption');
		$this->load->library('session');
		$this->load->library('funciones');
		$this->load->model('paypal_model', '', TRUE);
		$this->load->model('paypal_plan_model', '', TRUE);
		//$this->load->model('paypal_agreement_model', '', TRUE);
		$this->load->model('core_model', '', TRUE);
		
		date_default_timezone_set('America/Santiago');
	}
	
	public function index() {
		echo "what are you looking for?";
	}
	
	
	public function doPayPalNvp() {

		$s = new stdClass();
		$s->errNumber = 0;
		$s->errMessage = "";
		
		try {
			
			$post = $this->input->post();
			
			log_message("debug", print_r($post, TRUE));
			//print_r($post);
			
			$mess = "No se han definido todos los atributos necesarios";
		
			// Tomo e interpreto valores del POST
			$codPaymentType = $post["PT"];
			$amount = decode_url($post["am_".$codPaymentType]);
			$trx = decode_url($post["trx"]);
			if($codPaymentType == "") throw new Exception($mess." {codPaymentType}", 1000);
			if($amount == "") throw new Exception($mess." {amount}", 1000);
			if($trx == "") throw new Exception($mess." {trx}", 1000);

			// Busca el objeto de trx
			$oTrx = $this->core_model->getTrx($trx);
			if(is_null($oTrx)) throw new Exception("No se pudo identificar la transacción", 1002);
			 
			// Actualiza los valores de la trx con la opción de juego seleccionada
			$upd = new stdClass();
			$upd->idStage = parent::NUEVA_TRX_WP;
			$upd->idPaymentType = $codPaymentType;
			$upd->amount = $amount;
	
			$res = $this->core_model->updateTrx($oTrx->idTrx, $upd);
			if(!$res) throw new Exception("No se pudo actualizar la transacción", 1005);
			
			// STEP 1: Setear autorización de pago
			$step1 = $this->_setUpPaymentAuthorization($trx);
			if($step1->errNumber != 0) throw new Exception($step1->errMessage, 1001);
			
			$s->token = $step1->token;
			
			
		} catch(Exception $e) {
			$s->errNumber = $e->getCode();
			$s->errMessage = $e->getMessage();
			log_message("error", "Error en doPayPalNvp(). Código: ".$s->errNumber.", Mensaje: ".$s->errMessage);
		}
		
		$this->output
				->set_content_type('application/json')
				->set_output(json_encode($s));
		
	}
	
	
	
	
	
	// --------------------------------
	
	

	/**
	 * STEP 1: Setea la autorización de pago
	 */
	private function _setUpPaymentAuthorization($trx) {
		
		$salida = new stdClass();
		$salida->errNumber = 0;
		$salida->errMessage = "";

		$conTrans = FALSE;
		
		try {

			// Obtengo el objeto trx
			$oTrx = $this->core_model->getTrx($trx);
			
			// Obtengo el objeto comercio (se validó vigencia en paso anterior)
			$oComm = $this->core_model->getCommerceById($oTrx->idCommerce);
			
			$o = new stdClass();
			$o->L_BILLINGTYPE0 = "RecurringPayments";
			$o->L_BILLINGAGREEMENTDESCRIPTION0 = $this->config->item("PaypalPlanDescription");
			$o->cancelUrl = $oTrx->urlError;
			//$o->returnUrl = $oTrx->urlNotify;
			$o->returnUrl = base_url()."paypalnvp/notify";
			
			// -------------------------------------------
			// STEP1: ENVÍO de data a servicio NVP de PayPal
			// -------------------------------------------
			$res = $this->_doAction("SetExpressCheckout", $o);
			if(is_null($res)) throw new Exception("No se ha podido generar la autorización de pago", 1000);
			
			// Almaceno información inicial de la transacción
			// Verifica la respuesta desde PayPal
			/*
			[L_ERRORCODE0] => 10002
			[L_SHORTMESSAGE0] => Security%20error
			[L_LONGMESSAGE0] => Security%20header%20is%20not%20valid
			[L_SEVERITYCODE0] => Error
			*/
			
			$oPayPalTrx = new stdClass();
			$oPayPalTrx->idTrx = $oTrx->idTrx;
			if($res->ACK == "Success") $oPayPalTrx->token = $res->TOKEN; // solo hay token si es exitoso
			$oPayPalTrx->timestamp = $res->TIMESTAMP;
			$oPayPalTrx->correlationId = $res->CORRELATIONID;
			$oPayPalTrx->ack = $res->ACK;
			$oPayPalTrx->version = $res->VERSION;
			$oPayPalTrx->build = $res->BUILD;
			if($res->ACK != "Success") {
				$oPayPalTrx->errCode = $res->L_ERRORCODE0;
				$oPayPalTrx->errMsg = $res->L_LONGMESSAGE0;
			}

			$oPayPalTrxDecoded = $this->_decodeObject($oPayPalTrx); // decode para guardar en BD
			
			$resBd = $this->paypal_model->initTrxNvp($oPayPalTrxDecoded);
			if(is_null($resBd)) throw new Exception("No se ha pudo almacenar la respuesta de PayPal", 1001);
			
			if($res->ACK != "Success") {
				$errorCode = urldecode($res->L_ERRORCODE0);
				$msgError = urldecode($res->L_LONGMESSAGE0);
				throw new Exception("No se pudo procesar la autorización con PayPal. ErrorCode: $errorCode, Message: $msgError", 1002);
			}
				
			// OK
			$salida->errMessage = "OK";
			$salida->token = $oPayPalTrx->token;
			
		} catch(Exception $e) {
			$salida->errNumber = $e->getCode();
			$salida->errMessage = $e->getMessage();
			if($conTrans) $this->core_model->rollbackTrx();
		}
		
		return $salida;
	}
	
	/**
	 * STEP 2: Activación del plan creado. Debe estar en estado activo (ACTIVE)
	 * para poder relacionarlo a usuario
	 */
	private function _activePlan($idTrx, $token, $path) {
		
		$oActivePlan = new stdClass();
		$oState = new stdClass();
		$salida = new stdClass();
		
		$salida->errorNumber = 0;
		$salida->message = "";
		$oActivePlan->path = "/";
		$oState->state = "ACTIVE";
		$oActivePlan->value = $oState;
		$oActivePlan->op = "replace";
		$conTrans = FALSE;
		
		
		try {
			
			$curl = curl_init($path);
			$headers = array(
				'Authorization: Bearer '.$token,
				'Content-Type: application/json'	
			);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array($oActivePlan)));

			$response = curl_exec($curl); // llega como json
			$info = curl_getinfo($curl);
			
			curl_close($curl);
			
			// Respuesta correcta (si responde 200, se activó el plan)
			if($info['http_code'] != 200)
				throw new Exception("HTTP_CODE = ".$info['http_code'].", raw response: ".$response, 1000);
			
			// Verifica que el plan se haya activado (lo busco y obtengo su estado)
			$resGetPlan = $this->_doAction($token, $path, NULL, "get");
			if(is_null($resGetPlan))
				throw new Exception("No se pudo activar el plan, raw response: ".$response, 1001);
			
			// Se actualiza información de la transacción y planes
			$conTrans = TRUE;
			$this->core_model->inicioTrx();
			
			$oPayPalTrx = new stdClass();
			$oPayPalTrx->id = $resGetPlan["id"];
			$oPayPalTrx->state = $resGetPlan["state"];
			$oPayPalTrx->create_time = $resGetPlan["create_time"];
			$oPayPalTrx->update_time = $resGetPlan["update_time"];
			$res = $this->paypal_model->updateTrx($idTrx, $oPayPalTrx);
			
			if(!$res) throw new Exception("No se pudo actualizar el estado de la transacción idTrx = ".$idTrx, 1002);
			
			// Obtengo el PayPalTrx
			$res = $this->paypal_model->getByIdTrx($idTrx);
			
			// Actualiza los planes (respuesta desde PayPal)
			foreach($resGetPlan["payment_definitions"] as $PDs) {
				// Obtengo planes desde la bd
				$o = new stdClass();
				$o->id = $PDs["id"];
				$o->chm_id = $PDs["charge_models"][0]["id"];
				
				$pdsType = $PDs["type"];
				
				$oPPP = $this->paypal_plan_model->getPayPalPlan($res->idPayPalTrx, $pdsType);
				if(is_null($oPPP)) throw new Exception("No se encontró el plan > (idPayPalTrx: ".$res->idPayPalTrx.", type: ".$pdsType.")", 1003);
				
				$oPPPu = $this->paypal_plan_model->updateTrx($oPPP->idPayPalPlan, $o);
				if(!$oPPPu) throw new Exception("No se pudo actualizar estado del plan (idPayPalPlan: ".$oPPP->idPayPalPlan.")", 1003);
			}
			
			$this->core_model->commitTrx(); // commit a la transacción
			$conTrans = FALSE;
			
			// Devuelve el id del plan activado
			$salida->idPlan = $oPayPalTrx->id;
			
		} catch(Exception $e) {
			$salida->errorNumber = $e->getCode();
			$salida->message = $e->getMessage();
			//log_message("error", "Error en activePlan(). Código: ".$salida->errorNumber.", Mensaje: ".$salida->message);
			if($conTrans) $this->core_model->rollbackTrx();
		}
		
		return $salida;
		
	}
	
	/**
	 * STEP 3: Crea el agreement del plan creado
	 */
	private function _createAgreement($idTrx, $token, $idPlan) {
		
		$salida = new stdClass();
		$salida->errNumber = 0;
		$salida->errMessage = "";
		
		try {
			
			$o = new stdClass();
			
			$oPlan = new stdClass();
			$oPlan->id = $idPlan;
			
			$oPayer = new stdClass();
			$oPayer->payment_method = "paypal";
			
			$oShippingAddress = new stdClass();
			$oShippingAddress->line1 = $this->config->item("PaypalAddressLine1");
			$oShippingAddress->line2 = $this->config->item("PaypalAddressLine2");
			$oShippingAddress->city = $this->config->item("PaypalAddressCity");
			$oShippingAddress->state = $this->config->item("PaypalAddressState");
			$oShippingAddress->postal_code = $this->config->item("PaypalAddressPostalCode");
			$oShippingAddress->country_code = $this->config->item("PaypalAddressCountryCode");
			
			$o->name = $this->config->item("PaypalAgreementName");
			$o->description = $this->config->item("PaypalAgreementDescription");
			
			$date = new DateTime('+1 day');
			
			$o->start_date = $date->format("c"); // Date format yyyy-MM-dd z, as defined in ISO8601.
			$o->plan = $oPlan;
			$o->payer = $oPayer;
			//$o->shipping_address = $oShippingAddress;
			
			$resCreateAgreement = $this->_doAction($token,
											$this->config->item("PayPalServiceAgreements"),
											json_encode($o));
			
			if(is_null($resCreateAgreement)) throw new Exception("No se pudo crear el acuerdo de inscripción", 1001);
			
			
			// Obtiene las URL de APPROVAL y EXECUTE del agreement
			// (que se debe ejecutar DESPUÉS de la aprobación del usuario)
			$links = $resCreateAgreement["links"];
			foreach($links as $arrLink) {
				
				$rel = $arrLink["rel"];
				$href = $arrLink["href"];
				
				if($rel == "approval_url") {
					$salida->approvalAgreement = $href;
				} else if($rel == "execute") {
					$salida->executeAgreement = $href;
				}
			}
			
			// Actualiza las URL de agreement y obtiene el token de la transacción
			$qs = parse_url($salida->approvalAgreement, PHP_URL_QUERY);
			$arrQs = explode("&", $qs);
			$val = "";
			foreach($arrQs as $aqs) {
				$arr = explode("=", $aqs);
				if($arr[0] == "token") {
					$val = $arr[1];
					break;
				}
			}
			
			$up = new stdClass();
			$up->approval_agreement_url = $salida->approvalAgreement;
			$up->execute_agreement_url = $salida->executeAgreement;
			$up->token = $val;
			
			$urls = $this->paypal_model->updateTrx($idTrx, $up);

		} catch(Exception $e) {
			$salida->errNumber = $e->getCode();
			$salida->errMessage = $e->getMessage();
		}
		
		return $salida;
		
	}
	
	/**
	 * Solo para pruebas, debería lanzar URL de error del PRODUCTO
	 */
	public function error() {
		echo "La transacción ha sido cancelada";
	}
	public function ok() {
		echo "¡Transacción exitosa!";
	}
	
	/**
	 * Procesa el resultado de la transacción en PayPal
	 */
	public function notify() {
		
		$data = array();
		$data["error"] = FALSE;
		$data["message"] = "El perfil {TOKEN} de pago recurrente, ha sido creado satisfactoriamente";
		$estados = array(
			"ok" => 15,
			"error" => 16
		);
		
		$oPayPalTrx = new stdClass();
		try {
			
			// Valida que vengan datos necesarios para proceder
			$token = $this->input->get("token");
			if(empty($token)) throw new Exception("No se ha podido identificar la transacción", 1000);
			
			// Busca la trx por el token
			$oPayPalTrx = $this->paypal_model->getByToken($token, "nvp");
			if(is_null($oPayPalTrx)) throw new Exception("La transacción $token no pudo ser identificada en el sistema", 1000);

			// Estado a actualizar la transacción
			$res = $this->core_model->updateStageTrx($oPayPalTrx->idTrx, 17);
			if(!$res) throw new Exception("La transacción $token ha fracasado", 1004);

			// -------------------------------------------
			// STEP 3: Obtención detalles del cliente
			// -------------------------------------------
			$o = new stdClass();
			$o->TOKEN = $token;
			$res = $this->_doAction("GetExpressCheckoutDetails", $o);
			if(is_null($res)) throw new Exception("No se ha podido generar la autorización de pago", 1005);
			
			$oBd = new stdClass();
			$oBd->idPayPalTrxNvp = $oPayPalTrx->idPayPalTrxNvp;
			$oBd->timestamp = $res->TIMESTAMP;
			$oBd->correlationId = $res->CORRELATIONID;
			$oBd->ack = $res->ACK;
			
			if($res->ACK != "Success") {
				$oBd->errCode = $res->L_ERRORCODE0;
				$oBd->errMsg = $res->L_LONGMESSAGE0;
			} else {
				$oBd->billingAgreementAcceptedStatus = $res->BILLINGAGREEMENTACCEPTEDSTATUS;
				$oBd->checkoutStatus = $res->CHECKOUTSTATUS;
				$oBd->email = $res->EMAIL;
				$oBd->payerId = $res->PAYERID;
				$oBd->payerStatus = $res->PAYERSTATUS;
				$oBd->firstName = $res->FIRSTNAME;
				$oBd->lastName = $res->LASTNAME;
				$oBd->countryCode = $res->COUNTRYCODE;
				$oBd->shipToName = $res->SHIPTONAME;
				$oBd->shipToStreet = $res->SHIPTOSTREET;
				$oBd->shipToCity = $res->SHIPTOCITY;
				$oBd->shipToState = $res->SHIPTOSTATE;
				$oBd->shipToZip = $res->SHIPTOZIP;
				$oBd->shipToCountryCode = $res->SHIPTOCOUNTRYCODE;
				$oBd->shipToCountryName = $res->SHIPTOCOUNTRYNAME;
				$oBd->addressStatus = $res->ADDRESSSTATUS;
				$oBd->currencyCode = $res->CURRENCYCODE;
				$oBd->amt = $res->AMT;
				$oBd->shippingAmt = $res->SHIPPINGAMT;
				$oBd->handlingAmt = $res->HANDLINGAMT;
				$oBd->taxAmt = $res->TAXAMT;
				$oBd->insuranceAmt = $res->INSURANCEAMT;
				$oBd->shipDiscAmt = $res->SHIPDISCAMT;		
			}
			
			// Decodifica para guardar en bd
			$resDecoded = $this->_decodeObject($oBd);
			
			$resSave = $this->paypal_model->saveDetails($resDecoded);
			if(is_null($resSave)) throw new Exception("No se pudo almacenar el detalle de la transacción", 1007);
			
			if($res->ACK != "Success") {
				$errorCode = urldecode($res->L_ERRORCODE0);
				$msgError = urldecode($res->L_LONGMESSAGE0);
				throw new Exception("No se pudo procesar la autorización con PayPal. ErrorCode: $errorCode, Message: $msgError", 1006);
			}
			
			// -------------------------------------------
			// STEP 4: Crea el perfil de pago recurrentes
			// -------------------------------------------
			$crpp = $this->_createRecurringPaymentsProfile($oPayPalTrx->idTrx, $resSave, $token, $res->PAYERID);
			if($crpp->errCode != 0) throw new Exception("Falló la creación del perfil de pago recurrente // ".$crpp->errMsg, 1007);
			
			// OK, hace redirect a página OK de producto
			$data["message"] = str_replace("{TOKEN}", $crpp->profileId, $data["message"]);
			$oTrx = $this->core_model->getTrxById($oPayPalTrx->idTrx);
			
			// Invoco notify de producto
			// **************************************************
			// NOTIFICA AL COMERCIO A TRAVÉS DE URL PROPORCIONADA
			// **************************************************
			$oNotify = new stdClass();
			//$oNotify->result = ((int)$oRc->code != 0) ? 0 : 1;
			$oNotify->result = 1; // OK
			$oNotify->message = "La transacción ha finalizado satisfactoriamente";
			$oNotify->idUserExternal = $oTrx->idUserExternal;
			$oNotify->idApp = $oTrx->idApp;
			$oNotify->idPlan = $oTrx->idPlan;
			$oNotify->idCountry = $oTrx->idCountry;
			
			$oNotifyRes = $this->_notify($oTrx->urlNotify, $oNotify);
			
			if($oNotifyRes) {
				///$this->core_model->updateStageTrx($oTrxWP->idTrx, parent::OK_ALL);
			} else {
				//$this->core_model->updateStageTrx($oTrxWP->idTrx, parent::OK_NO_RESP_NOTIFY);
			}
			
			redirect($oTrx->urlOk);
			
		} catch(Exception $e) {
			$data["error"] = TRUE;
			$data["message"] = $e->getMessage();
			log_message("error", "Error en result() -> ".$e->getMessage());
			
			// Si llega 1000, debe buscar la trx en session
			$oTrx = new stdClass();
			if($e->getCode() == 1000) {
				$trx = $this->session->userdata("trx");
				$oTrx = $this->core_model->getTrx($trx);
			} else {
				$oTrx = $this->core_model->getTrxById($oPayPalTrx->idTrx);
			}
			$this->session->unset_userdata("trx");
			redirect($oTrx->urlError);
		}

	}
	
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
	
	
	/**
	 * STEP 4: Crea el perfil de pago recurrentes
	 */
	private function _createRecurringPaymentsProfile($idTrx, $idPayPalTrxNvpDetail, $token, $payerId) {
		
		$salida = new stdClass();
		$salida->errCode = 0;
		$salida->errMsg = "";
		
		try {
			
			$oTrx = $this->core_model->getTrxById($idTrx);
			if(is_null($oTrx)) throw new Exception("No se pudo identificar la transacción", 1000);
			
			$date = new DateTime('+1 day');
			
			$o = new stdClass();
			$o->TOKEN = $token;
			$o->PAYERID = $payerId;
			$o->PROFILESTARTDATE = $date->format("c"); //format yyyy-MM-dd z, as defined in ISO8601.
			$o->DESC = $this->config->item("PaypalPlanDescription");
			$o->BILLINGPERIOD = $this->config->item("PaypalPlanRegularFrequency");
			$o->BILLINGFREQUENCY = $this->config->item("PaypalPlanRegularCycles");
			$o->AMT = $oTrx->amount;
			$o->CURRENCYCODE = $this->config->item("PaypalPlanAmountCurrency");
			$o->COUNTRYCODE = $this->config->item("PaypalAddressCountryCode");
			$o->MAXFAILEDPAYMENTS = $this->config->item("PaypalMaxFailedPayments");
			
			// RESPUESTA PAYPAL
			$res = $this->_doAction("CreateRecurringPaymentsProfile", $o);
			$resDecoded = $this->_decodeObject($res);
			
			// Inserta en base de datos
			$oDecoded = $this->_decodeObject($o);
			$save = new stdClass();
			$save->idPayPalTrxNvpDetail = $idPayPalTrxNvpDetail;
			$save->profileStartDate = $oDecoded->PROFILESTARTDATE;
			$save->desc = $oDecoded->DESC;
			$save->billingPeriod = $oDecoded->BILLINGPERIOD;
			$save->billingFrequency = $oDecoded->BILLINGFREQUENCY;
			$save->amt = $oDecoded->AMT;
			$save->currencyCode = $oDecoded->CURRENCYCODE;
			$save->countryCode = $oDecoded->COUNTRYCODE;
			$save->maxFailedPayments = $oDecoded->MAXFAILEDPAYMENTS;
			$save->timestamp = $resDecoded->TIMESTAMP;
			$save->correlationId = $resDecoded->CORRELATIONID;
			$save->ack = $resDecoded->ACK;
			$save->version = $resDecoded->VERSION;
			$save->build = $resDecoded->BUILD;
			
			$errCodeArr = array();
			$errMsgArr = array();
			$errCodeStr = "";
			$errMsgStr = "";
			if($resDecoded->ACK != "Success") {
				// Obtiene los errores
				foreach($resDecoded as $key => $value) {
					if(strpos($key, "L_ERRORCODE") !== FALSE) $errCodeArr[] = $resDecoded->$key;
					if(strpos($key, "L_LONGMESSAGE") !== FALSE) $errMsgArr[] = $resDecoded->$key;
				}
				
				$errCodeStr = join(" - ", $errCodeArr);
				$errMsgStr = join(" - ", $errMsgArr);
				
				$save->errCode = $errCodeStr; // errCode
				$save->errMsg = $errMsgStr; // errMsg

			} else {
				$save->profileId = $resDecoded->PROFILEID;
				$save->profileStatus = $resDecoded->PROFILESTATUS;
			}
			
			$idPayPalTrxNvpProfile = $this->paypal_model->saveProfile($save);
			if(is_null($idPayPalTrxNvpProfile)) throw new Exception("No se pudo almacenar la información del perfil de pago recurrente", 1001);
			if($resDecoded->ACK != "Success") throw new Exception("Códigos: ".$errCodeStr.", Mensajes: ".$errMsgStr, 1002);
			
			$salida->profileId = $resDecoded->PROFILEID;
			
		} catch(Exception $e) {
			$salida->errCode = $e->getCode();
			$salida->errMsg = $e->getMessage();
		}
		
		return $salida;
	}
	
	
	/**
	 * Método que se ejecuta todos los días, obteniendo status de los profiles creados
	 */
	public function checkProfiles() {
		 set_time_limit(0);
		 
		$salida = new stdClass();
		$salida->err_number = 0;
		$salida->message = "";
		
		try {
			
			// Obtiene todos los profiles de paypal
			$profiles = $this->paypal_model->getAllProfiles();
			if(is_null($profiles)) throw new Exception("No hay perfiles de recurrencia (PayPal) para procesar", 1000);
			
			// Obtiene estado desde PayPal
			$results = array();
			foreach($profiles as $oProfile) {
				$o = new stdClass();
				$o->PROFILEID = $oProfile->profileId;
				$doInsert = FALSE;
				
				// RESPUESTA PAYPAL
				$res = $this->_doAction("GetRecurringPaymentsProfileDetails", $o);
				$resDecoded = $this->_decodeObject($res);
				
				// Interpreta respuesta y almacena resultado
				$save = new stdClass();
				$save->idPayPalTrxNvpProfile = $oProfile->idPayPalTrxNvpProfile;
				$save->ack = $resDecoded->ACK;
				$save->version = $resDecoded->VERSION;
				$save->build = $resDecoded->BUILD;
				$save->correlationId = $resDecoded->CORRELATIONID;
				
				if($resDecoded->ACK == "Success") {
					
					$save->status = $resDecoded->STATUS;
					$save->autoBilloutAmt = $resDecoded->AUTOBILLOUTAMT;
					$save->aggregateAmt = $resDecoded->AGGREGATEAMT;
					$save->amt = $resDecoded->AMT;
					$save->regularAmt = $resDecoded->REGULARAMT;
					$save->taxAmt = $resDecoded->TAXAMT;
					$save->regularTaxAmt = $resDecoded->REGULARTAXAMT;
					$save->nextBillingDate = $resDecoded->NEXTBILLINGDATE;
					$save->numCyclesCompleted = $resDecoded->NUMCYCLESCOMPLETED;
					$save->outstandingBalance = $resDecoded->OUTSTANDINGBALANCE;
					$save->failedPaymentCount = $resDecoded->FAILEDPAYMENTCOUNT;
					$save->lastPaymentDate = $resDecoded->LASTPAYMENTDATE;
					$save->lastPaymentAmt = $resDecoded->LASTPAYMENTAMT;
					
					// SOLO hará un insert, si la data efectivamente cambió (por ende, se hizo un pago)
					$history = $this->paypal_model->getHistory($save->idPayPalTrxNvpProfile,
																$save->status,
																$save->nextBillingDate,
																$save->lastPaymentDate);
					// Si llega vacío, es porque hubo cambio y debe ser registrado
					if(is_null($history)) $doInsert = TRUE;
					
				} else {
					
					// Obtiene los errores
					$errCodeArr = array();
					$errMsgArr = array();
					$errCodeStr = "";
					$errMsgStr = "";
				
					foreach($resDecoded as $key => $value) {
						if(strpos($key, "L_ERRORCODE") !== FALSE) $errCodeArr[] = $resDecoded->$key;
						if(strpos($key, "L_LONGMESSAGE") !== FALSE) $errMsgArr[] = $resDecoded->$key;
					}
					
					$errCodeStr = join(" - ", $errCodeArr);
					$errMsgStr = join(" - ", $errMsgArr);
					
					$save->errCode = $errCodeStr; // errCode
					$save->errMsg = $errMsgStr; // errMsg
					
					$doInsert = TRUE;
					
				}
				
				$results[] = $save;
				
				if($doInsert) {
					// insert a paypal history
					$save->creationDate = date("Y-m-d H:i:s");
					$saveAction = $this->paypal_model->saveHistory($save);
					//if($saveAction) throw new Exception("No se pudo registrar el estado de PayPal", 1001);
				}

			}
			
			/*echo "<pre>";
			print_r($results);
			echo "</pre>";*/
			
		
		} catch(Exception $e) {
			$salida->err_number = $e->getCode();
			$salida->message = $e->getMessage();
			$type = $salida->err_number == 1000 ? "debug" : "error";
			log_message($type, $salida->message);
		}
		
		echo json_encode($salida);
	} 
	 
	
	
	// --------------------------------------------

	
	/**
	 * Ejecución servicio PayPal NVP
	 */
	private function _doAction($method, $postdata) {
		
		$conCurl = FALSE;
		$curl = NULL;
		
		try {
			
			// Le agrega credenciales al objeto
			$postdata->USER = $this->config->item("PayPalUsernameNvp");
			$postdata->PWD = $this->config->item("PayPalPasswordNvp");
			$postdata->SIGNATURE = $this->config->item("PayPalSignatureNvp");
			$postdata->VERSION = $this->config->item("PayPalVersionNvp");
			$postdata->METHOD = $method;
			
			// Genera cadena
			$data = $this->_generateStringNvp($postdata);
			
			log_message("debug", print_r($data, TRUE));
			
			$curl = curl_init($this->config->item("PayPalAPISignature"));
			$conCurl = TRUE;
			
			curl_setopt($curl, CURLOPT_POST, TRUE);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			
			$response = curl_exec($curl);
			
			if(empty($response)) {
				throw new Exception(curl_error($curl), 1001);
			} else {
				$info = curl_getinfo($curl);
				if($info['http_code'] != 200 && $info['http_code'] != 201) {
					throw new Exception("HTTP_CODE = ".$info['http_code'].", raw response: ".$response, 1002);
				}
			}
			
			// Resultado queda como objeto
			//return json_decode($response, TRUE);
			return $this->_response2Object($response);
			
		} catch(Exception $e) {
			if($conCurl) curl_close($curl);
			log_message("error", "Error en POST -> cód: ".$e->getCode().", mensaje: ".$e->getMessage());
		}
		
		return NULL;
	
	}
	
	/**
	 * Parsea la información y convierte en string para NVP
	 */
	private function _generateStringNvp($o) {
	
		$salida = "";
		
		if(!is_null($o)) {
			foreach($o as $key => $value) {
				$salida .= $key."=".urlencode($value)."&";
			}
			
			$salida = substr($salida, 0, strlen($salida)-1);
		}

		return $salida;
	}
	
	/**
	 * Procesa la respuesta (string) y la convierte a objeto
	 */
	private function _response2Object($str) {
		
		$salida = new stdClass();
		$arr = explode("&", $str);
		
		foreach($arr as $part) {
			$kv = explode("=", $part);
			$salida->$kv[0] = $kv[1];
		}
		
		return $salida;
	}
	
	/**
	 * Decodifica los valores del objeto
	 */
	private function _decodeObject($o) {
		
		$o2 = new stdClass();
		
		foreach($o as $key => $value) {
			$o2->$key = urldecode($value);
		}
		return $o2;
	}
	
}
