<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Funciones {

    public function formatFecha($str, $conFecha = FALSE) {
		$str = trim($str);
		if(is_null($str) || $str == "") {
			return "";
		} else {
			$formato = !$conFecha ? "d/m/Y" : "d/m/Y H:i:s"; 
			return date($formato, strtotime(trim($str)));
		}
    }
	public function strToDate($str, $onlyDate = FALSE) {
		if($str == "") {
			return NULL;
		} else {
			//$f = DateTime::createFromFormat('d/m/Y h:i:s', trim($str));
			//return $f->format('Y-m-d h:i:s');
			$formato = $onlyDate ? "Y-m-d" : "Y-m-d h:i:s";
			return date($formato, strtotime(str_replace("/", "-", trim($str))));
		}
	}
	
	public function setNull($o) {
		
		foreach($o as $key => $value) {
			$comparar = NULL;
			
			// Aprovecha también de formatear cualquier intento de XSS
			//$this->load->helper('security');
			$CI =& get_instance();
			$CI->load->helper('security'); // load library 
			
			switch(gettype($value)) {
				case "integer":
				case "double":
					$comparar = 0;
					break;
				case "string":
					$value = htmlspecialchars($CI->security->xss_clean(trim($value)));
					$comparar = "";
					break;
			}
			
			if($value == $comparar) {
				$o->$key = NULL;
			}
		}
		
		return $o;
	}
	
	public function sanitizar($str) {
		$CI =& get_instance();
		return htmlspecialchars($CI->security->xss_clean(trim($str)));
	}

	/**
	 * Petición a través de cURL (se utiliza en prácticamente todas las implementaciones)
	 */
	public function doPost($service, $params) {
		// Configura cabeceras
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json'
		);
		
		$curl = curl_init($service);
		
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
		
		$exec = curl_exec($curl);
		
		curl_close($curl);
		
		log_message("debug", $exec);
		
		return $exec; 
	}
	
}

/* End of file Someclass.php */