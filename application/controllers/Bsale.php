<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bsale extends MY_Controller {
	
	public function __construct() {
		parent::__construct();
		$this->load->model('core_model', '', TRUE);
		$this->load->model('webpay_model', '', TRUE);
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
			echo "<pre>";
			print_r($docType);
			echo "</pre>";
			exit;*/
			
			
			
			/*$paymentType = $this->_doAction($this->config->item("BSaleServicePaymentType"));	// tipo de pagos
			echo "<pre>";
			print_r($paymentType);
			echo "</pre>";
			exit;*/
			
			
			$idTrx = $this->input->post("generating_trx");
			
			// Verifica que la trx haya sido recibida y exista en el sistema
			if(empty($idTrx)) throw new Exception("No se ha recibido ninguna transacción a procesar");
			
			$oTrx = $this->core_model->getTrxById($idTrx);
			if(is_null($oTrx)) throw new Exception("La transacción $idTrx no existe en el sistema");
			
			$oPaymentType = $this->core_model->getCommById($oTrx->idCommerce);
			if(is_null($oPaymentType)) throw new Exception("No se ha podido identificar el comercio asociado a la transacción");
		
	
			//$officeId = $this->_doAction($this->config->item("BSaleServiceOffice"));		// sucursal
			//$priceListId = $this->_doAction($this->config->item("BSaleServicePriceList"));	// lista de precios
			//$taxes = $this->_doAction($this->config->item("BSaleServiceTaxes"));			// impuestos
			
			//if(is_null($docType)) throw new Exception("No se pudo obtener la lista de tipos de documento", 1000); 
			//if(is_null($officeId)) throw new Exception("No se pudo obtener la lista de sucursales", 1000); 
			//if(is_null($priceListId)) throw new Exception("No se pudo obtener la lista de precios", 1000);
			//if(is_null($taxes)) throw new Exception("No se pudo obtener la lista de impuestos", 1000);
			
			// *******************************
			// Objeto para ser enviado a BSale
			// *******************************
			$o = new stdClass();
			$o->documentTypeId = $this->config->item("BSaleDocumentType");
			$o->officeId = $this->config->item("BSaleOffice");
			$o->priceListId = $this->config->item("BSalePriceList");
			$o->emissionDate = gmdate("U", time()); // debe ser en GMT (integer)
			//$nextWeek = time() + (7 * 24 * 60 * 60);
			//$o->expirationDate = gmdate("U", $nextWeek); // ?????????
			$o->expirationDate = $o->emissionDate;
			
			$details = new stdClass();
			// El monto debe ser enviado como neto
			$details->netUnitValue = round($oTrx->amount / 1.19);
			$details->quantity = 1;
			$details->taxId = "[".$this->config->item("BSaleTax")."]";
			$details->comment = "Suscripción Mensual ".$oPaymentType->name;
			$details->discount = 0;
			
			$payments = new stdClass();
			$payments->paymentTypeId = $this->config->item("BSalePaymentType");
			$payments->amount = $oTrx->amount;
			$payments->recordDate = $o->emissionDate;
			
			$o->details = array($details);
			$o->payments = array($payments);
			$o->declareSii = 1; // declarar a SII automáticamente

			$ticket = $this->_doAction($this->config->item("BSaleServiceTicket"),
										"POST",
										$o);
			
			// Procesa respuesta de BSale
			if(empty($ticket)) throw new Exception("Falló la respuesta de BSale al intentar generar boleta para la transacción $idTrx");
			if(!isset($ticket->id)) throw new Exception("BSale no ha podido la generar boleta para la transacción $idTrx");
			
			// Todo OK, así que registra respuesta
			// Elimina atributos que no necesito para la inserción y agrega los últimos
			$attrs = array("href",
							"document_type",
							"office",
							"user",
							"references",
							"document_taxes",
							"details",
							"sellers"
						);
			
			$l = count($attrs);
			//print_r($ticket); exit;
			for($i=0;$i<$l;$i++) unset($ticket->$attrs[$i]);
			
			$ticket->sent = 0;
			$ticket->creationDate = date("Y-m-d H:i:s");
			$ticket->idTrx = $idTrx;
			
			// Genera transacción de inserción
			$this->core_model->inicioTrx();
			
			if(is_null($this->core_model->newSaleTicket($ticket))) {
				$this->core_model->rollbackTrx();
				throw new Exception("La boleta fue creada satisfactoriamente pero no se pudo registrar en la base de datos");
			}
			
			if(!$this->core_model->updateStageTrx($idTrx, parent::BOLETA_GENERADA_PATPASS)) {
				$this->core_model->rollbackTrx();
				throw new Exception("No se pudo actualizar el estado de la transacción");
			}
			
			$this->core_model->commitTrx();
			echo "Boleta <b>".$ticket->number."</b> creada satisfactoriamente";
			echo "<br /><a href='".base_url()."admin/listTrxs'>Volver a Listado de Transacciones</a>";
			 
		} catch(Exception $e) {
			echo "No se pudo procesar la solicitud: " . $e->getMessage();
		}
	
	}
	
	
	
	/**
	 * Envía boleta al usuario
	 */
	public function sendSaleTicket() {
		
		try  {
			
			$idTrxBsale = $this->input->post("sending_trx");
			
			$oTrxBsale = $this->core_model->getTrxBSaleByIdTrxBSale($idTrxBsale);
			if(is_null($oTrxBsale)) throw new Exception("No hay información disponible para la boleta");
			
			// Busca información en tablas correspondientes
			$oTrxPatPass = $this->webpay_model->getTrxByIdTrx($oTrxBsale->idTrx);
			if(is_null($oTrxPatPass)) throw new Exception("No se ha podido obtener la información de la suscripción para la trx ".$oTrxBsale->idTrx);
			
			// Envío de email a usuario
			$this->load->library('email');
			
			$this->email->initialize(array(
			  'protocol' => 'smtp',
			  'smtp_host' => 'ssl://smtp.gmail.com',
			  'smtp_user' => 'feedreports2@3gmotion.com',
			  'smtp_pass' => '123456=ABC',
			  'smtp_port' => 465,
			  'crlf' => "\r\n",
			  'newline' => "\r\n",
			  '_smtp_auth' => TRUE
			));
			
			$this->email->from('feedreports2@3gmotion.com', 'TuPase.cl');
			$this->email->to($oTrxPatPass->cardHolderMail);
			$this->email->subject('Boleta suscripción TuPase.cl');
			$this->email->message('Boleta suscripción TuPase.cl');
			$this->email->attach($oTrxBsale->urlPdf);
			
			// Enviar email
			if($this->email->send()) {
				
				// Se actualiza a enviado para no reenviar correo
				$this->core_model->markAsSent($idTrxBsale);
				echo 'Correo enviado satisfactoriamente.<br /><a href="'.base_url().'admin/listTrxs">Volver a listado de transacciones</a>';
				
			} else {
				// Error
				print_r($this->email->print_debugger(array('headers')));
			}
			 
		} catch(Exception $e) {
			echo "No se pudo procesar la solicitud: " . $e->getMessage();
		}
	
	}
	
}
