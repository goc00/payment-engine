<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| ----------------------------
| TuSaldo (mobile) Integración v1.0
| ----------------------------
| Autor: Gastón Orellana
| Descripción: Opera todos los flujos para el cobro por HotBilling (mobile)
| Fecha creación: 11/02/2017
|
| ---------------
| Modificaciones:
| ---------------
| v1.0: 11/02/2017, GOC
| Primera versión de integración
*/

class Saldo extends MY_Controller {

	private $controller = "";
	
	// Etapas de transacción
	const NEW_TRX_SALDO						= 31;
	const REQUEST_HOTBILLING_SALDO			= 32;
	const FAILED_REQUEST_HOTBILLING_SALDO	= 33;
	const OK_REQUEST_HOTBILLING_SALDO		= 34;
	

	public function __construct() {
		parent::__construct();
		$this->load->helper('string');
		$this->load->helper('creditcard');
		$this->load->helper('crypto');
		$this->load->helper('url');
		//$this->load->library('oneclicklib');
		$this->load->model('hotbilling_model', '', TRUE);
		$this->load->model('core_model', '', TRUE);
		
		$this->controller = base_url().'saldo/';
	}
	
	
	/**
	 * Prueba de flujo para Oneclick
	 */
	public function authorizeTest() {

		$service = $this->controller."authorize";
		$curl = curl_init($service);
		
		$post;
		$ok = TRUE;
		
		if($ok) {
			$post = array(
				"CodExternal"		=> "ABC999",
				"UrlNotify"			=> $this->controller."notify",
				"UrlOk"           	=> $this->controller."result/ok",
				"UrlError"			=> $this->controller."result/error",
				"CommerceID"		=> 1234, // comercio de prueba
				"PaymentType"		=> 9, // tu saldo
				"Code"				=> "CLMOBI1001", // id de cobro
				"Ani"				=> "56912345678",
				"Login3G"			=> "demo",
				"Pass3G"			=> "3TCodoDV"
			);
		} else {
			$post = array(
				"CodExternal"		=> "ABC123",
				"UrlNotify"			=> $this->controller."notify",
				"UrlOk"           	=> $this->controller."result/ok",
				"UrlError"			=> $this->controller."result/error",
				"CommerceID"		=> 1234, // comercio de prueba
				"PaymentType"		=> 9, // tu saldo
				"Code"				=> "XXXXXXXX", // id de cobro
				"Ani"				=> "56962376680",
				"Login3G"			=> "3gmotion",
				"Pass3G"			=> "passX"
			);
		}

		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, FALSE); // NO espera respuesta
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
	
		$exec = curl_exec($curl);
		curl_close($curl);
	}
	
	public function result($res) {
		echo "El proceso ha terminado inesperadamente con $res";
	}
		
	
	/**
	 * Cobro por HotBilling
	 *
	 * @return string (json)
	 *
	 */
	public function authorize() {

		$salida = new stdClass();
		$salida->code = 0;
		$salida->message = "";
		
		// Recibe parámetros
		try {
			
			$msgRequiredError = "No se ha detectado el parámetro [PARAM]";
			$format = "Y-m-d H:i:s";

			$paramsRequired = array(
									"CodExternal",
									"UrlOk",
									"UrlError",
			                        "UrlNotify",
									"CommerceID",							
			                        "PaymentType",
									"Code",	// código de cobro (monto es implícito a este)
			                        "Ani",
									"Login3G",
									"Pass3G"
								);
			
			// Recibe los parámetros por POST
			$post = $this->input->post(NULL, TRUE);
			log_message("debug", "REQUEST: " . __METHOD__ . print_r($post, TRUE));

			// Valida que venga toda la información requerida
			// Setea de inmediato las URLs de respuesta
			if(empty($post)) throw new Exception("No se ha recibido ningún dato desde el origen", 1000); // que vengan datos desde el origen
			$post =	(object)$post;
			$l = count($paramsRequired);
			for($i=0;$i<$l;$i++) {
				$key = $paramsRequired[$i];
				if(!isset($post->$key)) throw new Exception(str_replace("PARAM", $key, $msgRequiredError), 1001);
			}
			
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
			
			// ----------------------------------------
			// Crea transacción en motor de pagos (trx)
			// Tabla "trx"
			// ----------------------------------------
			$o = new stdClass();
			
			$o->idStage = self::NEW_TRX_SALDO;
			
			$o->trx = random_string("alnum", parent::MAX_N_TRX_WP);

			$o->idUserExternal = 0;
			$o->idApp = 0;
			$o->idPlan = 0;
			$o->idCountry = "CL";
			$o->urlOk = $post->UrlOk;
			$o->urlError = $post->UrlError;
			$o->urlNotify = $post->UrlNotify;
			$o->idCommerce = $oComm->idCommerce;
			$o->idPaymentType = $post->PaymentType;
			$o->codExternal = $post->CodExternal;
			$o->oldFlow = 0;
			$o->creationDate = date("Y-m-d H:i:s");

			log_message("debug", print_r($o, TRUE)); // log de parámetros iniciales
			
			// CREA NUEVA TRANSACCIÓN EN EL MOTOR
			$idTrx = $this->core_model->newTrx($o);
			if(is_null($idTrx)) throw new Exception("No se pudo iniciar la transacción en el sistema", 1003);
			
			// ----------------------------
			//          HotBilling
			// ----------------------------
			$xml =	'<request>'.
						'<transaction>{TRX_CLIENT}</transaction>'.
						'<user>'.
							'<login>{LOGIN_3G_MOTION}</login>'.
							'<pwd>{PASS_3G_MOTION}</pwd>'.
						'</user>'.
						'<ani>{ANI_PHONE}</ani>'.
						'<code>{CODE}</code>'.
					'</request>';

			// Reemplazo de valores para completar XML
			$xml = str_replace("{TRX_CLIENT}", $o->trx, $xml);
			$xml = str_replace("{LOGIN_3G_MOTION}", $post->Login3G, $xml);
			$xml = str_replace("{PASS_3G_MOTION}", $post->Pass3G, $xml);
			$xml = str_replace("{ANI_PHONE}", $post->Ani, $xml);
			$xml = str_replace("{CODE}", $post->Code, $xml);
			
			$ch = curl_init($this->config->item("ServiceHotBilling"));
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
			curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			
			$output = curl_exec($ch);
			curl_close($ch);
			$xmlResponse = simplexml_load_string($output);
		
			// Avanza estado transacción
			$this->core_model->updateStageTrx($idTrx, self::REQUEST_HOTBILLING_SALDO);

			if(empty($xmlResponse)) throw new Exception("No se pudo procesar la solicitud al servicio HotBilling", 1004);
			
			// Hay respuesta del servicio de Billing
			// Códigos de respuesta del servicio
			$codes = array(
				"0" => "Transacción realizada con éxito",
				"1" => "Error no definido",
				"3" => "Xml inválido",
				"4" => "Usuario o ip inválido",
				"5" => "Usuario no autorizado",
				"13" => "El cliente no tiene saldo suficiente para realizar la operación"
			);
			
			$code = (int)$xmlResponse->code;
			$desc = (string)$xmlResponse->description;
			$transaction = (string)$xmlResponse->transaction;
			
			// Guarda respuesta en tabla de HotBilling
			$bdHB = new stdClass();
			$bdHB->idTrx = $idTrx;
			$bdHB->code = $code;
			$bdHB->description = $codes[$bdHB->code];
			$bdHB->transaction = $transaction;
			$bdHB->creationDate = date("Y-m-d H:i:s");
			
			$this->hotbilling_model->initTrx($bdHB);
			
			// Si hay error
			if((int)$code == 0) {
				
				$this->core_model->updateStageTrx($idTrx, self::OK_REQUEST_HOTBILLING_SALDO);
				$salida->message = "Cobro realizado satisfactoriamente";
				
			} else {
				$this->core_model->updateStageTrx($idTrx, self::FAILED_REQUEST_HOTBILLING_SALDO);
				throw new Exception("Error en Services/Billing -> " . $bdHB->description, 1005);
			}

			
		} catch(Exception $e) {
			log_message("error", __METHOD__ . " -> " . $e->getMessage());
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
		}
		
		// Salida JSON como respuesta
		echo json_encode($salida);
		
	}
	
}
