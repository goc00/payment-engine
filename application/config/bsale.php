<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
|--------------------------------------------------------------------------
| Host
|--------------------------------------------------------------------------
|
*/
// TRUE = Desarrollo
// FALSE = Producción
$config['BSaleDev'] = FALSE;
/*
|--------------------------------------------------------------------------
| Host
|--------------------------------------------------------------------------
|
*/
$config['BSaleHost'] = 'https://api.bsale.cl/';
/*
|--------------------------------------------------------------------------
| Token (ambiente)
|--------------------------------------------------------------------------
|
*/
if(!$config['BSaleDev']) {
	$config['BSaleAccessToken'] = '257af0036b86b73fc04d9cf9872713e2754b60d5'; // PRODUCCIÓN
} else {
	$config['BSaleAccessToken'] = '94be7cf14628254b331b5e8b855b7098db4bdbce'; // DEMO
}
/*
|--------------------------------------------------------------------------
| URL servicio, accesos API
|--------------------------------------------------------------------------
|
*/
$config['BSaleServiceA'] = $config['BSaleHost'].'v1/clients.json';
$config['BSaleServiceB'] = $config['BSaleHost'].'v1/clients/1.json';
$config['BSaleServiceTicket'] = $config['BSaleHost'].'v1/documents.json';
$config['BSaleServiceOffice'] = $config['BSaleHost'].'/v1/offices.json';
$config['BSaleServicePriceList'] = $config['BSaleHost'].'/v1/price_lists.json';
$config['BSaleServiceTaxes'] = $config['BSaleHost'].'/v1/taxes.json';
$config['BSaleServiceDocumentType'] = $config['BSaleHost'].'v1/document_types/1.json';
$config['BSaleServicePaymentType'] = $config['BSaleHost'].'v1/payment_types.json';
/*
|--------------------------------------------------------------------------
| Identificadores de atributos para generar boleta electrónica
|--------------------------------------------------------------------------
|
*/
if(!$config['BSaleDev']) {
	// PRODUCCIÓN
	$config['BSaleDocumentType'] = 1;
	$config['BSaleOffice'] = 1; // casa matriz
	$config['BSalePriceList'] = 2; // Lista de Precios Base
	$config['BSaleTax'] = 1; // IVA, 19%
	$config['BSalePaymentType'] = 2; // tipo de pago (tarjeta de crédito)
} else {
	// DEMO
	$config['BSaleDocumentType'] = 1; // tipo de documento: boleta = 1
	$config['BSaleOffice'] = 2; // casa matriz
	$config['BSalePriceList'] = 83; // pruebas
	$config['BSaleTax'] = 1; // pruebas
	$config['BSalePaymentType'] = 27;
}