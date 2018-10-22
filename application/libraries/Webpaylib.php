<?php
require_once('wss/soap-wsse.php');
require_once('wss/soap-validation.php');
require_once('webpayservice.php');

class Webpaylib {
	
	private $isWebpayNormal = FALSE; // controla si es PatPass o Webpay normal
	
	private $wSTransactionType;
	private $returnURL; // notify
	private $finalURL;
	private $commerceCode;
	private $webpayPrivateKey;
	private $webpayCertFile;
	private $webpayCertServer;
	
	function __construct() {
		$this->context =& get_instance();
		$this->config = $this->context->config;
	}
	
	/**
	 * Setea las variables respecto al tipo de integración
	 * PatPass o Webpay normal
	 */
	private function initWP() {
		if(!$this->isWebpayNormal) {
			// PatPass
			$this->wSTransactionType = $this->config->item("WSTransactionType");
			$this->returnURL = base_url().$this->config->item("UrlNotify");
			$this->finalURL = base_url().$this->config->item("UrlVoucher");
			$this->commerceCode = $this->config->item("CommerceCode");
			$this->webpayPrivateKey = $this->config->item("WebpayPrivateKey");
			$this->webpayCertFile = $this->config->item("WebpayCertFile");
			$this->webpayCertServer = $this->config->item("WebpayCertServer");
		} else {
			// Webpay normal
			$this->wSTransactionType = $this->config->item("WPPTransactionType");
			$this->returnURL = base_url().$this->config->item("UrlWPPNotify");
			$this->finalURL = base_url().$this->config->item("UrlWPPVoucher");
			$this->commerceCode = $this->config->item("WPPCommerceCode");
			$this->webpayPrivateKey = $this->config->item("WebpayPrivateKeyWPP");
			$this->webpayCertFile = $this->config->item("WebpayCertFileWPP");
			$this->webpayCertServer = $this->config->item("WebpayCertServerWPP");
		}
	}
	public function setIsWebpayNormal($value) { $this->isWebpayNormal = $value; }
	
	/**
	 * @desc Método que inicia la transacción. Se definen los objetos necesarios y
	 * estipulados en la documentación de Transbank.
	 * Al invocar initTransaction, se genera un token que representa de forma única una transacción (trx)
	 * 
	 * @return	bool	Si se ejecutó o no la acción initTransaction 
	*/
	public function initTransaction($o) {
		
		$this->initWP();
		
		log_message('debug', 'Inicio de llamada a método initTransaction');

		$wsInitTransactionInput = new wsInitTransactionInput();
		$wsTransactionDetail = new wsTransactionDetail();
		$wpmDetailInput = new wpmDetailInput();
		
		$wsInitTransactionInput->wSTransactionType = $this->wSTransactionType;
		$wsInitTransactionInput->sessionId         = $o->sessionId;
		$wsInitTransactionInput->returnURL         = $this->returnURL; // Notify
		$wsInitTransactionInput->finalURL          = $this->finalURL; // Voucher (evidencia trx)
		
		$wsTransactionDetail->amount               = $o->amount;
		$wsTransactionDetail->buyOrder             = $o->buyOrder;
		$wsTransactionDetail->commerceCode         = $this->commerceCode;
		
		// Solo para PatPass
		if(!$this->isWebpayNormal) {
			$wpmDetailInput->serviceId                 = $o->serviceId;
			$wpmDetailInput->cardHolderId              = $o->cardHolderId;
			$wpmDetailInput->cardHolderName            = $o->cardHolderName;
			$wpmDetailInput->cardHolderLastName1       = $o->cardHolderLastName1;
			$wpmDetailInput->cardHolderLastName2       = $o->cardHolderLastName2;
			$wpmDetailInput->cardHolderMail            = $o->cardHolderMail;
			$wpmDetailInput->cellPhoneNumber           = $o->cellPhoneNumber;
			$wpmDetailInput->expirationDate            = $o->expirationDate;
			$wpmDetailInput->commerceMail              = $this->config->item("CommerceEmail");
			$wpmDetailInput->ufFlag                     = FALSE;
			$wsInitTransactionInput->wPMDetail          = $wpmDetailInput;
		}
		
		
		$wsInitTransactionInput->transactionDetails = $wsTransactionDetail;
		
		// Llamado a servicio webpay
		$webpayService = new WebpayService(		
											$this->config->item("WebpayServicePath"),
											$this->webpayPrivateKey,
											$this->webpayCertFile
										);
		
		log_message("debug", "initTransaction (REQUEST) -> ".print_r($wsInitTransactionInput, TRUE));
		$initTransactionResponse = $webpayService->initTransaction(array("wsInitTransactionInput" => $wsInitTransactionInput));
		
		// Verifica que no haya error en la petición
		if($initTransactionResponse->errNumber == 0) {
			$xmlResponse = $webpayService->soapClient->__getLastResponse();
			$soapValidation = new SoapValidation($xmlResponse, $this->webpayCertServer);
			$validationResult = $soapValidation->getValidationResult();
			log_message("debug", "validationResult -> ".$validationResult);
			if($validationResult) {
				/*Invocar sólo sí $validationResult es TRUE*/
				$wsInitTransactionOutput = $initTransactionResponse->obj->return;
				log_message("debug", "initTransaction (RESPONSE) -> ".print_r($wsInitTransactionOutput, TRUE));
				return $wsInitTransactionOutput;
			} else {
				$this->_writeMessage('error', 'No se pudo validar el resultado de initTransaction (CERT_SERVER)');
			}
			
		} else {
			// Registra error
			$this->_writeMessage('error', $initTransactionResponse->errMessage);
		}
		
		return NULL;
		
		
	}
	
	/**
	 * Según documentación de Transbank, si se genera una excepción, se considera la
	 * transacción como anulada.
	 */
	public function getTransactionResultWp($o) {
		
		$this->initWP();
		
		$webpayService = new WebpayService(
											$this->config->item("WebpayServicePath"),
											$this->webpayPrivateKey,
											$this->webpayCertFile
										);
		
		$getTransactionResult = new getTransactionResult();
		$getTransactionResult->tokenInput = $o->token_ws;
		
		log_message("debug", "getTransactionResult (REQUEST) -> ".print_r($getTransactionResult, TRUE));
		try {
			
			$getTransactionResultResponse = $webpayService->getTransactionResult($getTransactionResult);
			
			log_message("debug", "getTransactionResult (RESPONSE) -> ".print_r($getTransactionResultResponse, TRUE));
		
			$xmlResponse = $webpayService->soapClient->__getLastResponse();
			$soapValidation = new SoapValidation($xmlResponse, $this->webpayCertServer);
			$validationResult = $soapValidation->getValidationResult();
			
			if(!$validationResult) {
				$this->_writeMessage('error', 'No se pudo validar el resultado de getTransactionResult (CERT_SERVER)');
				return NULL;
			}
			
		} catch(Exception $e) {
			log_message("debug", "getTransactionResult (RESPONSE) -> Se ha originado una excepción en getTransactionResult()");
			$getTransactionResultResponse = new stdClass();
			$getTransactionResultResponse->anulado = TRUE;
			$getTransactionResultResponse->return = "";
		}
		
		return $getTransactionResultResponse;
	}
	
	
	public function acknowledgeTransactionWp($o) {
		
		$this->initWP();
		
		$webpayService = new WebpayService(
											$this->config->item("WebpayServicePath"),
											$this->webpayPrivateKey,
											$this->webpayCertFile
										);
										
		$acknowledgeTransaction = new acknowledgeTransaction();
		$acknowledgeTransaction->tokenInput = $o->token_ws;
		
		log_message("debug", "acknowledgeTransaction (REQUEST) -> ".print_r($acknowledgeTransaction, TRUE));
		$acknowledgeTransactionResponse = $webpayService->acknowledgeTransaction($acknowledgeTransaction);
		log_message("debug", "acknowledgeTransaction (RESPONSE) -> ".print_r($acknowledgeTransactionResponse, TRUE));
		
		$xmlResponse = $webpayService->soapClient->__getLastResponse();
		$soapValidation = new SoapValidation($xmlResponse, $this->webpayCertServer);
		$validationResult = $soapValidation->getValidationResult();
		
		if(!$validationResult) {
			$this->_writeMessage('error', 'No se pudo validar el resultado de acknowledgeTransaction (CERT_SERVER)');
			return NULL;
		}
		
		return $validationResult;
	}
	
	
	private function _writeMessage($level, $msg) {
		log_message($level, '(ENGINE) '.strtoupper($msg));
	}
	
}
?>