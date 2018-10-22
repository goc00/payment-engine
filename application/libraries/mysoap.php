<?php
require_once('wss/xmlseclibs.php');
require_once('wss/soap-wsse.php');

class MySoap extends SoapClient {
	
	var $privateKey;
	var $certFile;
	
	function __construct($url, $arr) {
		parent::__construct($url, $arr);
	}

    function __doRequest($request, $location, $saction, $version, $one_way = 0) {
		
        $doc = new DOMDocument('1.0');
        $doc->loadXML($request);
        $objWSSE = new WSSESoap($doc);
        $objKey  = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array(
            'type' => 'private'
        ));
        $objKey->loadKey($this->privateKey, TRUE);
        $options = array(
            "insertBefore" => TRUE
        );
        $objWSSE->signSoapDoc($objKey, $options);
        $objWSSE->addIssuerSerial($this->certFile);
		
        $objKey = new XMLSecurityKey(XMLSecurityKey::AES256_CBC);
        $objKey->generateSessionKey();
		
        $retVal = parent::__doRequest($objWSSE->saveXML(), $location, $saction, $version);
		
        $doc    = new DOMDocument();
        $doc->loadXML($retVal);
		
        return $doc->saveXML();
    }
	
}

?>