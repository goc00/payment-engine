<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| --------------------
| Webpay-Engine v1.0
| --------------------
| Autor: Gastón Orellana
| Descripción: Opera todos los flujos para la implementación de PatPass by Webpay-Engine
| desde cualquier aplicación externa (3rd party). Apunta a ser consumido por cualquier producto.
| Fecha creación: 12/04/2016
*/

class Webpay extends CI_Controller {

	const ERR_VALIDACION	= 1000;
	const ERR_BD			= 1001;
	const ERR_UNKNOWN		= 1999;
	
	const ADMIN_USER		= "admin";
	const ADMIN_PASS		= "bbfacb9fc5ab1f72311d739b416345a7"; // 3g2016motion
	
	public function __construct() {
		parent::__construct();
		$this->load->helper('string');
		$this->load->model('webpay_model', '', TRUE);
		$this->load->library('webpaylib');
	}

	public function test() {
		$this->webpaylib->initTransaction();
	}
	
	/**
	 * Inicia el proceso de PatPass (si aplica), registrando u obteniendo respuesta
	 * desde Transbank. Todos los parámetros recibidos son por POST.
	 *
	 * Los parámetros obligatorios para Transbank corresponden a:
	 * (WE) = Lo procesa, genera y/o administra el Motor (Webpay-Engine)
	 *
	 * - WSTransactionType	:	(WE) Indica el tipo de transacción, su valor debe ser siempre TRX_NORMAL_WS_WPM (para PatPass)
	 * - returnURL 			:	(WE) URL del comercio a la cual Webpay redireccionará posterior al resultado de la autorización.
	 *							Es aquí donde el comercio deberá procesar el resultado de la autorización
	 * - finalURL 			:	(WE) URL del comercio a la cual Webpay redireccionará posterior al voucher de éxito del comercio.
	 *							Webpay enviará vía método POST la variable token_ws con el valor del token de transacción
	 * - transactionDetails:
	 *		Lista de objetos del tipo wsTransactionDetail, el cual contiene datos de la transacción asociada al primer pago
	 *		que se realizará en línea. Máxima cantidad de repeticiones es de 1 para este tipo de transacción.
	 *		-- amount		: Monto de la transacción.
	 *		-- buyOrder 	: (WE) Orden de compra de la tienda.
	 *		-- commerceCode : (WE) Código comercio de la tienda. (12 largo)
	 *		-- sharesAmount	: (Opcional, uso en cuotas comercio) Valor de la cuota
	 *		-- sharesNumber : (Opcional, uso en cuotas comercio)Número o cantidad de cuotas. 
	 * - wPMDetail: (Contiene los datos de una inscripción PATPASS BY WEBPAY)
	 *		Objeto del tipo wpmDetailInput, el cual contiene datos asociados a la inscripción de PatPass by Webpay
	 *		-- serviceId				: (WE) Identificador servicio, corresponde al código con el cual el comercio identifica el servicio prestado a su cliente. 
	 *		-- cardHolderId				: RUT del  tarjetahabiente. 
	 *									  Formato: NN.NNN.NNN-A 
	 *		-- cardHolderName			: Nombre tarjetahabiente. 
	 *		-- cardHolderLastName1		: Apellido paterno tarjetahabiente. 
	 *		-- cardHolderLastName2		: Apellido materno tarjetahabiente. 
	 *		-- cardHolderMail			: Correo electrónico tarjetahabiente. 
	 *		-- cellPhoneNumber			: Número teléfono celular tarjetahabiente.
	 *		-- expirationDate			: Fecha expiración de PatPass by Webpay, corresponde al último pago. Formato AAAA-MM-DD 
	 *		-- commerceMail				: (WE) Correo electrónico comercio. 
	 *		-- amount					: Monto fijo inscripción PatPass by Webpay
	 *		-- ufFlag					: (WE) Valor en true indica que el monto enviado está expresando en UF, valor en false indica que valor esta expresado en Pesos.
	 *
	 * Para efectos del WE, se requiere:
	 * - urlOk3rdParty		: URL de éxito del programa externo, a donde se retornará si el proceso culmina de manera satisfactoria
	 * - urlErr3rdParty		: URL de error del programa externo en caso de fallar la transacción
	 *
	 * @return	bool	Retorna éxito o no al producto
	*/
	public function initTrx() {
		
		$salida = new stdClass();
		$salida->err_number = 0;
		$salida->message = "";
		
		try {
			
			$oInitTrx = new stdClass(); // OBJETO que se pasará a librería (wsInitTransactionInput)
			
			// Recibe las URL encoded
			$urlOk3rdParty = trim($this->input->post("urlOk"));
			$urlErr3rdParty = trim($this->input->post("urlErr"));
			$urlNotify3rdParty = trim($this->input->post("urlNotify"));
			
			if($urlOk3rdParty == "" || $urlErr3rdParty == "") $this->_launchEx("No se han definido las rutas por parte del programa", self::ERR_VALIDACION);
			if(!$this->_isValidUrl($urlOk3rdParty)) $this->_launchEx("La ruta de éxito proporcionada no es válida", self::ERR_VALIDACION);
			if(!$this->_isValidUrl($urlOk3rdParty)) $this->_launchEx("La ruta de error proporcionada no es válida", self::ERR_VALIDACION);
			
			// wsTransactionDetail
			$oWsTransactionDetail = new stdClass();
			$oWsTransactionDetail->serviceId = trim($this->input->post("serviceId"));	// debe enviarlo el 3rd party, ej: 161536546542
			
			// Verifica la existencia de 3rd party a través de su código
			$oCommerceRegistered = $this->webpay_model->getCommerceRegistered($oWsTransactionDetail->serviceId);
			
			//$oInitTrx->
			$oInitTrx->wSTransactionType = $this->config->item("WSTransactionType");
			$oInitTrx->returnUrl = base_url().$this->config->item("UrlReturn");
			$oInitTrx->finalUrl = base_url().$this->config->item("UrlOk");
			
			
			
			$oInitTrx->amount = trim($this->input->post("amount"));
			$oInitTrx->buyOrder =
			$oInitTrx->commerceCode = $this->config->item("CommerceCode");
			
			// wpmDetailInput
			$oWpmDetailInput = new stdClass();
			$oInitTrx->serviceId = trim($this->input->post("serviceId"));	// debe enviarlo el 3rd party, ej: 161536546542
			$oInitTrx->cardHolderId = $this->config->item("cardHolderId");
			$oInitTrx->cardHolderName = $this->config->item("cardHolderName");
			$oInitTrx->cardHolderLastName1 = $this->config->item("cardHolderLastName1");
			$oInitTrx->cardHolderLastName2 = $this->config->item("cardHolderLastName2");
			$oInitTrx->cardHolderMail = $this->config->item("cardHolderMail");
			$oInitTrx->cellPhoneNumber = $this->config->item("cellPhoneNumber");
			$oInitTrx->expirationDate = $this->config->item("expirationDate");
			$oInitTrx->commerceMail = $this->config->item("CommerceEmail");
			//$oInitTrx->amount = $this->config->item("cardHolderId");
			$oInitTrx->ufFlag = FALSE;	// pesos
			
			
		
		} catch(Exception $e) {
			$salida->err_number = $e->getCode();
			$salida->message = $e->getMessage();
		}
		
		echo json_encode($salida);
		
	}
	
	
	
	/**
	 * Obtiene todas las transacciones SIN PROCESAR de WEBPAY
	 */
	public function listTrx() {
		
		$user = trim($this->input->post("user"));
		$pass = trim($this->input->post("pass"));
		
		try {
			
			// Ingreso a sección
			if($user != self::ADMIN_USER || md5($pass) != self::ADMIN_PASS) throw new Exception("El nombre de usuario y/o contraseña son inválidos", 1000);
			
		} catch(Exception $e) {
			log_message("error", $e->getMessage());
		}
			
	}
	
	
	// --------------------------------------------------------------------------------------------------------------------------

	private function _isValidUrl($url) {
		return (filter_var($url, FILTER_VALIDATE_URL) === FALSE) ? FALSE : TRUE;
	}
	private function _launchEx($msj, $num) {
		throw new Exception($msj, $num);
	}
	private function _generateStr($length) {
		return random_string("alnum", $length);
	}
}
