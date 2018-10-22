<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
|--------------------------------------------------------------------------
| URLs de servicio
|--------------------------------------------------------------------------
|
*/
$config['PayPalHost'] = "https://api.paypal.com";
$config['PayPalServiceOAuth'] = $config['PayPalHost']."/v1/oauth2/token";
$config['PayPalServicePlans'] = $config['PayPalHost']."/v1/payments/billing-plans";
$config['PayPalServiceAgreements'] = $config['PayPalHost']."/v1/payments/billing-agreements";
// NVP
// Desarrollo
$config['PayPalAPICertificate'] = "https://api.sandbox.paypal.com/nvp";
$config['PayPalAPISignature'] = "https://api-3t.sandbox.paypal.com/nvp";
$config['PayPalAuthorization'] = "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token={TOKEN}";
// Producción
/*$config['PayPalAPICertificate'] = "https://api.paypal.com/nvp";
$config['PayPalAPISignature'] = "https://api-3t.paypal.com/nvp";
$config['PayPalAuthorization'] = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token={TOKEN}";*/
/*
|--------------------------------------------------------------------------
| Credenciales
|--------------------------------------------------------------------------
|
*/
$config['PayPalAccount'] = "gaston.orellana-facilitator@live.cl";
$config['PayPalClientID'] = "AchWxV5CliIMj2uOapiIAb12KETMoWmih8CgZo2WmbCad7qzgOOXtyMVzxm3vv6hjMioAz-hJMk33Uni";
$config['PayPalSecret'] = "EPiguIAkMvwBh1tJa4_ykkZTQnlhT0iMZY-HKD-SqOj1fM_r7PDom7SpoJrv7sDvEnx25jrGn4tlK8mP";
// NVP
// Desarrollo
//$config['PayPalUsernameNvp'] = "gaston.orellana-facilitator_api1.live.cl";
//$config['PayPalPasswordNvp'] = "RGKY6SPE2VH78LQ2";
//$config['PayPalSignatureNvp'] = "AFcWxV21C7fd0v3bYYYRCpSSRl31Au7x9K92qaa6800IIYA.hn3yPbrF";
$config['PayPalUsernameNvp'] = "gorellana-facilitator_api1.3gmotion.com";
$config['PayPalPasswordNvp'] = "XXPXVHADEH64AB4U";
$config['PayPalSignatureNvp'] = "AeDjyYJCtVqRAuolGHvLpnQMhARcAlWvX5fCIhENEEYvTZWTBR4wWyrU";

// Producción
/*$config['PayPalUsernameNvp'] = "gorellana_api1.3gmotion.com";
$config['PayPalPasswordNvp'] = "6LWRJZGCK2YHJ9SM";
$config['PayPalSignatureNvp'] = "AFcWxV21C7fd0v3bYYYRCpSSRl31AMOYDQOd.kWKIEgQbhmOG0adfdkS";*/
$config['PayPalVersionNvp'] = "86";
/*
|--------------------------------------------------------------------------
| URL de return(post result de) y final
|--------------------------------------------------------------------------
|
*/
$config['PaypalReturnUrl'] = "paypal/notify";
$config['PaypalFinalUrl'] = "paypal/final";
/*
|--------------------------------------------------------------------------
| Configuración común
|--------------------------------------------------------------------------
|
*/
$config['PaypalPlanName'] = "PRPP - {PRODUCT_NAME}";
$config['PaypalPlanDescription'] = "Pago Recurrente PayPal";
$config['PaypalPlanType'] = "FIXED"; // FIXED, INFINITE
$config['PaypalPlanAmountCurrency'] = "USD";
$config['PaypalPlanChargeModelType'] = "SHIPPING"; // SHIPPING, TAX
//$config['PaypalPlanMaxFailAttempts'] = "0";
//$config['PaypalPlanAutoBillAmount'] = "YES";
$config['PaypalPlanInitialFailAmountAction'] = "CANCEL"; // CONTINUE, CANCEL
$config['PaypalAgreementName'] = "Acuerdo de Inscripción";
$config['PaypalAgreementDescription'] = "Acuerdo de Inscripción Mensual";
$config['PaypalMaxFailedPayments'] = "3";

$config['PaypalAddressLine1'] = "Av. Pérez Valenzuela 1635";
$config['PaypalAddressLine2'] = "Providencia";
$config['PaypalAddressCity'] = "Santiago";
$config['PaypalAddressState'] = "RM";
$config['PaypalAddressPostalCode'] = "7500028";
$config['PaypalAddressCountryCode'] = "CL";

/*
|--------------------------------------------------------------------------
| Configuración plan TRIAL
|--------------------------------------------------------------------------
|
*/
$config['PaypalTrial'] = TRUE; // Activa o desactiva creación de plan tipo trial
$config['PaypalPeriodTrial'] = "Month";
$config['PaypalFrecuencyTrial'] = "3";
$config['PaypalTotalCyclesTrial'] = "1";
/*
|--------------------------------------------------------------------------
| Configuración plan REGULAR (pago recurrente efectivo)
|--------------------------------------------------------------------------
|
*/
$config['PaypalPlanRegularName'] = "Standard Plan";
$config['PaypalPlanRegularType'] = "REGULAR"; // TRIAL, REGULAR
$config['PaypalPlanRegularFrequencyInterval'] = "1";
$config['PaypalPlanRegularFrequency'] = "Month";
$config['PaypalPlanRegularCycles'] = "1"; // CADA 1 MES



