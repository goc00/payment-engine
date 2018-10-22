<?php
require_once('webpay-sdk-php/libwebpay/webpay.php');	// SDK

class Webpaypluslib {
	
	private $webpay;
	private $folderDefault = "DEFAULT";
	
	function __construct() {
		$this->context =& get_instance();
		$this->config = $this->context->config; // para instacia de config
		
		$this->setConfiguration(
			$this->config->item("WPPCommerceCode"),
			str_replace("{COMM}", $this->folderDefault, $this->config->item("WebpayPrivateKeyWPP")),
			str_replace("{COMM}", $this->folderDefault, $this->config->item("WebpayCertFileWPP")),
			str_replace("{COMM}", $this->folderDefault, $this->config->item("WebpayCertServerWPP"))
		);
	}
	
	/**
	 * Permite cambiar la configuración del objeto con valores propios del comercio
	 */
	public function setConfiguration($commerceCode, $privateKey, $certCommerce, $certWebpay) {
		
		$configuration = new configuration();     
		$configuration->setEnvironment($this->config->item("WebpayEnvironment"));
		$configuration->setCommerceCode($commerceCode);
		
		$configuration->setPrivateKey(file_get_contents($privateKey));
		$configuration->setPublicCert(file_get_contents($certCommerce));
		$configuration->setWebpayCert(file_get_contents($certWebpay));
  
		$this->webpay = new Webpay($configuration);
		
	}
	
	/**
	 * Inicializa variables de configuración para el objeto webpay
	 */
	/*private function _init() {
		
		$privateKey = file_get_contents($this->config->item("WebpayPrivateKeyWPP"));
		$publicCert = file_get_contents($this->config->item("WebpayCertFileWPP"));
		$webpayCert = file_get_contents($this->config->item("WebpayCertServerWPP"));
		
		$configuration = new configuration();     
		$configuration->setEnvironment($this->config->item("WebpayEnvironment"));
		$configuration->setCommerceCode($this->config->item("WPPCommerceCode"));
		$configuration->setPrivateKey($privateKey);
		$configuration->setPublicCert($publicCert);
		$configuration->setWebpayCert($webpayCert);
  
		$this->webpay = new Webpay($configuration);
	}*/
	
	
	/**
	 * initTransaction
	 * Realiza el pago en Transbank (tarjeta de crédito o redcompra)
	 *
	 * @return WsInitTransactionOutput
	 */
	public function initTransaction($amount, $buyOrder, $sessionId , $urlReturn, $urlFinal) {
		return $this->webpay->getNormalTransaction()->initTransaction($amount, $buyOrder, $sessionId , $urlReturn, $urlFinal);
	}

	
	/**
	 * getTransactionResult
	 * Según documentación de Transbank, si se genera una excepción, se considera la
	 * transacción como anulada.
	 *
	  * @return transactionResultOutput
	 */
	public function getTransactionResult($token) {
		return $this->webpay->getNormalTransaction()->getTransactionResult($token);
	}
	
	
	/**
	 * acknowledgeTransaction
	 * Confirma la transacción con Transbank (si no se invoca, la transacción será reversada automáticamente)
	 *
	  * @return acknowledgeTransactionResponse
	 */
	public function acknowledgeTransaction($tokenInput) {
		return $this->webpay->getNormalTransaction()->acknowledgeTransaction($tokenInput);
	}
	
}
?>