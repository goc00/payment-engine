<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron extends CI_Controller {
	
	public function __construct() {
		parent::__construct();
		$this->load->model('paypal_model', '', TRUE);
	}
	
	public function doPaypalBilling() {
		try {
	
			$salto = "\r\n";
			$separator = ";";

			$hoy = date("Ymd") - 1;
			$path = FCPATH."payments/";
			$nombre = "paypal_".$hoy.".csv";
			
			$contenido = $this->paypal_model->billing();
			
			// Preparación de contenido para archivo
			$out = array(
					"idPayPalTrxNvpProfileHistory",
					"idPayPalTrxNvpProfile",
					"status",
					"autoBilloutAmt",
					"aggregateAmt",
					"amt",
					"regularAmt",
					"taxAmt",
					"regularTaxAmt",
					"nextBillingDate",
					"numCyclesCompleted",
					"outstandingBalance",
					"failedPaymentCount",
					"lastPaymentDate",
					"lastPaymentAmt",
					"ack",
					"version",
					"build",
					"correlationId",
					"errCode",
					"errMsg",
					"creationDate",
					"idCommerce",
					"idCountry"
				);
			$salida = implode($separator, $out).$salto;
			$l = count($out);
			if(!is_null($contenido)) {
				foreach($contenido as $row) {
					$rowArr = array();
					for($i=0;$i<$l;$i++) {
						$rowArr[] = $row->$out[$i]; 
					}
					$salida .= implode($separator, $rowArr).$salto;
				}
			}

			$archivo = $path.$nombre;
			$res = file_put_contents($archivo, $salida);
			
			if($res === FALSE) {
				throw new Exception("El archivo de conciliación no pudo ser generado", 1001);
			}
			
			echo "Proceso finalizado correctamente ".$hoy;

		} catch(Exception $e) {
			$err = "CRON (PayPal) > " . $e->getCode().": ".$e->getMessage();
			log_message("error", $err);
			echo $e->getCode().": ".$err;
		}
	}

}
