<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| ----------------------------
| Webpay Plus Integración v1.4
| ----------------------------
| Autor: Gastón Orellana
| Descripción: Opera todos los flujos para la implementación de Webpay Plus de Transbank.
| Fecha creación: 25/04/2017
*/

class Webpayplus extends MY_Controller {

	private $controller = "";
	private $urlError = "";

	// Estados para WebpayPlus
	const NEW_WPP_TRX				= 18;

	const POST_TOKEN_WPP			= 4;
	const RETORNA_TOKEN_WPP			= 5;
	const FALLO_GETTRXRES_WPP		= 6;
	const VALIDA_CON_TRX_WPP		= 7;
	const OK_ALL_WPP				= 9;
	const OK_NO_RESP_NOTIFY_WPP		= 8;
	const ANULADO_TRX_WPP			= 13;
	const TRX_OK_WPP				= 1;
	const FALLO_INIT_TRX_WPP		= 3;


	public function __construct() {
		parent::__construct();
		$this->load->helper('string');
		$this->load->helper('creditcard');
		$this->load->helper('crypto');
		$this->load->helper('url');
		$this->load->library('webpaypluslib');
		$this->load->model('webpay_model', '', TRUE);
		$this->load->model('core_model', '', TRUE);
		$this->load->model('commerceptv2_model', '', TRUE);

		$this->controller = base_url().'webpayplus/';
		$this->urlError = $this->controller."result/error";
	}


	/**
	 * Método última versión para el inicio de transacción con Webpay PLUS
	 * Maneja correctamente el flujo ESPECÍFICO, sin mezclar lógica del core
	 */
	public function initTransactionV2() {

		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;

		// URLs por defecto, por si no alcanza a setear con datos de usuario
		$urlError = $this->urlError;

		try {

			// Recibe a idTrx por POST
			$idTrx = trim($this->input->post("idTrx"));
			$sessionId = trim($this->input->post("sessionId"));
			$amount = trim($this->input->post("amount"));

			// Obtengo el oTrx
			$oTrx = $this->core_model->getTrxById($idTrx);
			if(is_null($oTrx)) throw new Exception("No existe la transacción en el sistema", 1001);

			// Verifica que los montos sea idénticos
			if($amount != $oTrx->amount) throw new Exception("Se ha detectado una inconsistencia en la transacción", 1002);

			// Responde OK con lo generado, inicializa proceso con Transbank
			$oTrxWp = new stdClass();
			$oTrxWp->idTrx = $idTrx;
			$oTrxWp->sessionId = $sessionId;
			$oTrxWp->amount = $amount;
			$oTrxWp->buyOrder = "WPP".str_replace(".","",microtime(TRUE));
			$oTrxWp->creationDate = date("Y-m-d H:i:s");

			// Crea registro en la tabla de Webpay pago normal
			$trxWpBd = $this->webpay_model->initTrx($oTrxWp);
			if(is_null($trxWpBd)) throw new Exception("No se pudo almacenar la información para WebPay. ".print_r($oTrxWp, TRUE), 1004);

			$this->core_model->updateStageTrx($idTrx, self::NEW_WPP_TRX);

			// ---------------------------------------------------------------------
			// Invoca a la librería de WebPay para intentar hacer el initTransaction
			// ---------------------------------------------------------------------
			
			// Multi-code support. If transbank code is sent, will use it, otherwise
			// codes used will be setted by default
			
			// Find transbank code
			$oCommPT = $this->commerceptv2_model->findByAttrs([
                    'cpt.idCommerce'    => $oTrx->idCommerce,
                    'cpt.idPaymentType'	=> $oTrx->idPaymentType
                ]);
			
			if(!empty($oCommPT->ownPaymentCode)) {
				
				// Use prefix attribute to select folder with certificates
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				$prefix = strtoupper($oCommerce->prefix);
			
				$this->webpaypluslib->setConfiguration(
					$oCommPT->ownPaymentCode,
					str_replace("{COMM}", $prefix, $this->config->item("WebpayPrivateKeyWPP")),
					str_replace("{COMM}", $prefix, $this->config->item("WebpayCertFileWPP")),
					str_replace("{COMM}", $prefix, $this->config->item("WebpayCertServerWPP")));

			}
			
			
			
			$webpay = $this->webpaypluslib->initTransaction($amount,
															$oTrxWp->buyOrder,
															$sessionId,
															$this->controller."notify",		// notify de motor de pagos
															$this->controller."voucher");	// voucher (también del motor de pagos)
			
			if(is_null($webpay)) {

				$this->core_model->updateStageTrx($idTrx, self::FALLO_INIT_TRX_WPP);
				throw new Exception("No se pudo inicializar la comunicación con Transbank", 1005);

			} else {
				// Hay respuesta, pero debe verificar que esta sea válida
				if(is_array($webpay)) {
					// Si viene como arreglo, hay error
					if(isset($webpay["error"])) {
						$this->core_model->updateStageTrx($idTrx, self::FALLO_INIT_TRX_WPP);
						throw new Exception($webpay["error"] . " - " . $webpay["detail"], 1007);
					}
				}

			}

			// Recibe el token y la url a donde hacer POST con este
			$token = $webpay->token;
			$urlX = $webpay->url;

			$this->core_model->updateStageTrx($idTrx, self::POST_TOKEN_WPP);

			$upd = new stdClass();
			$upd->token = $token;
			$res = $this->webpay_model->updateTrx($trxWpBd, $upd);
			if(!$res) throw new Exception("No se pudo actualizar la información del token", 1006);

			$this->_postToken($token, $urlX);


		} catch(Exception $e) {
			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
			//print_r($urlError); exit;
			//header('Location: '.$urlError); die();
			//redirect($urlError);
			$this->_postToken("", $urlError);
		}

	}


	/**
	 * Método VITAL, es donde WebPay notifica a nosotros el comercio que la trx está autorizada
	*/
    public function notify() {

        $oTrx = NULL;
        $urlError = $this->urlError;

        $token = "";
        $urlRedirect = "";
        $res = new stdClass();
        $idWPTrxPatPass = 0;

        try {

            $this->load->view("webpay/notify"); // Muestra la pantalla de carga

            // Recibe el token a través de token_ws y verifica que efectivamente exista
            $token = $this->input->post("token_ws");

            if(empty($token)) throw new Exception("No se ha podido determinar la transacción", 1001);

            $oTrxWP = $this->webpay_model->getTrxByToken($token);
            if(is_null($oTrxWP)) throw new Exception("La transacción en proceso no existe", 1002);

            $idTrx = $oTrxWP->idTrx;
            $idWPTrxPatPass = $oTrxWP->idWPTrxPatPass;

            $oTrx = $this->core_model->getTrxById($idTrx);
            if(is_null($oTrx)) throw new Exception("No existe la transacción en el sistema", 1003);

            // Cambia de estado el trx
            $urlError = $oTrx->urlError;
            $this->core_model->updateStageTrx($idTrx, self::RETORNA_TOKEN_WPP);

            // Luego de validar a token_ws, se invoca a getTransactionResult() de webpay para verificar
            // el resultado de la transacción

            // ---------------------------------------------------------------------------
            // WebPay -> getTransactionResult
            // ---------------------------------------------------------------------------
			
			// Use prefix attribute to select folder with certificates
			$oCommPT = $this->commerceptv2_model->findByAttrs([
                    'cpt.idCommerce'    => $oTrx->idCommerce,
                    'cpt.idPaymentType'	=> $oTrx->idPaymentType
                ]);

			if(!empty($oCommPT->ownPaymentCode)) {
				
				// Use prefix attribute to select folder with certificates
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				$prefix = strtoupper($oCommerce->prefix);
			
				$this->webpaypluslib->setConfiguration(
					$oCommPT->ownPaymentCode,
					str_replace("{COMM}", $prefix, $this->config->item("WebpayPrivateKeyWPP")),
					str_replace("{COMM}", $prefix, $this->config->item("WebpayCertFileWPP")),
					str_replace("{COMM}", $prefix, $this->config->item("WebpayCertServerWPP")));

			}
			
            $transactionResultOutput = $this->webpaypluslib->getTransactionResult($token);
            // ---------------------------------------------------------------------------
        
            if(empty($transactionResultOutput)) {
                $this->core_model->updateStageTrx($idTrx, self::FALLO_GETTRXRES_WPP);
                throw new Exception("No se pudo procesar la transacción con Transbank", 1004);
            }

            // Seteo de url de redirección (hacia Transbank)
            $urlRedirect = $transactionResultOutput->urlRedirection;

            // Busca los ID correspondientes
            $paymentTypeCode = $transactionResultOutput->detailOutput->paymentTypeCode;
            $responseCode = $transactionResultOutput->detailOutput->responseCode;
            $vci = $transactionResultOutput->VCI;

            $oPtc = $this->webpay_model->getTypeXXByCode("ptc", $paymentTypeCode);
            $oRc = $this->webpay_model->getTypeXXByCode("rc", $responseCode);
            $oVci = $this->webpay_model->getTypeXXByCode("vci", $vci);
            $typeDef = "XX"; // resultados desconocidos
            if(is_null($oPtc)) $oPtc = $this->webpay_model->getTypeXXByCode("ptc", $typeDef);
            if(is_null($oRc)) $oRc = $this->webpay_model->getTypeXXByCode("rc", $typeDef);
            if(is_null($oVci)) $oVci = $this->webpay_model->getTypeXXByCode("vci", $typeDef);

            // Verifica inmediatamente el resultado de la transacción
            $authorizationCode = $transactionResultOutput->detailOutput->authorizationCode;
            $idWPResponseCode = $oRc->idWPResponseCode;

            log_message("debug", print_r($transactionResultOutput, TRUE));

            $stop = TRUE;
            if(!empty($authorizationCode)) {
                if($authorizationCode != "00000") {
                    // Además, se verifica que el idWPResponseCode sea siempre 1
                    if($idWPResponseCode == 1)
                        $stop = FALSE;
                }
            }
            if($stop) {
                $this->core_model->updateStageTrx($idTrx, self::FALLO_GETTRXRES_WPP);
                log_message("debug", "Último error en BD: " . $this->webpay_model->getLastError());

                $res->idWPPaymentTypeCode = $oPtc->idWPPaymentTypeCode;
                $res->idWPResponseCode = $idWPResponseCode;
                $res->idWPVci = $oVci->idWPVci;
                $res->accountingDate = $transactionResultOutput->accountingDate;
                $res->cardNumber = $transactionResultOutput->cardDetail->cardNumber;
                $res->cardExpirationDate = $transactionResultOutput->cardDetail->cardExpirationDate;
                $res->authorizationCode = $authorizationCode;
                $res->sharesNumber = $transactionResultOutput->detailOutput->sharesNumber;
                $res->transactionDate = $transactionResultOutput->transactionDate;

                throw new Exception($oRc->description, 1009);
            }

            // Actualiza con el resultado de la transacción
            $res->idWPPaymentTypeCode = $oPtc->idWPPaymentTypeCode;
            $res->idWPResponseCode = $idWPResponseCode;
            $res->idWPVci = $oVci->idWPVci;
            $res->accountingDate = $transactionResultOutput->accountingDate;
            $res->cardNumber = $transactionResultOutput->cardDetail->cardNumber;
            $res->cardExpirationDate = $transactionResultOutput->cardDetail->cardExpirationDate;
            $res->authorizationCode = $authorizationCode;
            $res->sharesNumber = $transactionResultOutput->detailOutput->sharesNumber;
            $res->transactionDate = $transactionResultOutput->transactionDate;

            log_message("debug", "Transbank response: " . print_r($res, TRUE));


            // Almacena/actualiza los valores de la respuesta
            $oo = $this->webpay_model->updateTrx($idWPTrxPatPass, $res);


            // -------------------- NOTIFICA OK AL COMERCIO --------------------
            $oNotifyRes = $this->_notify3rdParty($oTrx->urlNotify,
                1, // ok
                $oTrx->codExternal, // trx 3rd party
                $oRc->description);
            // -----------------------------------------------------------------

            if($oNotifyRes) {
                $this->core_model->updateStageTrx($idTrx, self::OK_ALL_WPP);
            } else {
                $this->core_model->updateStageTrx($idTrx, self::OK_NO_RESP_NOTIFY_WPP);
            }


        } catch(Exception $e) {

            log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());

            if($idWPTrxPatPass != 0 && !is_null($res))
                $oo = $this->webpay_model->updateTrx($idWPTrxPatPass, $res);

            if(!is_null($oTrx)) {
                // Notifica de error al comercio
                $this->_notify3rdParty($oTrx->urlNotify,
                    0, // error
                    $oTrx->codExternal, // trx 3rd party
                    $e->getMessage());
            }


        }

        // Envía nuevamente token por POST a Webpay para mostrar el voucher
        if(empty($urlRedirect)) redirect($this->urlError);
        else $this->_postToken($token, $urlRedirect);

    }

    /**
     * Unexpected Error
     *
     * @param string $res identification error
     */
	public function result($res)
    {
        $data = [
            'logo'      =>  false,
            'message'   => "El proceso ha terminado inesperadamente con $res"
        ];

        $this->load->view('transaction/unexpected', $data);
	}



	/**
	 * Página de resultado de Webpay
	 * Según documentación (v1.8) de Transbank, este página recibe el resultado del
	 * flujo de anulación (botón "Anular")
	 */
    public function voucher() {

        $token = $this->input->post("token_ws");

        // Importante: En MOBILE, Transbank envía por TBK_TOKEN_COMPRA la anulación
        $anulado = FALSE;
        $mobile = FALSE;
        $anulacion = "";
        if(!empty($this->input->post("TBK_TOKEN"))) {
            $anulacion = $this->input->post("TBK_TOKEN");
        } else {
            // mobile
            $anulacion = $this->input->post("TBK_ORDEN_COMPRA");
            $mobile = TRUE;
        }

        // Si no llega el token, se considera como error porque el proceso de anulación, ahora corre como
        // un flujo determinado y a través de una excepción
        if(!empty($anulacion)) {

            // Se verifica que la transacción haya sido efectivamente anulada.
            // Debe retornar una excepción
			
			$oTrxWP = NULL;
			if($mobile) {
				// Busca por el buyOrder
				$oTrxWP = $this->webpay_model->getTrxByBuyOrder($anulacion);
			} else {
				$oTrxWP = $this->webpay_model->getTrxByToken($anulacion);
			}

			$oTrx = $this->core_model->getTrxById($oTrxWP->idTrx);
			
			// Use prefix attribute to select folder with certificates
			$oCommPT = $this->commerceptv2_model->findByAttrs([
                    'cpt.idCommerce'    => $oTrx->idCommerce,
                    'cpt.idPaymentType'	=> $oTrx->idPaymentType
                ]);

			if(!empty($oCommPT->ownPaymentCode)) {
				
				// Use prefix attribute to select folder with certificates
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				$prefix = strtoupper($oCommerce->prefix);
			
				$this->webpaypluslib->setConfiguration(
					$oCommPT->ownPaymentCode,
					str_replace("{COMM}", $prefix, $this->config->item("WebpayPrivateKeyWPP")),
					str_replace("{COMM}", $prefix, $this->config->item("WebpayCertFileWPP")),
					str_replace("{COMM}", $prefix, $this->config->item("WebpayCertServerWPP")));

			}
			
			// Call webpay library
            $getTransactionResultResponse = $this->webpaypluslib->getTransactionResult($anulacion);

            // Transacción anulada
            if(is_array($getTransactionResultResponse)) {

                $err = $getTransactionResultResponse["error"];
                $detail = $getTransactionResultResponse["detail"];

                // Obtengo oTrxWP por su token
                log_message("debug", __METHOD__ . " Se ha anulado la transacción con token = $anulacion");

                /*$oTrxWP = NULL;
                if($mobile) {
                    // Busca por el buyOrder
                    $oTrxWP = $this->webpay_model->getTrxByBuyOrder($anulacion);
                } else {
                    $oTrxWP = $this->webpay_model->getTrxByToken($anulacion);
                }

                $oTrx = $this->core_model->getTrxById($oTrxWP->idTrx);*/

                $this->core_model->updateStageTrx($oTrxWP->idTrx, self::ANULADO_TRX_WPP);

                $oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);

                // Como pasa directo al voucher, requiere hacer el notify al comercio
                $this->_notify3rdParty($oTrx->urlNotify,
                    0, // error
                    $oTrx->codExternal, // trx 3rd party
                    "La transacción fue anulada");

                $data["error"] = 1;
                $data["buyOrder"] = $oTrxWP->buyOrder;
                $data["msj"] = "La transacción ha sido anulada";
                $data["returnUrl"] = $oTrx->urlOk; // urlOK
                $data["errorUrl"] = $oTrx->urlError;
                $data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
                $data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
                $data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;

                $this->load->view("webpay/voucher_normal", $data);

                return;
            } else {
                // Error desconocido
                redirect($this->urlError);
            }

        }

        if(empty($token)) {
            // Si no viene token, existe un error o se está accediendo maliciosamente
            // Se redirige a término fatal
            //print_r($_POST); exit;
            redirect($this->urlError);
        }

        $oTrxWP = $this->webpay_model->getTrxByToken($token);
        $oTrx = $this->core_model->getTrxById($oTrxWP->idTrx);
        $oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
        $ptc = $this->webpay_model->getPTById($oTrxWP->idWPPaymentTypeCode);

        // Busca la información en la TRX para enviarlo a la URL de éxito del comercio
        $data["buyOrder"] = $oTrxWP->buyOrder;

        // Evalúa la respuesta
        $error = 0;

        $arr = explode("T", $oTrxWP->transactionDate);
        $hora = explode(".", $arr[1]);
        $str2 = $arr[0]." ".$hora[0];

        $data["amount"] = "$".number_format((float)$oTrxWP->amount, 0, ",", ".");
        $data["currency"] = (int)$oTrxWP->ufFlag == 1 ? "UF" : "CLP";
        $data["authorizationCode"] = $oTrxWP->authorizationCode;
        $data["transactionDate"] = $str2;
        //$data["paymentType"] = $ptc->description." (".$ptc->code.")";
        $data["paymentType"] = "Crédito";

        $sharesNumber = array(
            "VD" => "00",
            "VN" => "00",
            "VC" => $oTrxWP->sharesNumber,
            "SI" => 3,
            "S2" => 2,
            "NC" => $oTrxWP->sharesNumber
        );
        $sharesType = array(
            "VD" => "Venta Débito",
            "VN" => "Sin Cuotas",
            "VC" => "Cuotas Normales",
            "SI" => "Sin Interés",
            "S2" => "Sin Interés",
            "NC" => "Sin Interés"
        );

        $data["sharesType"] = $sharesType[$ptc->code];
        $data["sharesNumber"] = $sharesNumber[$ptc->code];
        $data["cardNumber"] = "XXXXXXXXXXXX".$oTrxWP->cardNumber;

        $msj = "¡Transacción Realizada Satisfactoriamente!";

        // Carga de vista con voucher final (resultado OK)

        $data["error"] = ($oTrx->idStage != self::OK_ALL_WPP && $oTrx->idStage != self::OK_NO_RESP_NOTIFY_WPP) ? 1: 0; // no hay error
        $data["msj"] = ($oTrx->idStage != self::OK_ALL_WPP && $oTrx->idStage != self::OK_NO_RESP_NOTIFY_WPP) ? "Error en la transacción" : $msj;
        $data["returnUrl"] = $oTrx->urlOk;
        $data["errorUrl"] = $oTrx->urlError;
        $data["description"] = $oCommerce->description;
        $data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
        $data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
        $data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;

        $this->load->view("webpay/voucher_normal", $data);
    }





	// ------------------- PRIVATE METHODS -------------------





	/**
	 * Hace envío del token a url por POST, a través de variable token_ws
	*/
	private function _postToken($token, $url) {

		try {

			$data["token"] = $token;
			$data["url"] = $url;

			// Dejar token en session por si se anula
			$this->session->set_userdata("token_tmp", $token);

			$this->load->view("webpay/post_token", $data);

		} catch(Exception $e) {
			$this->_error($e->getMessage());
		}

	}


}
