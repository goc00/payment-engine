<?php

class MY_Controller extends CI_Controller {
	
	// Core
	const CLP		= 1;
	const USD		= 2;
	const NUEVA_TRX	= 1;
	
	// Tipos de Pago
	const ID_WEBPAY				= 1;
	const ID_OTC				= 2;
	const ID_OPERATOR			= 3;
	const ID_PAYPAL				= 4;
	const ID_WEBPAY_PLUS		= 5;
	
	// WebPay PatPass
	const MAX_N_TRX_WP			= 18;						// Caracteres máximos para la generación de trx en wp
	const TRX_OK				= 1;
	const NUEVA_TRX_WP			= 2;						// ID nueva transacción WP (PatPass)
	const FALLO_INIT_TRX_WP		= 3;
	const POST_TOKEN_WP			= 4;
	const RETORNA_TOKEN_WP		= 5;
	const FALLO_GETTRXRES_WP	= 6;
	const VALIDA_CON_TRX_WP		= 7;
	const OK_NO_RESP_NOTIFY		= 8;
	const OK_ALL				= 9;
	const ANULADO_TRX_WP		= 13;
	const EXP_DATE_WP			= "2017-12-31";				// Fecha de expiración para contratos PatPass
	
	// Operator
	const OK_OPE				= 10;
	const ERR_OPE				= 11;
	
	// PayPal
	
	// WebPay Normal
	const NEW_TRX_WPP			= 18;
	
	function __construct() {
		parent::__construct();
	}
}

