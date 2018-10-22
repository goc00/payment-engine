<?php

class MY_Controller extends CI_Controller 
{	
	// Core
	const CLP						= 1;
	const USD						= 2;
	const NUEVA_TRX					= 1;
	
	// Tipos de Pago
	const ID_WEBPAY					= 1;
	const ID_OTC					= 2;
	const ID_OPERATOR				= 3;
	const ID_PAYPAL					= 4;
	const ID_WEBPAY_PLUS			= 5;
	const ID_BRAINTREE				= 6;
	
	// PatPass
	const MAX_N_TRX_WP				= 8;						// Caracteres máximos para la generación de trx en wp
	const TRX_OK					= 1;
	const NUEVA_TRX_WP				= 2;						// ID nueva transacción WP (PatPass)
	const FALLO_INIT_TRX_WP			= 3;
	const POST_TOKEN_WP				= 4;
	const RETORNA_TOKEN_WP			= 5;
	const FALLO_GETTRXRES_WP		= 6;
	const VALIDA_CON_TRX_WP			= 7;
	const OK_NO_RESP_NOTIFY			= 8;
	const OK_ALL					= 9;						// Proceso OK para PatPass
	const ANULADO_TRX_WP			= 13;
	const BOLETA_GENERADA_PATPASS	= 19;
	const BOLETA_ENVIADA_PATPASS	= 20;
	const EXP_DATE_WP				= "2036-12-31";				// Fecha de expiración para contratos PatPass
	
	// Operator
	const OK_OPE					= 10;
	const ERR_OPE					= 11;
	
	// PayPal
	
	// WebPay Normal
	const NEW_TRX_WPP				= 18;
	
	// BrainTree
	const NEW_TRX_BT				= 21;
	const PROCESSING_PAYLOAD		= 22;
	
    public function __construct() 
    {
		parent::__construct();
		//date_default_timezone_set('America/Santiago');
		
		/* Controla el acceso a los controllers (core) del motor, limitando
		cualquier petición que no provenga desde la API pública. */
			
		// Obtengo controller invocado
		$host = $_SERVER['REMOTE_ADDR'];
		///$archivo = file_put_contents("/home/bitnami/htdocs/pe3g/assets/request_logs.txt", "Accediendo desde: ".$host.PHP_EOL, FILE_APPEND);
		$controller = $this->uri->segment(1);
		//if(!in_array($host, $this->config->item("WhiteListIps")) && strtolower($controller) != "api") {
		//if(!in_array($host, $this->config->item("WhiteListIps"))) {
		/*if(strtolower($controller) != "api") {
			if(!in_array($host, $this->config->item("WhiteListIps"))) {
				// Si no está en la lista blanca, genera un 403
				header('HTTP/1.0 403 Forbidden');
				echo 'You are forbidden!';
				exit;
			}	
		}*/
	}
	
	
	/**
	 * Consumo de servicios externos (apunta a los mismos controladores)
	 */
    protected function _doPost($service, $arr, $return = TRUE) 
    {
		$curl = curl_init($service);
		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, $return);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $arr);
		
		$exec = curl_exec($curl);
		curl_close($curl);
		
		if($return) {
			
			$salida = new stdClass();
			$salida->code = -1;
			$salida->message = "";
			$salida->result = NULL;
	
			try {
				
				if(empty($exec)) throw new Exception("No se obtuvo ninguna respuesta desde el servicio", 1000);

				$obj = json_decode($exec);
				$salida->code = $obj->code;
				$salida->message = $obj->message;
				$salida->result = $obj->result;
				if(isset($obj->paymentForm)) $salida->paymentForm = $obj->paymentForm;
				
			} catch(Exception $e) {
				$salida->code = $e->getCode();
				$salida->message = $e->getMessage();
			}

			return $salida;
		}
		
		return;
	}
	
	/**
	 * Notifica al notify del 3rd party (quien se está integrando al motor de pagos)
	 * Permite además enviarle los parámetros extras que sean a través de objeto
	 *
	 * @access private
	 * @return void
	 */
    protected function _notify3rdParty($urlNotify, $result, $codExternal, $message, $obj = NULL)
    {
		// **************************************************
		// NOTIFICA AL COMERCIO A TRAVÉS DE URL PROPORCIONADA
		// **************************************************
		$oNotify = new stdClass();
		$oNotify->result = $result;
		$oNotify->codExternal = $codExternal;
		$oNotify->message = $message;
		if(!is_null($obj)) {
			foreach($obj as $key => $value) {
				$oNotify->$key = $value;
			}
		}

		$curl = curl_init($urlNotify);
		
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json'
		);

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($oNotify));
		
		$oNotifyRes = curl_exec($curl);
		curl_close($curl);
		
		return $oNotifyRes;
	}	
}