<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'third_party/lib/Braintree.php');

/*
 *  Braintree_lib
 *  This is a codeigniter wrapper around the braintree sdk, any new sdk can be wrapped around here
 *  License: MIT to accomodate braintree open source sdk license (BSD)
 *  Author: Clint Canada
 *  Library tests (and parameters for lower Braintree functions) are found in:
 *  https://github.com/braintree/braintree_php/tree/master/tests/integration
 */

/**
    General Usage:
        In Codeigniter controller
        function __construct(){
            parent::__construct();
            $this->load->library("braintree_lib");
        }

        function <function>{
            $token = $this->braintree_lib->create_client_token();
            $data['client_token'] = $token;
            $this->load->view('myview',$data);
        }

        In View section
        <script src="https://js.braintreegateway.com/v2/braintree.js"></script>
        <script>
              braintree.setup("<?php echo $client_token;?>", "<integration>", options);
        </script>

    For more information on javascript client: 
    https://developers.braintreepayments.com/javascript+php/sdk/client/setup
 */

class Braintreelib {

	function __construct() {

        // We will load the configuration for braintree
        $CI = &get_instance();

        // Let us load the configurations for the braintree library
        Braintree_Configuration::environment($CI->config->item('BraintreeEnvironment'));
		Braintree_Configuration::merchantId($CI->config->item('BraintreeMerchantId'));
		Braintree_Configuration::publicKey($CI->config->item('BraintreePublicKey'));
		Braintree_Configuration::privateKey($CI->config->item('BraintreePrivateKey'));
    }

    // This function simply creates a client token for the javascript sdk
    function createClientToken(){
    	$clientToken = Braintree_ClientToken::generate();
    	return $clientToken;
    }
	
	// Genera transacción con tarjeta de crédito
	function sale($amount, $nonce) {
		
		$result = Braintree_Transaction::sale([
		  'amount' => $amount,
		  'paymentMethodNonce' => $nonce,
		  'options' => [
			'submitForSettlement' => true
		  ]
		]);
		
		return $result;
		
	}
	
}