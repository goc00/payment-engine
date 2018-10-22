<?php defined('BASEPATH') OR exit('No direct script access allowed');

require (APPPATH.'third_party/PayU.php');

class Payuprovider extends MY_Controller {
  
  const CURRENCY                          = "COP";
  const CREDIT_CARD                       = "VISA,MASTERCARD,DINERS,AMEX";
  const APPROVED                          = 4;
  const REJECTED                          = 6;
  const EXPIRED                           = 5;

  // Constructor
  function __construct() {
    parent::__construct();

    $this->load->library('sanitize');

    $this->load->model("core_model", "", true);
    $this->load->model("transactionv2_model", "", true);
    $this->load->model("payu_model", "", true);
    $this->load->model("stage_model", "stage", true);

  }

  /**
   * PayU capture and authorization. Start transaction.
   *
   *
   * @return json: payu_response
   */
  public function startTrx() {

    try {
      
      $post = $this->sanitize->inputParams(true,true,false);
      $this->sanitize->generateLog(__METHOD__, "POST request: ". print_r($post, true));

      if (empty($post)) { throw new Exception("Parameters are not valid", 400); }
      
      // Find trx object
      $oTrx = $this->transactionv2_model->findByAttr('trx', $post->trx);
      if(empty($oTrx)) { throw new Exception("Transaction not found", 409); }

      // Get idTrx
      $idTrx = $oTrx->idTrx;
      $this->core_model->updateStageTrx($idTrx, $this->config->item("payu_NEW_ORDER")); // Update transaction stage

      // Create new order in PayU table
      $newPayU = [
        "idTrx"             => $post->idTrx,
        "buyerEmail"        => $post->buyerEmail,
        "creationDate"      => date("Y-m-d H:i:s")
      ];
     
      $idPayU = $this->payu_model->initTrx($newPayU);
      if(is_null($idPayU)) {
        $this->core_model->updateStageTrx($idTrx, $this->config->item("payu_ORDER_FAILURE"));
        throw new Exception("Couldn't create PayU transaction", 400);
      }

      // Create signature
      // 20-05-2018, GOC, Added support for hiding debit
      $signature = "";
      if(!$this->config->item("payu_only_creditcard")) {

        $signature = $this->config->item("payu_api_key")
                  ."~".$this->config->item("payu_merchant_id")
                  ."~".$post->trx
                  ."~".$post->amount
                  ."~".self::CURRENCY;

      } else {

        $signature = $this->config->item("payu_api_key")
                  ."~".$this->config->item("payu_merchant_id")
                  ."~".$post->trx
                  ."~".$post->amount
                  ."~".self::CURRENCY
                  ."~".self::CREDIT_CARD;

      }
      
      $this->sanitize->generateLog(__METHOD__, "Signature created: ". $signature);
      $parameters = [

        "merchantId"                          => $this->config->item("payu_merchant_id"),
        PayUParameters::ACCOUNT_ID            => $this->config->item("payu_account_id"),
        PayUParameters::DESCRIPTION           => "Pago PayU - Test",
        PayUParameters::REFERENCE_CODE        => $post->trx,
        "amount"                              => $post->amount,
        PayUParameters::TAX_VALUE             => 0, // En caso de no tener IVA debe enviarse en 0.
        PayUParameters::TAX_RETURN_BASE       => 0, // En caso de no tener IVA debe enviarse en 0.
        PayUParameters::CURRENCY              => self::CURRENCY,
        "signature"                           => md5($signature),
        "test"                                => $this->config->item("payu_istest") ? 1 : 0,
        PayUParameters::BUYER_EMAIL           => $post->buyerEmail,
        PayuParameters::RESPONSE_URL          => base_url("v2/Payuprovider/voucher"),
        "confirmationUrl"                     => base_url("v2/Payuprovider/notify"),

        "url"                                 => $this->config->item("payu_payment_url")
        
      ];

      if($this->config->item("payu_only_creditcard")) {
        $parameters["paymentMethods"] = self::CREDIT_CARD; 
      }

      $this->sanitize->generateLog(__METHOD__, "Parameters sent: ". print_r($parameters, true));

      // POST to PayU Gateway
      $updPayU = [
        "signature" => $parameters["signature"]
      ];
      $this->payu_model->updateTrx($idPayU, (object)$updPayU);
      $this->core_model->updateStageTrx($idTrx, $this->config->item("payu_POST_ORDER")); // Update transaction stage
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
   * Receive notification from PayU, in order to complete the transaction/payment
   *
   * @return void
   */
  public function notify() {

    $oTrx = null;

    try {

      $post = $this->input->post(NULL, TRUE); // returns all POST items with XSS filter
      log_message("debug", print_r($post, TRUE));
      if(empty($post)) { throw new Exception("No response was detected in the PayU's notification process", $this->config->item("payu_NOTIFY_NO_RESPONSE")); }

      // Find trx with reference_sale returned
      $post = (object)$post;
      $trx = $post->reference_sale;

      $oTrx = $this->transactionv2_model->findByAttr('trx', $trx);
      if(empty($oTrx)) { throw new Exception("Transaction $trx not found", $this->config->item("payu_NOTIFY_TRX_NOT_FOUND")); }

      $idTrx = $oTrx->idTrx; // idTrx

      // Find PayUTrx with idTrx
      $oPayUTrx = $this->payu_model->findByAttr("idTrx", $idTrx); 
      if(empty($oPayUTrx)) { throw new Exception("PayU transaction (idTrx: $idTrx) not found", $this->config->item("payu_NOTIFY_PAYU_TRX_NOT_FOUND")); }

      $idPayUTrx = $oPayUTrx->idPayUTrx;
      if(is_null($this->payu_model->updateTrx($idPayUTrx, $post))) {
        throw new Exception("Couldn't update PayU transaction", $this->config->item("payu_NOTIFY_FAILURE"));
      }

      // Check if transaction was already proccesed.
      // Get state_pol in order to check it
      if(is_null($post->state_pol)) {

        // NEW TRX
        // Save (updating) data received
        /*if(is_null($this->payu_model->updateTrx($idPayUTrx, $post))) {
          throw new Exception("Couldn't update PayU transaction", $this->config->item("payu_NOTIFY_FAILURE"));
        }*/

      } else {
        // Retry (n)
        // Will create new payu trx for every action, keeping original idTrx
      }

      
      
      /* -- TRANSACTION VALIDATIONS --
      1. Si el segundo decimal del parámetro value es cero, ejemplo: 150.00
      El nuevo valor new_value para generar la firma debe ir con sólo un decimal así: 150.0.
      2. Si el segundo decimal del parámetro value es diferente a cero, ejemplo: 150.26
      El nuevo valor new_value para generar la firma debe ir con los dos decimales así: 150.26.
      3. Validación de firma   
      */
      $value = $post->value."";
      $l = strlen($value);
      $lastDigit = substr($value, $l-1, 1);
      $newValue = ($lastDigit == "0") ? substr($value,0,$l-1) : $value;

      $signature = md5($this->config->item("payu_api_key")
                  ."~".$this->config->item("payu_merchant_id")
                  ."~".$post->reference_sale
                  ."~".$newValue
                  ."~".$post->currency
                  ."~".$post->state_pol);

      if($signature != $post->sign) { throw new Exception("Signature doesn't match. Transaction aborted", $this->config->item("payu_NOTIFY_BAD_SIGNATURE")); }

      // Will check transaction state
      $isOk = 1;
      $message = "Transaction OK";
      if((int)$post->state_pol == self::APPROVED) {

        // OK
        $this->core_model->updateStageTrx($idTrx, $this->config->item("payu_TRX_OK"));
        
      } else {

        $stage = 0;
        if((int)$post->state_pol == self::REJECTED) {
          $stage = $this->config->item("payu_TRX_REJECTED");
        } else if((int)$post->state_pol == self::EXPIRED) {
          $stage = $this->config->item("payu_TRX_EXPIRED");
        } else {
          $stage = $this->config->item("payu_TRX_PENDING");
        }

        $isOk = 0;
        $message = $post->response_message_pol;
        $this->core_model->updateStageTrx($idTrx, $stage);
        
      }

      // OK or NOK
      $this->_notify3rdParty(
        $oTrx->urlNotify,   // notify 3rd party (commerce)
        $isOk,              // 0 NOK, 1 OK
        $oTrx->codExternal, // trx 3rd party (commerce)
        $message            // description
      );
  
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

    }

  }

  /**
   * Receive notification from PayU, in order to complete the transaction/payment
   *
   * @return void
   */
  public function voucher() {
    /* https://int.digevopayments.com/pe3g/v2/Payuprovider/voucher?
	merchantId=508029
	merchant_name=Test+PayU+Test+comercio
	&merchant_address=Av+123+Calle+12
	&telephone=7512354
	&merchant_url=http%3A%2F%2Fpruebaslapv.xtrweb.com
	&transactionState=4&
	lapTransactionState=APPROVED
	&message=APPROVED
	&referenceCode=Xne9r80OC7vkgiAjy1
  &reference_pol=844070365
  &transactionId=fdd7abe1-0b8a-47f3-90c5-d778faae8202
  &description=Pago+PayU+-+Test
  &trazabilityCode=00000000
  &cus=00000000
  &orderLanguage=es
  &extra1=
  &extra2=
  &extra3=
  &polTransactionState=4
  &signature=7cf34b5158118d80d48bc24a8b20459c
  &polResponseCode=1
  &lapResponseCode=APPROVED
  &risk=.00
  &polPaymentMethod=10
  &lapPaymentMethod=VISA
  &polPaymentMethodType=2
  &lapPaymentMethodType=CREDIT_CARD
  &installmentsNumber=1
  &TX_VALUE=1900.00
  &TX_TAX=303.36
  &currency=COP
  &lng=es
  &pseCycle=
  &buyerEmail=test%40test.com
  &pseBank=
  &pseReference1=
  &pseReference2=
  &pseReference3=
  &authorizationCode=00000000
  &processingDate=2018-04-19 */
  
    try {

      // Check for params
      $get = (object)$this->input->get();
      if(empty($get)) {
        throw new Exception("Couldn't identify transaction response", $this->config->item("payu_NOTIFY_TRX_NOT_FOUND"));
      }
	
      $trx = $get->referenceCode; // trx
      $oTrx = $this->transactionv2_model->findByAttr('trx', $trx);
      
      if(empty($oTrx)) { throw new Exception("Transaction $trx not found (response page)", $this->config->item("payu_NOTIFY_TRX_NOT_FOUND")); }

      $idTrx = $oTrx->idTrx;
      /*
      PayU's documentation says the following variables arrive only in response page (no confirmation as we expected);
      CUS, pseBank, pseCycle, pseReference1/pseReference2/pseReference3
      */
      $update = [
        "cus" => $get->cus,
        "pse_bank" => $get->pseBank,
        "pse_cycle" => $get->pseCycle,
        "pse_reference1" => $get->pseReference1,
        "pse_reference2" => $get->pseReference2,
        "pse_reference3" => $get->pseReference3
      ];
      

      // Find PayUTrx with idTrx
      $oPayUTrx = $this->payu_model->findByAttr("idTrx", $idTrx); 
      if(empty($oPayUTrx)) {
        throw new Exception("PayU transaction (idTrx: $idTrx) not found (response page)", $this->config->item("payu_NOTIFY_PAYU_TRX_NOT_FOUND"));
      }

      $idPayUTrx = $oPayUTrx->idPayUTrx;

      if(is_null($this->payu_model->updateTrx($idPayUTrx, (object)$update))) {
        throw new Exception("Couldn't update PayU transaction (response page)", $this->config->item("payu_NOTIFY_FAILURE"));
      }

      // Check if trx was successful or not
      if($oTrx->idStage != $this->config->item("payu_TRX_OK")) {
          // Get information from stage
          $oStage = $this->stage->find($oTrx->idStage);
          throw new Exception($oStage->description, 400);
      }


      // Find data to display
      $oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
      $data = [
          "buyOrder"      => $oPayUTrx->reference_pol,
          "bgColor"       => !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault"),
          "fontColor"     => !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault"),
          "logo"          => !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL,
          "amount"        => '$'.number_format(floatval($oTrx->amount), 0, ',', '.'),
          "urlOk"         => $oTrx->urlOk,
          "creationDate"  => date("d/m/Y H:i:s", strtotime($oTrx->creationDate)),
          "status"        => empty($oPayUTrx->response_message_pol) ? "APPROVED" : strtoupper($oPayUTrx->response_message_pol),
          "commerceName"  => $oCommerce->name,
          "currency"      => self::CURRENCY,
          "paymentMethod" => $oPayUTrx->franchise,
          "fee"           => $oPayUTrx->installments_number
      ];

      $this->load->view('payu/success', $data);

    } catch(Exception $e) {

      $this->sanitize->generateLog(__METHOD__, $e->getMessage(), 'error');
            
      $pass = [
          'logo'      =>  false,
          'message'   => "El proceso ha terminado inesperadamente: " . $e->getMessage()
      ];

      $this->load->view('transaction/unexpected', $pass);

    }

  }

  
  // ---------------------- PRIVATE METHODS ----------------------------

  /**
	 * Consume initial transaction in order to turn on
	 */
	private function _postToken($data) {

		try {

      $pass["parameters"] = $data;

			$this->load->view("payu/post_token", $pass);

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
