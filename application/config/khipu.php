<?php
$DEV = TRUE;
/*
|--------------------------------------------------------------------------
| Credenciales integración Khipu
|--------------------------------------------------------------------------
|
*/
if(!$DEV) {
	
	// Producción
	$config['KhipuReceiverId']		= '102834';
	$config['KhipuSecret']			= '5236bac339213e9e49060a658b8a4ba26ee99bf1';
	
} else {
	
	// Desarrollo
	$config['KhipuReceiverId']		= '102840';
	$config['KhipuSecret']			= 'ba500bd95cbe0eee0e4ab0b535be10769337ee8a';
	
}
/*
|--------------------------------------------------------------------------
| Servicios (son los mismos para producción y desarrollo)
|--------------------------------------------------------------------------
|
*/
$config['KhipuEndpoint']			= "https://khipu.com/api/2.0/payments";