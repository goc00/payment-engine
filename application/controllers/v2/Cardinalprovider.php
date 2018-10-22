<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cardinalprovider extends MY_Controller {

  // Responses
  const RESPONSE_OK         = 1; // Transaction approved
  const RESPONSE_DECLINED   = 2; // Transaction declined
  const RESPONSE_ERROR      = 3; // Error in transaction data or system error

  // Response code
  const RESPONSE_CODE_OK    = 100;

  // CVV2
  const CVV2_NO_MATCH       = "N";

  
  // Constructor
  function __construct() {
    parent::__construct();

    $this->load->helper('crypto');

    $this->load->library('sanitize');
    $this->load->library('encryption');

    $this->load->model("core_model", "", true);
    $this->load->model("transactionv2_model", "", true);
    $this->load->model("cardinal_model", "", true);
  }

  /**
   * Start new transaction with Cardinal Gateway
   *
   * @return json: cardinal_response
   */
  public function startTrx() {

    try {

      $post = $this->sanitize->inputParams(true, true, false);
      $this->sanitize->generateLog(__METHOD__, "POST request: ". print_r($post, true));

      if (empty($post)) { throw new Exception("Parameters are not valid", 400); }
      
      // Find trx object
      $oTrx = $this->transactionv2_model->findByAttr('trx', $post->trx);
      if(empty($oTrx)) { throw new Exception("Transaction not found", 409); }

      // Get idTrx
      $idTrx = $oTrx->idTrx;

      $ccnumber = $post->ccnumber;
      $cvv = $post->cvv;
      $ccexp = $post->ccexp;
      $firstname = $post->firstname;
      $lastname = $post->lastname;
      $email = $post->email;

      $amount = number_format($post->amount, 2, ".", "");
      //$email = "test@test.com";
      $trx = $oTrx->trx;

      $key = $this->config->item("CardinalKey");
      $securityKey = $this->config->item("CardinalSecurityKey");

      // Create hash
      $time = time();
      $hash = md5("$trx|$amount|$time|$securityKey");
     
      // POST to Cardinal
      $parameters = array(

        // Required
        "orderid"               => $trx,
        "type"                  => $this->config->item("CardinalType"),
        "key_id"                => $key,
        "hash"                  => $hash,
        "time"                  => $time,

        "redirect"              => base_url("v2/Cardinalprovider/notify"),
        
        // Required**
        "ccnumber"              => $ccnumber,
        "cvv"                   => $cvv, // 999 OK
        "ccexp"                 => $ccexp, // MMYY
        "firstname"             => $firstname,
			  "lastname"              => $lastname,
        "checkname"             => "$firstname $lastname",
        //"checkaccount"          => $cc_fname,
        //"account_holder_type"   => $cc_fname, // The customer’s type of ACH account
        //"account_type"          => $cc_fname, // The customer’s type of ACH account
        "amount"                => $amount,

        // Recommended
        //"cvv"                   => $cvv,
        //"payment"               => "cc", // cc, check
        //"ipaddress"             => $ipaddress,
        //"firstname"             => $firstname,
        //"lastname"              => $lastname,
        //"address"               => "",
        "email"                 => $email

      );

      // Save transaction's variables, encrypting sensible data
      $bdParams = $parameters;
      
      $bdParams["idTrx"] = $idTrx;
      $bdParams["creationDate"] = date("Y-m-d H:i:s");
      $bdParams["ccnumber"] = encode_url($bdParams["ccnumber"]);
      $bdParams["cvv"] = encode_url($bdParams["cvv"]);
      $bdParams["ccexp"] = encode_url($bdParams["ccexp"]);

      $idCardinal = $this->cardinal_model->initTrx($bdParams);

      if(is_null($idCardinal)) {
        $this->core_model->updateStageTrx($idTrx, $this->config->item("cardinal_ORDER_FAILURE"));
        throw new Exception("Couldn't create Cardinal transaction", 400);
      }

      // Update stage
      $this->core_model->updateStageTrx($idTrx, $this->config->item("cardinal_POST_ORDER"));

      // Send POST to Cardinal gateway
      $this->_postToken($parameters);

    } catch(Exception $e) {
      $this->sanitize->generateLog(__METHOD__, $e->getMessage());
            
      $data = [
          'logo'      =>  false,
          'message'   => "El proceso ha terminado inesperadamente: " . $e->getMessage()
      ];

      $this->load->view('transaction/unexpected', $data);
    }

  }


  /**
   * Cardinal's response for GET (querystring)
   */
  public function notify() {

    $get = $this->input->get();

    $oTrx = NULL;
    $error = 0;
    $errorMsg = "";

    try {

      // Empty response
      if(empty($get)) { throw new Exception("Couldn't find transaction response", 400); }

      $get = (object)$get;

      // Find transaction
      $oTrx = $this->transactionv2_model->findByAttr('trx', $get->orderid);
      if(empty($oTrx)) {
        $error = $this->config->item("cardinal_TRX_NOT_FOUND");
        $errorMsg = "Transaction not found";
      }

      $idTrx = $oTrx->idTrx;
      $oCardinalTrx = $this->cardinal_model->findByAttr("idTrx", $idTrx); 
      //print_r($oCardinalTrx); exit;
      if(empty($oCardinalTrx)) { throw new Exception("Cardinal transaction (idTrx: $idTrx) not found", $this->config->item("cardinal_TRX_NOT_FOUND")); }

      $idCardinal = $oCardinalTrx->idCardinal;

      // At this point we can update info
      $hash = $get->hash;
      unset($get->hash);
      $get->hash_post = $hash;

      if(is_null($this->cardinal_model->updateTrx($idCardinal, $get))) {
        $error = $this->config->item("cardinal_NOTIFY_FAILURE");
        $errorMsg = "Couldn't update Cardinal transaction";
      }
      $oCardinalTrx = $this->cardinal_model->findByAttr("idTrx", $idTrx); 

      // Responses
      if($get->response != self::RESPONSE_OK) {
        $error = $this->config->item("cardinal_TRX_FAILED");
        $errorMsg = "Transaction failed by Cardinal gateway. " . $get->responsetext;
      }

      if($get->response_code != self::RESPONSE_CODE_OK) {
        $error = $this->config->item("cardinal_TRX_REJECTED");
        $errorMsg = "Transaction was rejected by Cardinal gateway. " . $get->responsetext;
      }

      // Minimal validations
      if((float)$get->amount != (float)$oTrx->amount) {
        $error =  $this->config->item("cardinal_TRX_BAD_PRICE");
        $errorMsg = "Transaction's price doesn't match. Process aborted";
      }

      if($get->cvvresponse == self::CVV2_NO_MATCH) {
        $error = $this->config->item("cardinal_TRX_BAD_CVV2");
        $errorMsg = "CVV2 doesn't match. Process aborted";
      }

      // If error is on, throw exception after update state
      if($error > 0) { throw new Exception($errorMsg, $error); }

      // Notify
      $this->_notify3rdParty(
        $oTrx->urlNotify,   // notify 3rd party (commerce)
        1,              // 0 NOK, 1 OK
        $oTrx->codExternal, // trx 3rd party (commerce)
        "OK"            // description
      );

      $oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
      /*echo "<pre>";
      print_r();
      echo "</pre>"; exit;*/
      $data = [
          "orderId"       => $oCardinalTrx->orderid,
          "authCode"      => $oCardinalTrx->authcode,
          "bgColor"       => !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault"),
          "fontColor"     => !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault"),
          "logo"          => !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL,
          "amount"        => '$'.number_format(floatval($oTrx->amount), 0, ',', '.'),
          "urlOk"         => $oTrx->urlOk,
          "creationDate"  => date("d/m/Y H:i:s", strtotime($oTrx->creationDate)),
          "status"        => $oCardinalTrx->responsetext,
          "commerceName"  => $oCommerce->name
      ];

      $this->load->view('cardinal/success', $data);

    } catch(Exception $e) {

      if(!is_null($oTrx)) {

        // Update error state in transaction
        $this->sanitize->generateLog(__METHOD__, $e->getMessage(), 'error');
        $this->core_model->updateStageTrx($oTrx->idTrx, $e->getCode());

        // Notify to commerce
        $this->_notify3rdParty(
          $oTrx->urlNotify,   // notify 3rd party (commerce)
          0,                  // 0 NOK, 1 OK
          $oTrx->codExternal, // trx 3rd party (commerce)
          $e->getMessage()    // description
        );
      }


      $pass = [
          'logo'      =>  false,
          'message'   => "No se pudo completar la transacción requerida: " . $e->getMessage()
      ];

      $this->load->view('transaction/unexpected', $pass);

    }

  }

  /**
 * Realiza una trx de cobro a Cardinal, parámetros van por GET
 *
 * @param string: cc_number, número de CC
 * @param string: cc_cvv, CVV de la CC
 * @param string: cc_exp, fecha de expiración en formato MMYY
 * @param string: cc_fname, primer nombre de la CC
 * @param string: cc_lname, apellido de la CC
 * @param numeric: amount, monto
 *
 * @return view: cardinal_response
 */
  public function process() {
    

  }
  
  
  // ---------------------- PRIVATE METHODS ----------------------------

  /**
	 * Consume initial transaction in order to turn on
	 */
	private function _postToken($data) {

		try {

      $data["url"] = $this->config->item("CardinalCredomaticEndpoint");
      $pass["parameters"] = $data;

			$this->load->view("cardinal/post_token", $pass);

		} catch(Exception $e) {
			$this->sanitize->generateLog(__METHOD__, $e->getMessage(), 'error');
            
      $pass = [
          'logo'      =>  false,
          'message'   => "El proceso ha terminado inesperadamente: " . $e->getMessage()
      ];

      $this->load->view('transaction/unexpected', $pass);
		}

	}
  
  
}
