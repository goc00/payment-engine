<?php
require_once("wp/wp_classes.php");
require_once("mysoap.php");

class WebpayService
{
    var $soapClient;
    
    private static $classmap = array('getTransactionResult' => 'getTransactionResult', 'getTransactionResultResponse' => 'getTransactionResultResponse', 'transactionResultOutput' => 'transactionResultOutput', 'cardDetail' => 'cardDetail', 'wsTransactionDetailOutput' => 'wsTransactionDetailOutput', 'wsTransactionDetail' => 'wsTransactionDetail', 'acknowledgeTransaction' => 'acknowledgeTransaction', 'acknowledgeTransactionResponse' => 'acknowledgeTransactionResponse', 'initTransaction' => 'initTransaction', 'wsInitTransactionInput' => 'wsInitTransactionInput', 'wpmDetailInput' => 'wpmDetailInput', 'initTransactionResponse' => 'initTransactionResponse', 'wsInitTransactionOutput' => 'wsInitTransactionOutput');
    
    function __construct($url,
						$privateKey,
						$certFile
						) {
		$this->soapClient = new MySoap($url, array("classmap" => self::$classmap, "trace" => true, "exceptions" => true));
		$this->soapClient->privateKey = $privateKey;
		$this->soapClient->certFile = $certFile;
    }
    
    function getTransactionResult($getTransactionResult) {
        $getTransactionResultResponse = $this->soapClient->getTransactionResult($getTransactionResult);
        return $getTransactionResultResponse;
    }
    function acknowledgeTransaction($acknowledgeTransaction) {
        $acknowledgeTransactionResponse = $this->soapClient->acknowledgeTransaction($acknowledgeTransaction);
        return $acknowledgeTransactionResponse;
        
    }
	
	/**
	 * Método invocador de servicio initTransaction de Transbank
	*/
    function initTransaction($initTransaction) {
		
		$result = new stdClass();
		$result->errNumber = 0;
		$result->errMessage = "";
		$result->obj = null;
		
        try {
			
			$result->obj = $this->soapClient->initTransaction($initTransaction); // initTransactionResponse
			
		} catch(Exception $e) {
			$result->errNumber = 1000;
			$result->errMessage = $this->soapClient->__getLastResponse();
		}
		
        return $result;
        
    }
	
}


?>
