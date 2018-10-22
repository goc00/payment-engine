<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class BSale extends CI_Controller {
	
	public function __construct() {
		parent::__construct();
		//$this->load->helper('string');
		$this->load->model('paypal_model', '', TRUE);
	}

	public function index() {
		echo "you, again... what are you looking for?";
	}
	
	
	/**
	 * Realiza las peticiones por cURL
	 */
	private function _doAction($service, $type = "GET", $params = NULL) {

		try {
			
			$access_token = $this->config->item("BSaleAccessToken");
		
			// Inicia cURL
			$session = curl_init($service);
			
			// Indica a cURL que retorne data
			curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);

			if($type == "POST") {
				
				// No viene parámetros
				if(is_null($params)) throw new Exception("No se ha definido ningún objeto para el POST", 1000);
				
				curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($session, CURLOPT_POST, TRUE);
				curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($params));
			}
			
			// Configura cabeceras
			$headers = array(
				'access_token: ' . $access_token,
				'Accept: application/json',
				'Content-Type: application/json'
			);
			curl_setopt($session, CURLOPT_HTTPHEADER, $headers);

			// Ejecuta cURL
			$response = curl_exec($session);
			if(empty($response)) throw new Exception("ERROR: " . curl_error($session));
			
			//print_r($response); exit;
			$info = curl_getinfo($session);
			$errCode = $info["http_code"];
			
			if($errCode != 200 && $errCode != 201) throw new Exception("ERROR: " . print_r($info, TRUE));
			
			// Cierra la sesión cURL
			curl_close($session);
		
			return json_decode($response);

		} catch(Exception $e) {
			log_message("error", $e->getMessage());
			return NULL;
		}

	}
	
	/**
	 * Obtiene los tipos de documento asociados a la cuenta
	 */
	public function makeSaleTicket() {
		
		try  {
			 
			/*$docType = $this->_doAction($this->config->item("BSaleServiceDocumentType"));	// tipo de documento
			$officeId = $this->_doAction($this->config->item("BSaleServiceOffice"));		// sucursal
			$priceListId = $this->_doAction($this->config->item("BSaleServicePriceList"));	// lista de precios
			$taxes = $this->_doAction($this->config->item("BSaleServiceTaxes"));			// impuestos
			
			if(is_null($docType)) throw new Exception("No se pudo obtener la lista de tipos de documento", 1000); 
			if(is_null($officeId)) throw new Exception("No se pudo obtener la lista de sucursales", 1000); 
			if(is_null($priceListId)) throw new Exception("No se pudo obtener la lista de precios", 1000);
			if(is_null($taxes)) throw new Exception("No se pudo obtener la lista de impuestos", 1000); */
			
			/*echo "<pre>";
			print_r($taxes);
			echo "</pre>"; exit;
			*/
			$o = new stdClass();
			$o->documentTypeId = $this->config->item("BSaleDocumentType");
			$o->officeId = $this->config->item("BSaleOffice");
			$o->priceListId = $this->config->item("BSalePriceList");
			$o->emissionDate = time();
			$o->expirationDate = time("+ 1 day");
			
			$details = new stdClass();
			$details->netUnitValue = 100;
			$details->quantity = 1;
			$details->taxId = "[".$this->config->item("BSaleTax")."]";
			$details->comment = "Mensaje de prueba";
			$details->discount = 0;
			
			$o->details = array($details);
			$o->declareSii = 1;
			
			/*echo "<pre>";
			print_r(json_encode($o));
			echo "</pre>";
			exit;*/
			
			$ticket = $this->_doAction($this->config->item("BSaleServiceTicket"),
										"POST",
										$o);
			
			echo "<pre>";
			print_r($ticket);
			echo "</pre>";
			 
		} catch(Exception $e) {
			echo "No se pudo procesar la solicitud: " . $e->getMessage();
		}
		
		
		
	}
	

}
