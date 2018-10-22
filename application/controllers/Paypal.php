<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Paypal extends MY_Controller {
	
	public function __construct() {
		parent::__construct();
		$this->load->helper('string');
		$this->load->helper('creditcard');
		$this->load->helper('crypto');
		$this->load->library('encryption');
		$this->load->library('session');
		$this->load->model('paypal_model', '', TRUE);
		$this->load->model('paypal_plan_model', '', TRUE);
		$this->load->model('paypal_agreement_model', '', TRUE);
		$this->load->model('core_model', '', TRUE);
		
		date_default_timezone_set('America/Santiago');
	}
	
	public function index() {
		echo "what are you looking for?";
	}
	
	// --------------------------------
	
	/**
	 * Inicia proceso en PayPal
	 * Recibe el contenido enviado por POST y lo procesa
	 */
	public function doPayPal() {

		$s = new stdClass();
		$s->errNumber = 0;
		$s->errMessage = "";
		
		try {
			
			$post = $this->input->post();
			$mess = "No se han definido todos los atributos necesarios";
		
			// Tomo e interpreto valores del POST
			$codPaymentType = $post["PT"];
			$amount = decode_url($post["am_".$codPaymentType]);
			$trx = decode_url($post["trx"]);
			if($codPaymentType == "") throw new Exception($mess." {codPaymentType}", 1000);
			if($amount == "") throw new Exception($mess." {amount}", 1000);
			if($trx == "") throw new Exception($mess." {trx}", 1000);
			
			// Obtengo el Access-Token de la cuenta
			$accessToken = $this->_getAccessToken();
			if(is_null($accessToken)) throw new Exception("No se pudo obtener el Access-Token", 1001);
			
			// Obtengo objeto trx (se validó en core que exista)
			$oTrx = $this->core_model->getTrx($trx);
			
			// Creo plan de pago recurrente
			$createPlan = $this->_createPlan($accessToken, $codPaymentType, $amount, $oTrx);
			if($createPlan->errNumber != 0) throw new Exception($createPlan->errNumber, $createPlan->errMessage);
			
			// Activo plan creado
			$activePlan = $this->_activePlan($oTrx->idTrx, $accessToken, $createPlan->path);
			if($activePlan->errorNumber != 0) throw new Exception($activePlan->message, 1002);
			
			// Creo agreement del plan creado para aprobación de usuario
			// El resultado exitoso son las URL de approval y execute (agreement)
			$createAgreement = $this->_createAgreement($oTrx->idTrx, $accessToken, $activePlan->idPlan);
			if($createAgreement->errNumber != 0) throw new Exception($createAgreement->errNumber, $createAgreement->errMessage);
			
			$s->approvalAgreement = $createAgreement->approvalAgreement;
			$s->execAgreement = $createAgreement->executeAgreement;
			
		} catch(Exception $e) {
			$s->errNumber = $e->getCode();
			$s->errMessage = $e->getMessage();
			log_message("error", "Error en doPayPal(). Código: ".$s->errNumber.", Mensaje: ".$s->errMessage);
		}
		
		$this->output
				->set_content_type('application/json')
				->set_output(json_encode($s));
		
	}
	

	/**
	 * STEP 1: Crea plan de pago recurrente para usuario
	 */
	private function _createPlan($accessToken, $codPaymentType, $amount, $oTrx) {
		
		$salida = new stdClass();
		$salida->errNumber = 0;
		$salida->errMessage = "";

		$conTrans = FALSE;
		
		try {

			// Obtengo el objeto trx
			// Obtengo el objeto comercio (se validó vigencia en paso anterior)
			$oComm = $this->core_model->getCommerceById($oTrx->idCommerce);

			// Crea objeto BillingPlan
			$billingPlan = new stdClass();
			$billingPlan->name = $this->config->item("PaypalPlanName");
			$billingPlan->name = str_replace("{PRODUCT_NAME}", $oComm->name, $billingPlan->name); // le pasa el nombre del producto
			$billingPlan->description = $this->config->item("PaypalPlanDescription");
			$billingPlan->type = $this->config->item("PaypalPlanType");
			
			// Monto y chargeModel, que se comparten al ser una misma transacción
			// De igual manera, está la opción de generar distintos valores si es necesario
			$oAmount = new stdClass();
			$oAmount->currency = $this->config->item("PaypalPlanAmountCurrency");
			$oAmount->value = $amount;
			
			$chargeModel = new stdClass();
			$chargeModel->type = $this->config->item("PaypalPlanChargeModelType");
			$chargeModel->amount = $oAmount;
			
			$paymentDefinitionTrial = new stdClass();
			$paymentDefinition = new stdClass();
			
			// Crea un objeto si está habilitado el Trial
			if($this->config->item("PaypalPlanTrial")) {
				$paymentDefinitionTrial->name = $this->config->item("PaypalPlanTrialName");
				$paymentDefinitionTrial->type = $this->config->item("PaypalPlanTrialType");
				$paymentDefinitionTrial->frequency_interval = $this->config->item("PaypalPlanTrialFrequencyInterval");
				$paymentDefinitionTrial->frequency = $this->config->item("PaypalPlanTrialFrequency");
				$paymentDefinitionTrial->cycles = $this->config->item("PaypalPlanTrialCycles");

				$paymentDefinitionTrial->amount = $oAmount;
				$paymentDefinitionTrial->charge_models = array($chargeModel);
				
				$billingPlan->payment_definitions[] = $paymentDefinitionTrial;
			}
			
			// Plan regular, pago recurrente
			$paymentDefinition->name = $this->config->item("PaypalPlanRegularName");
			$paymentDefinition->type = $this->config->item("PaypalPlanRegularType");
			$paymentDefinition->frequency_interval = $this->config->item("PaypalPlanRegularFrequencyInterval");
			$paymentDefinition->frequency = $this->config->item("PaypalPlanRegularFrequency");
			$paymentDefinition->cycles = $this->config->item("PaypalPlanRegularCycles");

			$paymentDefinition->amount = $oAmount;
			$paymentDefinition->charge_models = array($chargeModel);
			
			$billingPlan->payment_definitions[] = $paymentDefinition;
			
			// Merchant Preferences
			$merchantPreferences = new stdClass();
			$oSetupFee = new stdClass();
			$oSetupFee->currency = $this->config->item("PaypalPlanAmountCurrency");
			$oSetupFee->value = "0";
			
			$merchantPreferences->setup_fee = $oSetupFee;
			$merchantPreferences->cancel_url = $oTrx->urlError;
			$merchantPreferences->return_url = $oTrx->urlNotify;
			$merchantPreferences->initial_fail_amount_action = $this->config->item("PaypalPlanInitialFailAmountAction");
			
			$billingPlan->merchant_preferences = $merchantPreferences;

			// -------------------------------------------
			// ENVÍO de data a servicio REST API de PayPal
			// -------------------------------------------
			$resCreatePlan = $this->_doAction($accessToken,
											$this->config->item("PayPalServicePlans"),
											json_encode($billingPlan));
			
			if(is_null($resCreatePlan)) throw new Exception("No se ha podido crear el plan", 1001);
			
			// Almaceno información inicial de la transacción
			$oPayPalTrx = new stdClass();
			$oPayPalTrx->idTrx = $oTrx->idTrx;
			$oPayPalTrx->name = $billingPlan->name;
			$oPayPalTrx->description = $billingPlan->description;
			$oPayPalTrx->type = $billingPlan->type;
			$oPayPalTrx->state = $resCreatePlan["state"];
			
			// INicia transacción
			$this->core_model->inicioTrx();
			$conTrans = TRUE;
			
			$idPayPalTrx = $this->paypal_model->initTrx($oPayPalTrx);
			
			if(is_null($idPayPalTrx)) throw new Exception("No se ha podido almacenar la transacción", 1002);
			
			// Almacena los planes asociados a la transacción
			$ok = 0;
			foreach($billingPlan->payment_definitions as $oPD) {
				$oBdPD = new stdClass();
				$oBdPD->idPayPalTrx = $idPayPalTrx;
				$oBdPD->name = $oPD->name;
				$oBdPD->type = $oPD->type;
				$oBdPD->frequency_interval = $oPD->frequency_interval;
				$oBdPD->frequency = $oPD->frequency;
				$oBdPD->cycles = $oPD->cycles;
				$oBdPD->amount_currency = $oPD->amount->currency;
				$oBdPD->amount_value = $oPD->amount->value;
				$oBdPD->chm_type = $oPD->charge_models[0]->type;
				$oBdPD->chm_amount_currency = $oPD->amount->currency;
				$oBdPD->chm_amount_value = $oPD->charge_models[0]->amount;
				
				$res = $this->paypal_plan_model->initTrx($oBdPD);
				
				if(!is_null($res)) $ok++;
				else throw new Exception("No se pudo almacenar el plan -> ".print_r($oBdPD, TRUE), 1003);
			}
			
			// Si llega acá, se logró almacenar toda la información
			$this->core_model->commitTrx();
			$conTrans = FALSE;
			
			// Almaceno la respuesta de PayPal en el plan creado
			// Queda en estado CREATED, debe pasar a ACTIVE para poder asociarlo a usuario
			// Obtiene URL con la que se activará el plan 
			$salida->errMessage = "OK";
			$salida->path = $resCreatePlan["links"][0]["href"];
			
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
	 * Procesa el resultado de la transacción en PayPal
	 */
	public function result($resultado) {
		
		$data = array();
		$data["error"] = FALSE;
		$data["message"] = "La transacción {TOKEN} ha finalizado satisfactoriamente";
		$estados = array(
			"ok" => 15,
			"error" => 16
		);
		
		try {
			
			// Valida que vengan datos necesarios para proceder
			$token = $this->input->get("token");
			if(empty($token)) throw new Exception("No se ha podido identificar la transacción", 1000);
			if($resultado != "notify") throw new Exception("La transacción $token ha fracasado", 1001);
			
			// Busca la trx por el token
			$oPayPalTrx = $this->paypal_model->getByToken($token);
			if(is_null($oPayPalTrx)) throw new Exception("La transacción $token no pudo ser identificada en el sistema", 1002);
			
			// Estado a actualizar la transacción
			//$idStage = $estados[$resultado];
			$res = $this->core_model->updateStageTrx($oPayPalTrx->idTrx, 17); // AGREEMENT APROBADO
			if(!$res) throw new Exception("La transacción $token ha fracasado", 1004);
			
			// Mensaje OK
			$data["message"] = str_replace("{TOKEN}", $token, $data["message"]);
			
			// Si está todo OK, hace un POST para ejecutar el agreement
			//redirect($oPayPalTrx->execute_agreement_url);
			$accessToken = $this->_getAccessToken();
			if(is_null($accessToken)) throw new Exception("No se pudo obtener el Access-Token", 1005);
			
			$o = new stdClass();
			$execAgreement = $this->_doAction($accessToken,
											  $oPayPalTrx->execute_agreement_url,
											  json_encode($o));
			
			// Inserta los datos del agreement
			$oAgreement = new stdClass();
			$oAgreement->idPayPalTrx = $oPayPalTrx->idPayPalTrx;
			$oAgreement->id = $execAgreement["id"];
			$oAgreement->state = $execAgreement["state"];
			$oAgreement->description = $execAgreement["description"];
			$oAgreement->payer_payment_method = $execAgreement["payer"]["payment_method"];
			$oAgreement->payer_status = $execAgreement["payer"]["status"];
			$oAgreement->payer_info_email = $execAgreement["payer"]["payer_info"]["email"];
			$oAgreement->payer_info_first_name = $execAgreement["payer"]["payer_info"]["first_name"];
			$oAgreement->payer_info_last_name = $execAgreement["payer"]["payer_info"]["last_name"];
			$oAgreement->payer_info_payer_id = $execAgreement["payer"]["payer_info"]["payer_id"];
			$oAgreement->start_date = $execAgreement["start_date"];
			$oAgreement->final_payment_date = $execAgreement["agreement_details"]["final_payment_date"];
			
			$insAggr = $this->paypal_agreement_model->insert($oAgreement);
			if(is_null($insAggr)) throw new Exception("No se pudo almacenar la información del Agreement", 1010);
			
			echo "<pre>";
			print_r($execAgreement);
			echo "</pre>";
			
		} catch(Exception $e) {
			$data["error"] = TRUE;
			$data["message"] = $e->getMessage();
			log_message("error", "Error en result() -> ".$e->getMessage());
			$this->load->view("paypal/result", $data);
		}

	}
	
	// --------------------------------------------
	
	// Obtengo el Access-Token a través de OAuth
	private function _getAccessToken() {
		
		$url = $this->config->item("PayPalServiceOAuth"); 
		$postArgs = 'grant_type=client_credentials';
		$clientId = $this->config->item("PayPalClientID");
		$clientSecret = $this->config->item("PayPalSecret");
		
		$curl = curl_init($url); 
		curl_setopt($curl, CURLOPT_POST, true); 
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_USERPWD, $clientId . ":" . $clientSecret);
		curl_setopt($curl, CURLOPT_HEADER, false); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postArgs); 

		try {
			
			$response = curl_exec($curl);
			
			if (empty($response)) {
				curl_close($curl); // close cURL handler
				throw new Exception(curl_error($curl), 1000);
			} else {
				
				$info = curl_getinfo($curl);
				//echo "Time took: " . $info['total_time']*1000 . "ms\n";
				
				curl_close($curl); // close cURL handler
				
				if($info['http_code'] != 200 && $info['http_code'] != 201) {
					throw new Exception("HTTP_CODE = ".$info['http_code'].", raw response: ".$response, 1001);
				}
			}
			// Convert the result from JSON format to a PHP array 
			$jsonResponse = json_decode($response);
			
			log_message("debug", "Obtención de Access-Token satisfactoria");
			return $jsonResponse->access_token;
			
		} catch(Exception $e) {
			log_message("error", "Error en get_access_token() -> cód: ".$e->getCode().", mensaje: ".$e->getMessage());
		}
		
		return NULL;
	
	}

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
