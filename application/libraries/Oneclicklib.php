<?php
require_once('webpay-sdk-php/libwebpay/webpay.php');	// SDK

class Oneclicklib {
	
	private $webpay;
	private $folderDefault = "DEFAULT";
	private $configX;
	
	function __construct() {
		$this->context =& get_instance();
		$this->configX = $this->context->config; // para instancia de config
		
		$this->setConfiguration(
			$this->configX->item("OneclickCommerceCode"),
			str_replace("{COMM}", $this->folderDefault, $this->configX->item("OneclickPrivateKey")),
			str_replace("{COMM}", $this->folderDefault, $this->configX->item("OneclickCertFile")),
			str_replace("{COMM}", $this->folderDefault, $this->configX->item("OneclickCertServer"))
		);
	}
	
	
	/**
	 * Permite cambiar la configuración del objeto con valores propios del comercio
	 */
	public function setConfiguration($commerceCode, $privateKey, $certCommerce, $certWebpay) {
		
		$configuration = new configuration();     
		$configuration->setEnvironment($this->configX->item("OneclickEnvironment"));
		$configuration->setCommerceCode($commerceCode);
		
		$configuration->setPrivateKey(file_get_contents($privateKey));
		$configuration->setPublicCert(file_get_contents($certCommerce));
		$configuration->setWebpayCert(file_get_contents($certWebpay));
		
		$this->webpay = new Webpay($configuration);
		
		log_message("debug", __METHOD__ . " CONF OBJECT > " . print_r($this->webpay, TRUE));
		
	}
	
	/**
	 * Inicializa variables de configuración para el objeto webpay
	 */
	/*private function _init() {
		
		$privateKey = file_get_contents($this->config->item("OneclickPrivateKey"));
		$publicCert = file_get_contents($this->config->item("OneclickCertFile"));
		$webpayCert = file_get_contents($this->config->item("OneclickCertServer"));
		
		$configuration = new configuration();     
		$configuration->setEnvironment($this->config->item("OneclickEnvironment"));			  
		$configuration->setCommerceCode($this->config->item("OneclickCommerceCode"));		  
		$configuration->setPrivateKey($privateKey);		  
		$configuration->setPublicCert($publicCert);			  
		$configuration->setWebpayCert($webpayCert);
  
		$this->webpay = new Webpay($configuration);
	}*/
	
	/**
	 * initTransaction
	 * Realiza la inscripción de una tarjeta de crédito.
	 *
	 * @return OneClickInscriptionOutput
	 */
	function initTransaction($userName, $email, $urlReturn) {
		log_message("debug", __METHOD__ . " REQUEST > " .$userName.", ".$email.", ".$urlReturn);
		$result = $this->webpay->getOneClickTransaction()->initInscription($userName, $email, $urlReturn);
		log_message("debug", __METHOD__ . " RESPONSE > " .print_r($result, TRUE));
		
		return $result;
	}
	
	/**
	 * finishInscription
	 * Permite finalizar el proceso de inscripción del tarjetahabiente.
	 *
	 * @return oneClickFinishInscriptionOutput
	 */
	function finishInscription($token) {
		log_message("debug", __METHOD__ . " REQUEST > " .$token);
		$result = $this->webpay->getOneClickTransaction()->finishInscription($token);
		log_message("debug", __METHOD__ . " RESPONSE > " .print_r($result, TRUE));
		
		return $result;
	}
	
	/**
	 * removeUser
	 * Permite eliminar una inscripción de usuario en Transbank.
	 *
	 * @return removeUserResponse
	 */
	function removeUser($tbkUser, $username) {
		$o = new stdClass();
		$o->tbkUser = $tbkUser;
		$o->username = $username;
		log_message("debug", __METHOD__ . " REQUEST > " . print_r($o, TRUE));
		$result = $this->webpay->getOneClickTransaction()->removeUser($tbkUser, $username);
		log_message("debug", __METHOD__ . " RESPONSE > " .print_r($result, TRUE));
		
		return $result;
	}
	
	/**
	 * authorize
	 * Autoriza una transacción.
	 *
	 * @return OneClickPayOutput
	 */
	function authorize($buyOrder, $tbkUser, $username, $amount) {
		log_message("debug", __METHOD__ . " REQUEST > " .$buyOrder.", ".$tbkUser.", ".$username.", ".$amount);
		
		//log_message("debug", __METHOD__ . " WEBPAY OBJECT > " .print_r($this->webpay->getOneClickTransaction(), TRUE));
		
		$result = $this->webpay->getOneClickTransaction()->authorize($buyOrder, $tbkUser, $username, $amount);
		//var_dump($this->webpay).PHP_EOL;
		/*echo "<pre>";
		var_dump($this->webpay->getOneClickTransaction());
		echo "</pre>";*/
		//var_dump($result);
		log_message("debug", __METHOD__ . " RESPONSE > " . print_r($result, TRUE));
		
		return $result;
	}
	
	/**
	 * reverse
	 * Reversa transacción
	 *
	 * @return oneClickReverseOutput
	 */
	function reverse($buyOrder) {
		$o = new stdClass();
		$o->buyOrder = $buyOrder;
		log_message("debug", __METHOD__ . " REQUEST > " . print_r($o, TRUE));
		$result = $this->webpay->getOneClickTransaction()->reverseTransaction($buyOrder);
		log_message("debug", __METHOD__ . " RESPONSE > " .print_r($result, TRUE));
		
		return $result;
	}
	
}
?>