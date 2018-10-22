<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| ----------------------------
| OneClick Integración v1.0
| ----------------------------
| Autor: Gastón Orellana
| Descripción: Opera todos los flujos para la implementación de Oneclick de Transbank.
| Fecha creación: 08/02/2017
|
| ---------------
| Modificaciones:
| ---------------
| v1.0: 08/02/2017, GOC
| Primera versión de integración
*/

class Oneclick extends MY_Controller {

	private $controller = "";
	private $urlError = ""; // url por defecto para todos los métodos
	
	// Etapas de transacción
	const NEW_TRX_ONECLICK				= 23;
	const FAILED_TRX_ONECLICK			= 24;
	const POST_TRX_ONECLICK				= 26;
	const FINISH_TRX_ONECLICK			= 27;
	const FAILED_FINISH_TRX_ONECLICK	= 28;
	const OK_FINISH_TRX_ONECLICK		= 29;
	const OK_TRX_ONECLICK				= 30;
	const TERMS_AND_COND_ACCEPTED		= 35;
	const TERMS_AND_COND_REJECTED		= 36;
	const REVERSED_TRX_ONECLICK			= 43;
	
	// Estados de respuesta de Oneclick (authorize)
	const OK							= 1;
	const REJECTED_1					= 2;
	const REJECTED_2					= 3;
	const REJECTED_3					= 4;
	const REJECTED_4					= 5;
	const REJECTED_5					= 6;
	const REJECTED_6					= 7;
	const REJECTED_7					= 8;
	const REJECTED_8					= 9;
	const MAX_DAILY_AMOUNT_EXCEEDED		= 10;
	const MAX_AMOUNT_EXCEEDED			= 11;
	const MAX_DAILY_QUANTITY_EXCEEDED	= 12;
	
	// Estados relacionados a recurrencia
	const OK_ONECLICK_RECURRENCE		= 38;
	const FAILED_ONECLICK_RECURRENCE	= 39;
	const TRY_ONECLICK_RECURRENCE		= 41;
	
	const PAYMENT_TYPE					= 8; // payment type (oneclick)

	public function __construct() {
		parent::__construct();
		$this->load->helper('string');
		$this->load->helper('creditcard');
		$this->load->helper('crypto');
		$this->load->helper('url');
		$this->load->library('encryption');
		$this->load->model('oneclick_model', '', TRUE);
		$this->load->model('core_model', '', TRUE);
		$this->load->model('commerceptv2_model', '', TRUE);
		
		$this->controller = base_url().'oneclick/';
		$this->urlError = $this->controller."result/error";
	}
	
	
	/**
	 * Muestra el término y condiciones
	 */
	public function showTermsAndConditions() {
		
		// URLs por defecto, por si no alcanza a setear con datos de usuario
		$urlError = $this->urlError;
		$oTrx = NULL;

		try {
			
			$token = trim($this->input->post("token")); // llega encriptado (idTrx)
			if(empty($token)) throw new Exception("No se pudo identificar la transacción", 1000);
			
			$idTrx = decode_url($token);
			$oTrx = $this->core_model->getTrxById($idTrx);
			if(is_null($oTrx)) throw new Exception("No se ha pudo determinar la transacción en el sistema", 1002);
			
			// Comercio
			$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
			
			// Paso de parámetros a vista
			$this->data["host"] = base_url();
			$this->data["action"] = $this->controller."doInscriptionAction";
			$this->data["token"] = $token;
			$this->data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
			$this->data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
			$this->data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;
			$this->data["commName"] = $oCommerce->name;
			
			$this->load->view("oneclick/show_terms_and_cond_view", $this->data);
			
		} catch(Exception $e) {
			
			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
			
			$this->data["message"] = $e->getMessage();
			$this->data["url"] = $urlError;
			
			if(!is_null($oTrx)) {
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				$this->data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
				$this->data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
				$this->data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;
				
			} else {
				
				// Por defecto
				$this->data["bgColor"] = "#".$this->config->item("BgColorDefault");
				$this->data["fontColor"] = "#".$this->config->item("FontColorDefault");
				$this->data["logo"] = NULL;
				
			}
			
			$this->load->view("error2_view", $this->data);
		}
		
	}
	
	/**
	 * Encargado solo de ejecutar proceso de inscripción de Oneclick
	 *
	 * @return void
	 */
	public function doInscriptionAction() {
		
		$urlError = $this->urlError;
		$oTrx = NULL;
		
		try {
			
			// trx encriptado
			$token = trim($this->input->post("token"));
			$selection = trim($this->input->post("selection"));
			if(empty($token)) throw new Exception("No se pudo identificar la transacción", 1000);
			if(empty($selection)) throw new Exception("No se pudo identificar la acción a realizar", 1000);
			
			$selection = strtolower($selection);
			$idTrx = decode_url($token);

			if($selection == "ok") {
				
				// Realiza proceso de inscripción (enrrolamiento)
				$res = $this->core_model->updateStageTrx($idTrx, self::TERMS_AND_COND_ACCEPTED);
				
				$oTrx = $this->core_model->getTrxById($idTrx);
				if(is_null($oTrx)) throw new Exception("No se ha pudo determinar la transacción en el sistema", 1001);
				
				// -----------------------
				// Inicio de enrrolamiento
				// -----------------------
				// Crea transacción en tabla Oneclick
				$oOneclick = new stdClass();
				$oOneclick->idTrx = $idTrx;
				$oOneclick->creationDate = date("Y-m-d H:i:s");
				
				// Crea registro en la tabla de Oneclick
				$idOneclickTrx = $this->oneclick_model->initTrx($oOneclick);
				
				if(is_null($idOneclickTrx)) throw new Exception("No se pudo almacenar la información para Oneclick. ".print_r($oOneclick, TRUE), 1004);
				
				// Comercio asociado
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				
				// INVOCA LIBRERÍA ONECLICK -> initTransaction()
				$idUserExternal = $oTrx->idUserExternal;
				$prefix = strtolower($oCommerce->prefix);
				//print_r($prefix); exit;
				
				
				$oCommPT = $this->commerceptv2_model->findByAttrs([
                    'cpt.idCommerce'    => $oTrx->idCommerce,
                    'cpt.idPaymentType'	=> $oTrx->idPaymentType
                ]);
				
				$this->load->library('oneclicklib');
				if(!empty($oCommPT->ownPaymentCode)) {
					
					// Use prefix attribute to select folder with certificates
					$this->oneclicklib->setConfiguration(
						$oCommPT->ownPaymentCode,
						str_replace("{COMM}", $prefix, $this->config->item("OneclickPrivateKey")),
						str_replace("{COMM}", $prefix, $this->config->item("OneclickCertFile")),
						str_replace("{COMM}", $prefix, $this->config->item("OneclickCertServer")));

				}
				
				//print_r($this->oneclicklib); exit;
				$initTrxOneclick = $this->oneclicklib->initTransaction($prefix.$idUserExternal, 						// username
																		$prefix."_".$idUserExternal."@3gmotion.com",	// email
																		$this->controller."finishTransaction");
				// Revisa integridad de la respuesta									
				if(empty($initTrxOneclick)) {
					$this->core_model->updateStageTrx($idTrx, self::FAILED_TRX_ONECLICK);
					throw new Exception("No se pudo inicializar la comunicación con Transbank.", 1005);
				} else {
					// No viene vacío, pero determina si viene con error o no
					if(is_array($initTrxOneclick)) {
						// Si viene como arreglo, hay error
						if(isset($initTrxOneclick["error"])) {
							$this->core_model->updateStageTrx($idTrx, self::FAILED_TRX_ONECLICK);
							throw new Exception($initTrxOneclick["error"] . " - " . $initTrxOneclick["detail"], 1007);
						}
					}
				}
				
				// Recibe el token y la url a donde hacer POST con este
				$token = $initTrxOneclick->token;
				$urlWebpay = $initTrxOneclick->urlWebpay;
				
				// Actualiza etapa de transacción y token en oneclick
				$this->core_model->updateStageTrx($idTrx, self::POST_TRX_ONECLICK);
				
				$upd = new stdClass();
				$upd->token = $token;
				$res = $this->oneclick_model->updateTrx($idOneclickTrx, $upd);
				if(!$res) throw new Exception("No se pudo actualizar la información del token", 1006);
				
				// Llamado efectivo a Tranbank
				$this->_postToken($token, $urlWebpay);
				
			} else if($selection == "nok") {
				
				// Marca la transacción como rechazada (no se aceptaron los términos y condiciones)
				// Busca la URL de error para redireccionar
				$res = $this->core_model->updateStageTrx($idTrx, self::TERMS_AND_COND_REJECTED);
				
				$oTrx = $this->core_model->getTrxById($idTrx);
				if(is_null($oTrx)) throw new Exception("No se ha pudo determinar la transacción en el sistema", 1001);
				
				$urlError = $oTrx->urlError;
				throw new Exception("No se han aceptado los términos y condiciones", 1002);
				
			} else {
				// Acción incorrecta
				throw new Exception("No se pudo identificar la acción a realizar", 1003);
			}
			
		} catch(Exception $e) {
			
			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
			
			if(!is_null($oTrx)) {
				// Notifica de error al comercio
				/*$this->_notify3rdParty($oTrx->urlNotify,
										0, // error
										$oTrx->codExternal, // trx 3rd party
										$e->getMessage());*/
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				$this->data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
				$this->data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
				$this->data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;
				
			} else {
				
				// Por defecto
				$this->data["bgColor"] = "#".$this->config->item("BgColorDefault");
				$this->data["fontColor"] = "#".$this->config->item("FontColorDefault");
				$this->data["logo"] = NULL;
				
			}
			
			$this->data["message"] = $e->getMessage();
			$this->data["url"] = $urlError;
			
			$this->load->view("error2_view", $this->data);
			
		}
		
	}
	
	
	/**
	 * Método que recibe el token de la respuesta de initTransaction
	 * Debe invocar al finishTransaction para obtener resultado del enrrolamiento
	 * y dar por culminado el proceso.
	 *
	 * IMPORTANTE: Este proceso NO hace ningún cargo a la cuenta del usuario
	 *
	 */
	public function finishTransaction() {
		
		$oTrx = NULL;
		$urlError = $this->urlError;
		
		try {
			
			$start = microtime(TRUE);
			
			// Recibe el token a través de "TBK_TOKEN"
			$token = $this->input->post("TBK_TOKEN");
			if(empty($token)) throw new Exception("No se ha podido determinar la transacción", 1000);
			
			// Se trae OneclickTrx
			$oOneclick = $this->oneclick_model->getTrxByToken($token);
			if(is_null($oOneclick)) throw new Exception("La transacción token $token no existe", 1001);
	
			// Se trae Trx
			$idTrx = $oOneclick->idTrx;
			$oTrx = $this->core_model->getTrxById($idTrx);
			if(is_null($oTrx)) throw new Exception("No existe la transacción id ".$idTrx." en el sistema", 1002);
			$urlError = $oTrx->urlError;
			
			// Con la identificación de todo lo necesario, actualiza estado
			$this->core_model->updateStageTrx($idTrx, self::FINISH_TRX_ONECLICK);
			
			// ----------------------------------------------------------------
			// Invoca librería Oneclick -> finishTransaction()
			// ----------------------------------------------------------------
			
			// Multi-code support. If transbank code is sent, will use it, otherwise
			// codes used will be setted by default
			
			// Find transbank code
			$oCommPT = $this->commerceptv2_model->findByAttrs([
                    'cpt.idCommerce'    => $oTrx->idCommerce,
                    'cpt.idPaymentType'	=> $oTrx->idPaymentType
                ]);
			
			$this->load->library('oneclicklib');
			if(!empty($oCommPT->ownPaymentCode)) {
				
				// Use prefix attribute to select folder with certificates
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				$prefix = strtoupper($oCommerce->prefix);
			
				$this->oneclicklib->setConfiguration(
					$oCommPT->ownPaymentCode,
					str_replace("{COMM}", $prefix, $this->config->item("OneclickPrivateKey")),
					str_replace("{COMM}", $prefix, $this->config->item("OneclickCertFile")),
					str_replace("{COMM}", $prefix, $this->config->item("OneclickCertServer")));

			}
			
			$finishTransaction = $this->oneclicklib->finishInscription($token);
			// ----------------------------------------------------------------

			// Falló validación de respuesta desde Transbank
			$stop = FALSE;
			if(is_null($finishTransaction)) {
				$stop = TRUE;
			} else {
				// Hay respuesta, así que debe verificar por el respondeCode
				if((int)$finishTransaction->responseCode != 0) $stop = TRUE;
			}
			
			if($stop) {
				$this->core_model->updateStageTrx($idTrx, self::FAILED_FINISH_TRX_ONECLICK);
				throw new Exception("Sucedió un error en el proceso de enrrolamiento", 1004);
			}
			
			// Si llega a este punto, el enrrolamiento fue satisfactorio
			$resFinishTransaction = new stdClass();
			$resFinishTransaction->authCode = $finishTransaction->authCode;
			$resFinishTransaction->creditCardType = $finishTransaction->creditCardType;
			$resFinishTransaction->last4CardDigits = $finishTransaction->last4CardDigits;
			$resFinishTransaction->responseCode = $finishTransaction->responseCode;
			$resFinishTransaction->tbkUser = $finishTransaction->tbkUser;
		
			// Actualiza información de enrrolamiento
			// No puedo detener el proceso por un error en la actualización,
			// porque en este punto el proceso de enrrolamiento finalizó correctamente.
			$res = $this->oneclick_model->updateTrx($oOneclick->idOneclickTrx, $resFinishTransaction);
			
			// Actualiza estado
			$this->core_model->updateStageTrx($idTrx, self::OK_FINISH_TRX_ONECLICK);
			
			// Ahora y como fue exitoso, hace el cobro (authorize) automáticamente
			// Invoco método de cobro efectivo (authorize)

			// 10-05-2018, si la trx es try&buy, no ejecuta authorize automático y envío OK instantáneo
			$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
			if($oTrx->try == 1) {

				// try&buy
				// Notificación OK a comercio
				$this->_notify3rdParty(
										$oTrx->urlNotify,
										1, // OK
										$oTrx->codExternal,
										"Transacción realizada satisfactoriamente"
									);
				// -----------------------------------------------------------------
				// Resultado de la transacción
				$this->data["authCode"] = $oOneclick->authCode;
				$this->data["amount"] = $oTrx->amount;
				$this->data["trx"] = $oTrx->trx;
				//$this->data["authorizationCode"] = $res->authorizationCode;
				$this->data["creationDate"] = $oOneclick->creationDate;
				$this->data["creditCardType"] = $oOneclick->creditCardType;
				$this->data["last4CardDigits"] = $oOneclick->last4CardDigits;
				$this->data["description"] = "Suscripción modalidad Try&Buy";
				$this->data["urlOk"] = $oTrx->urlOk;
				$this->data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
				$this->data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
				$this->data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;
				$this->data["commerceName"] = $oCommerce->name;
				
				//$this->load->view("oneclick/voucher", $this->data);
				$this->load->view('oneclick/success_inscription', $this->data);


			} else {
				//$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
			
				$authorize = array(
					"idTrx" => $idTrx,
					"codExternal" => $oTrx->codExternal,
					"username" => $oTrx->idUserExternal,
					"tbkUser" => $resFinishTransaction->tbkUser,
					"amount" => $oTrx->amount,
					"description" => $oCommerce->description,
					"urlError" => $urlError,
					"urlOk" => $oTrx->urlOk,
					"urlNotify" => $oTrx->urlNotify
				);
				
				$this->_doPost(base_url()."oneclick/authorize", $authorize, FALSE); // no hay retorno
			}

		} catch(Exception $e) {
			
			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
			
			if(!is_null($oTrx)) {
				// Notifica de error al comercio
				$this->_notify3rdParty($oTrx->urlNotify,
										0, // error
										$oTrx->codExternal, // trx 3rd party
										$e->getMessage());
				
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				$this->data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
				$this->data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
				$this->data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;
				
			} else {
				
				// Por defecto
				$this->data["bgColor"] = "#".$this->config->item("BgColorDefault");
				$this->data["fontColor"] = "#".$this->config->item("FontColorDefault");
				$this->data["logo"] = NULL;
				
			}
			
			$this->data["message"] = $e->getMessage();
			$this->data["url"] = $urlError;
			
			$this->load->view("error2_view", $this->data);
			
		}
		
	}
	
	
	
	/**
	 * Autoriza una transacción.
	 * Es el cobro efectivo contra la tarjeta del usuario
	 * Recibe el identificador de la transacción (idTrx) encriptado
	 *
	 * @return string (json)
	 *
	 */
	public function authorize() {
		
		$urlError = $this->urlError;
		$codExternal = "";
		$urlNotify = "";
		$oTrx = NULL;
		
		// Recibe parámetros
		try {
			
			$start = microtime(TRUE);
			
			$idTrx = trim($this->input->post("idTrx"));
			$codExternal = trim($this->input->post("codExternal"));
			$username = trim($this->input->post("username")); // idUserExternal
			$tbkUser = trim($this->input->post("tbkUser"));
			$amount = trim($this->input->post("amount"));
			$description = trim($this->input->post("description"));
			$urlErrorX = trim($this->input->post("urlError"));
			$urlOk = trim($this->input->post("urlOk"));
			$urlNotify = trim($this->input->post("urlNotify"));
			
			if(empty($idTrx) || empty($username) || empty($tbkUser) || empty($amount) || empty($description)
				|| empty($codExternal) || empty($urlErrorX) || empty($urlOk) || empty($urlNotify))
				throw new Exception("No se han detectado los parámetros necesarios para continuar", 1001);
			
			$oTrx = $this->core_model->getTrxById($idTrx);
			if(is_null($oTrx)) throw new Exception("No existe la transacción id ".$idTrx." en el sistema", 1002);
			
			$urlError = $urlErrorX;
			
			// ---------------------------------------
			// Parámetros iniciales de trx en Oneclick
			// ---------------------------------------
			// Inicializa proceso con Transbank
			$oOneclick = new stdClass();
			$oOneclick->idTrx = $idTrx;
			$oOneclick->buyOrder = $idTrx+1000; //str_replace(".","",microtime(TRUE)); // dice LONG la doc de Transbank (?)
			$oOneclick->creationDate = date("Y-m-d H:i:s");
			
			// Crea registro en la tabla de Oneclick
			$idOneclickTrx = $this->oneclick_model->initTrx($oOneclick);
			if(is_null($idOneclickTrx)) throw new Exception("No se pudo almacenar la información para Oneclick. ".print_r($oOneclick, TRUE), 1004);
			
			// ---------------------------------------------
			// Invoca librería Oneclick -> initTransaction()
			// ---------------------------------------------
			$start2 = microtime(TRUE);
			
			/*
			$prefix.$idUserExternal, 						// username
			$prefix."_".$idUserExternal."@3gmotion.com",	// email
			*/
			
			$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
			
			
			// Multi-code support. If transbank code is sent, will use it, otherwise
			// codes used will be setted by default
			
			// Find transbank code
			$oCommPT = $this->commerceptv2_model->findByAttrs([
                    'cpt.idCommerce'    => $oTrx->idCommerce,
                    'cpt.idPaymentType'	=> $oTrx->idPaymentType
                ]);
			/*	echo "hola";
			print_r($oCommPT); exit;*/
			
			$this->load->library('oneclicklib');
			/*echo "<pre>";
			print_r($this->oneclicklib);
			echo "</pre>";
			exit;*/
			if(!empty($oCommPT->ownPaymentCode)) {
				
				// Use prefix attribute to select folder with certificates
				$prefix = strtoupper($oCommerce->prefix);
			
				$this->oneclicklib->setConfiguration(
					$oCommPT->ownPaymentCode,
					str_replace("{COMM}", $prefix, $this->config->item("OneclickPrivateKey")),
					str_replace("{COMM}", $prefix, $this->config->item("OneclickCertFile")),
					str_replace("{COMM}", $prefix, $this->config->item("OneclickCertServer")));

			}
			
			$authorizeRes = $this->oneclicklib->authorize($oOneclick->buyOrder,
														$tbkUser,
														strtolower($oCommerce->prefix).$username,
														$amount);
			$timeElapsed2 = microtime(true) - $start2; // segundos
			log_message("debug", "Oneclicklib:authorize -> TIEMPO TRANSCURRIDO: $timeElapsed2");
			
			if(empty($authorizeRes)) {
				$this->core_model->updateStageTrx($idTrx, self::FAILED_TRX_ONECLICK);
				throw new Exception("No se pudo inicializar la comunicación con Transbank.", 1005);
			}
			
			// Resultado de authorize
			// Busca tabla de estados
			$codes = array(
				"0" => 1,		// OK (Aprobado)
				"-1" => 2,		// Rechazado
				"-2" => 3,		// Rechazado
				"-3" => 4,		// Rechazado
				"-4" => 5,		// Rechazado
				"-5" => 6,		// Rechazado
				"-6" => 7,		// Rechazado
				"-7" => 8,		// Rechazado
				"-8" => 9,		// Rechazado
				"-97" => 10,	// Límites Oneclick, máximo monto diario de pago excedido
				"-98" => 11,	// Límites Oneclick, máximo monto de pago excedido
				"-99" => 12,	// Límites Oneclick, máxima cantidad de pagos diarios excedido
			);
			
			$res = new stdClass();
			$res->idOneclickTrxResult = $codes[$authorizeRes->responseCode];
			$res->authorizationCode = $authorizeRes->authorizationCode;
			$res->creditCardType = $authorizeRes->creditCardType;
			$res->last4CardDigits = $authorizeRes->last4CardDigits;
			//$res->responseCode = $authorizeRes->responseCode;
			$res->transactionId = $authorizeRes->transactionId;
			
			$this->oneclick_model->updateTrx($idOneclickTrx, $res);
			
			if($res->idOneclickTrxResult != 1) {
				$this->core_model->updateStageTrx($idTrx, self::FAILED_TRX_ONECLICK);
				throw new Exception("No se pudo inicializar la comunicación con Transbank.", 1005);
			}
			
			// Llegado a este punto, la transacción fue realizada satisfactoriamente
			$this->core_model->updateStageTrx($idTrx, self::OK_TRX_ONECLICK);
			
			// Obtengo comercio
			$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
			
			$startC = microtime(TRUE);
			// -----------------------------------------------------------------
			// 				NOTIFICA A COMERCIO EL OK DE LA TRX
			// -----------------------------------------------------------------
			$notifying = $this->_notify3rdParty($urlNotify,
									1, // OK
									$codExternal,
									"Transacción realizada satisfactoriamente");
			// -----------------------------------------------------------------
			$timeElapsedC = microtime(true) - $startC; // segundos
			log_message("debug", "Notificación a comercio -> TIEMPO TRANSCURRIDO: $timeElapsedC");	
			
			// Resultado de la transacción
			$this->data["buyOrder"] = $oOneclick->buyOrder;
			$this->data["amount"] = $amount;
			$this->data["authorizationCode"] = $res->authorizationCode;
			$this->data["creationDate"] = $oOneclick->creationDate;
			$this->data["last4CardDigits"] = $res->last4CardDigits;
			$this->data["description"] = $description;
			$this->data["urlOk"] = $urlOk;
			$this->data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
			$this->data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
			$this->data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;
			
			$timeElapsed = microtime(true) - $start; // segundos
			log_message("debug", __METHOD__ . " TIEMPO TRANSCURRIDO: $timeElapsed");
			
			//$this->load->view("oneclick/voucher", $this->data);
            $this->load->view('oneclick/success', $this->data);
		} catch(Exception $e) {

			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
			
			if(!is_null($oTrx)) {
				// Notifica de error al comercio
				$this->_notify3rdParty($urlNotify,
									0, // error
									$codExternal, // trx 3rd party
									$e->getMessage());
				
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				$this->data["bgColor"] = !is_null($oCommerce->bgColor) ? "#".$oCommerce->bgColor : "#".$this->config->item("BgColorDefault");
				$this->data["fontColor"] = !is_null($oCommerce->fontColor) ? "#".$oCommerce->fontColor : "#".$this->config->item("FontColorDefault");
				$this->data["logo"] = !is_null($oCommerce->logo) ? $this->config->item("LogosPath").$oCommerce->logo : NULL;
				
			} else {
				
				// Por defecto
				$this->data["bgColor"] = "#".$this->config->item("BgColorDefault");
				$this->data["fontColor"] = "#".$this->config->item("FontColorDefault");
				$this->data["logo"] = NULL;
				
			}
			
			$this->data["message"] = $e->getMessage();
			$this->data["url"] = $urlError;
			
			$this->load->view("error2_view", $this->data);
			
		}
		
	}
	
	/**
	 * Simple authorize request. It will be used by recurrence flow.
	 *
	 * @return boolean
	 */
	public function authorizeSimplified() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		// Recibe parámetros
		try {
			
			$start = microtime(TRUE);
			
			$post = $this->sanitize->inputParams(true, true, false);
			
			//$idTrx = trim($this->input->post("idTrx"));
			$idCommerce = $post->idCommerce;
			$trx = $post->trx;
			$amount = $post->amount;
			$idUserExternal = $post->idUserExternal; // will be used as part of username
			$codExternal = $post->codExternal;
			$tbkUser = $post->tbkUser;
			$prefixCommerce = $post->prefixCommerce;
			
			if(empty($idCommerce) || empty($trx) || empty($amount) || empty($idUserExternal) || empty($codExternal) || empty($tbkUser) || empty($prefixCommerce)) {
				throw new Exception("No se han detectado los parámetros necesarios para continuar", 1001);
			}
			
			// Create new transaction
			$newTrx = new stdClass();
			$newTrx->idStage				= self::TRY_ONECLICK_RECURRENCE;
            $newTrx->idCommerce				= $idCommerce;
			$newTrx->idPaymentType			= self::PAYMENT_TYPE;
            $newTrx->trx					= $trx;
			$newTrx->amount					= $amount;
			$newTrx->idUserExternal			= $idUserExternal;
			$newTrx->codExternal			= $codExternal;
			
			
			$newTrx->idApp 					= "";
			$newTrx->idPlan 				= 0;
			$newTrx->idCountry 				= 1;	// Chile
			
            $newTrx->creationDate 			= date('Y-m-d H:i:s');
			
            // Log init params
            log_message('debug', print_r($newTrx, TRUE));

            // Start new trx in engine
            $idTrx = $this->core_model->newTrx($newTrx);
			if (is_null($idTrx)) {
				throw new Exception('Failed to start transaction on system', 1002);
            }

			// ---------------------------------------
			// Parámetros iniciales de trx en Oneclick
			// ---------------------------------------
			// Inicializa proceso con Transbank
			$oOneclick = new stdClass();
			$oOneclick->idTrx = $idTrx;
			$oOneclick->buyOrder = $idTrx+1000; //str_replace(".","",microtime(TRUE)); // dice LONG la doc de Transbank (?)
			$oOneclick->creationDate = date("Y-m-d H:i:s");
			
			// Crea registro en la tabla de Oneclick
			$idOneclickTrx = $this->oneclick_model->initTrx($oOneclick);
			if(is_null($idOneclickTrx)) throw new Exception("No se pudo almacenar la información para Oneclick. ".print_r($oOneclick, TRUE), 1004);
			
			// ---------------------------------------------
			// Invoca librería Oneclick -> initTransaction()
			// ---------------------------------------------
			$start2 = microtime(TRUE);
			
			$this->load->library('oneclicklib');

			$authorizeRes = $this->oneclicklib->authorize($oOneclick->buyOrder,
														$tbkUser,
														strtolower($prefixCommerce).$idUserExternal,
														$amount);
			$timeElapsed2 = microtime(true) - $start2; // segundos
			log_message("debug", "Oneclicklib:authorize -> TIEMPO TRANSCURRIDO: $timeElapsed2");
			
			if(empty($authorizeRes)) {
				$this->core_model->updateStageTrx($idTrx, self::FAILED_ONECLICK_RECURRENCE);
				throw new Exception("No se pudo inicializar la comunicación con Transbank.", 1005);
			}
			
			// Resultado de authorize
			// Busca tabla de estados
			$codes = array(
				"0" => 1,		// OK (Aprobado)
				"-1" => 2,		// Rechazado
				"-2" => 3,		// Rechazado
				"-3" => 4,		// Rechazado
				"-4" => 5,		// Rechazado
				"-5" => 6,		// Rechazado
				"-6" => 7,		// Rechazado
				"-7" => 8,		// Rechazado
				"-8" => 9,		// Rechazado
				"-97" => 10,	// Límites Oneclick, máximo monto diario de pago excedido
				"-98" => 11,	// Límites Oneclick, máximo monto de pago excedido
				"-99" => 12,	// Límites Oneclick, máxima cantidad de pagos diarios excedido
			);
			
			$res = new stdClass();
			$res->idOneclickTrxResult = $codes[$authorizeRes->responseCode];
			$res->authorizationCode = $authorizeRes->authorizationCode;
			$res->creditCardType = $authorizeRes->creditCardType;
			$res->last4CardDigits = $authorizeRes->last4CardDigits;
			$res->transactionId = $authorizeRes->transactionId;
			
			$this->oneclick_model->updateTrx($idOneclickTrx, $res);
			
			if($res->idOneclickTrxResult != 1) {
				$this->core_model->updateStageTrx($idTrx, self::FAILED_ONECLICK_RECURRENCE);
				throw new Exception("No se pudo inicializar la comunicación con Transbank.", 1005);
			}
			
			// Llegado a este punto, la transacción fue realizada satisfactoriamente
			$this->core_model->updateStageTrx($idTrx, self::OK_ONECLICK_RECURRENCE);
			
			// OK
			$salida->code = 0;
			$salida->result = $idTrx; // idTrx just created
			
			
		} catch(Exception $e) {

			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			
		}
		
		
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($salida));
		
	}
	
	
	
	
	
	/**
	 * Obtiene detalle de la transacción en función del comm y usuario
	 */
	public function getDetailsByUserExtAndComm() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			// Support two ways to get data from POST
			$idCommerce = "";
			$idUserExternal = "";
			if(!empty($_POST)) {
				$idCommerce = trim($this->input->post("idCommerce"));
				$idUserExternal = trim($this->input->post("idUserExternal"));
			} else {
				$post = $this->sanitize->inputParams(true, true, false);
				$idCommerce = $post->idCommerce;
				$idUserExternal = $post->idUserExternal;
			}
			
			if(empty($idCommerce) || empty($idUserExternal))
				throw new Exception("No se han definido todos los campos requeridos", 1101);
			
			log_message("debug", "REQUEST: " . __METHOD__ . " -> ". print_r($this->input->post(NULL, TRUE), TRUE));
			
			// Busca detalle	
			$salida->result = $this->oneclick_model->getDetailsByUserExternalAndComm($idCommerce, $idUserExternal, self::OK_FINISH_TRX_ONECLICK);
			
			/*if(empty($salida->result)) {
				throw new Exception("No se ha encontrado cuenta activa asociada al usuario", 1102);
			}*/
			
			// OK
			$salida->code = 0;

		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
		}
		
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($salida));
		
	}
	
	
	
	
	
	
	
	
	
	// ------------------------- LÓGICA ANTIGUA, QUEDARÁ DEPRECATED -------------------------
	
	
	
	/**
	 * Elimina al usuario Oneclick
	 */
	public function removeUser() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			// Recibe los parámetros por POST
			$tbkUser = trim($this->input->post("tbkUser"));
			$username = trim($this->input->post("username"));
	
			if(empty($tbkUser) || empty($username))
				throw new Exception("No se han definido todos los campos requeridos", 1001);
			
			log_message("debug", "REQUEST: " . __METHOD__ . print_r($this->input->post(NULL, TRUE), TRUE));

			
			// ----------------------------------------
			// Crea transacción en motor de pagos (trx)
			// ----------------------------------------
			/*$o = new stdClass();
			
			$o->idStage = self::NEW_TRX_ONECLICK;
			
			$o->trx = random_string("alnum", parent::MAX_N_TRX_WP);

			$o->idUserExternal = 0;
			$o->idApp = 0;
			$o->idPlan = 0;
			$o->idCountry = "CL";
			$o->urlOk = $post->UrlOk;
			$o->urlError = $post->UrlError;
			$o->urlNotify = $post->UrlNotify;
			$o->idCommerce = $oComm->idCommerce;
			$o->idPaymentType = $post->PaymentType;
			$o->codExternal = $post->CodExternal;
			$o->oldFlow = 0;
			$o->creationDate = date("Y-m-d H:i:s");

			log_message("debug", print_r($o, TRUE)); // log de parámetros iniciales
			
			// CREA NUEVA TRANSACCIÓN EN EL MOTOR
			$idTrx = $this->core_model->newTrx($o);
			if(is_null($idTrx)) throw new Exception("No se pudo iniciar la transacción en el sistema", 1003);*/
			
			// ---------------------------------------
			// Parámetros iniciales de trx en Oneclick
			// ---------------------------------------
			// Inicializa proceso con Transbank
			/*$oOneclick = new stdClass();
			$oOneclick->idTrx = $idTrx;
			$oOneclick->creationDate = date("Y-m-d H:i:s");
			
			// Crea registro en la tabla de Oneclick
			$idOneclickTrx = $this->oneclick_model->initTrx($oOneclick);
			if(is_null($idOneclickTrx)) throw new Exception("No se pudo almacenar la información para Oneclick. ".print_r($oOneclick, TRUE), 1004);*/
			
			// ---------------------------------------------
			// Invoca librería Oneclick -> removeUser()
			// ---------------------------------------------
			
			
			$this->load->library('oneclicklib');
			$removeUser = $this->oneclicklib->removeUser($tbkUser, $username);
			if(empty($removeUser)) {
				//$this->core_model->updateStageTrx($idTrx, self::FAILED_TRX_ONECLICK);
				throw new Exception("La cuenta seleccionada no existe o ya ha sido eliminada.", 1005);
			}
			
			// Recibe el token y la url a donde hacer POST con este
			//$token = $initTrxOneclick->token;
			//$urlWebpay = $initTrxOneclick->urlWebpay;
			
			// Actualiza etapa de transacción y oneclick
			//$this->core_model->updateStageTrx($idTrx, self::POST_TRX_ONECLICK);
			
			/*$upd = new stdClass();
			$upd->token = $token;
			$res = $this->oneclick_model->updateTrx($idOneclickTrx, $upd);
			if(!$res) throw new Exception("No se pudo actualizar la información del token", 1006);*/
			
			//$this->_postToken($token, $urlWebpay);
			$salida->code = 0;
			$salida->message = "La cuenta ha sido eliminada exitosamente";

			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
		}
		
		echo json_encode($salida);
		
	}
	
	
	/**
	 * Reversa transacción en Oneclick
	 */
	public function reverse() {
		
		$salida = new stdClass();
		$salida->code = -1;
		$salida->message = "";
		$salida->result = NULL;
		
		try {
			
			// Recibe los parámetros por POST
			$buyOrder = trim($this->input->post("buyOrder"));
	
			if(empty($buyOrder))
				throw new Exception("No se han definido todos los campos requeridos", 1001);

			// ---------------------------------------------
			// Invoca librería Oneclick -> reverse()
			// ---------------------------------------------
			
			// Multi-code support. If transbank code is sent, will use it, otherwise
			// codes used will be setted by default
			
			$oTrx = $this->oneclick_model->getTrxDetailsByBuyOrder($buyOrder);
			
			// Find transbank code
			$oCommPT = $this->commerceptv2_model->findByAttrs([
                    'cpt.idCommerce'    => $oTrx->idCommerce,
                    'cpt.idPaymentType'	=> $oTrx->idPaymentType
                ]);
			
			$this->load->library('oneclicklib');
			if(!empty($oCommPT->ownPaymentCode)) {
				
				// Use prefix attribute to select folder with certificates
				$oCommerce = $this->core_model->getCommerceById($oTrx->idCommerce);
				$prefix = strtoupper($oCommerce->prefix);
			
				$this->oneclicklib->setConfiguration(
					$oCommPT->ownPaymentCode,
					str_replace("{COMM}", $prefix, $this->config->item("OneclickPrivateKey")),
					str_replace("{COMM}", $prefix, $this->config->item("OneclickCertFile")),
					str_replace("{COMM}", $prefix, $this->config->item("OneclickCertServer")));

			}
			
			$reverseTrx = $this->oneclicklib->reverse($buyOrder);
			
			if(empty($reverseTrx)) throw new Exception("No se pudo procesar la solicitud de reversa.", 1005);
			if(!$reverseTrx->reversed) throw new Exception("La transacción no existe o ya ha sido reversada.", 1005);
			
			$salida->code = 0;
			$salida->message = "La transacción ha sido reversada exitosamente";
			$salida->result = $reverseTrx->reverseCode;
			
			$upd = [
				"idApp"		=> $salida->result,
				"idStage"	=> self::REVERSED_TRX_ONECLICK
			];
				
			$this->core_model->updateTrx($oTrx->idTrx, (object)$upd); 
			
		} catch(Exception $e) {
			$salida->code = $e->getCode();
			$salida->message = $e->getMessage();
			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
		}
		
		echo json_encode($salida);
		
	}
	
	
	
	public function result($res) {
		echo "El proceso ha terminado inesperadamente con $res";
	}
		
	
	
	
	/**
	 * Cuando la transacción no es válida (no se puede certificar contra Transbank)
	 */
	public function reject($token) {
		
		$oOneclick = $this->oneclick_model->getTrxByToken($token);
		$oTrx = $this->core_model->getTrxById($oOneclick->idTrx);

		$data["buyOrder"] = $oTrx->trx;
		$data["errorUrl"] = $oTrx->urlError;
		
		$this->load->view("oneclick/reject", $data);
	}
		

	// ---------------- PRIVATE METHODS --------------------
	
	/**
	 * Hace envío del token a url por POST, a través de variable TBK_TOKEN
	*/
	private function _postToken($token, $url) {
		
		try {
			
			$data["token"] = $token;
			$data["url"] = $url;
			
			$this->load->view("oneclick/post_token", $data);
			
		} catch(Exception $e) {
			$this->_error($e->getMessage());
		}
	
	}
	
}
