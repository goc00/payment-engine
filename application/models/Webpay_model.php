<?php class Webpay_model extends CI_Model {

	private $table = "wptrxpatpass";
    private $lastError = "";

    function __construct() {
		parent::__construct();
		//$this->load->database();
    }

    public function getLastError() {
        return $this->lastError;
    }
	
	/**
	 * Regista el inicio de la transacción en el sistema
	 *
	 * @param	$data	Objeto con valores a insertar en la tabla
	 * @return ID del registro insertado
	 */
    function initTrx($data) {	
		if($this->db->insert($this->table, $data))
			return $this->db->insert_id();
            
        return NULL;
    }
	
	/**
	 * Actualiza valores de la trx webpay
	 *
	 * @param	$idWPTrxPatPass		ID de la transacción
	 * @param	$o					Valores (atributos) a actualizar
 	 *
	 * @return boolean
	 */
    function updateTrx($idWPTrxPatPass, $o) {
        $o->modificationDate = date("Y-m-d H:i:s");
        $this->db->where("idWPTrxPatPass", $idWPTrxPatPass);

        $res = $this->db->update("wptrxpatpass", $o);

        log_message("error", "updateTrx: " . print_r($this->db->error(), true));

        if(empty($res)) {
            $this->lastError = "Error en BD -> " . $this->db->error();
        }

        return $res;
    }
	
	/**
	 * Obtiene el TRX por el código trx
	 *
	 * @param	$token
 	 *
	 * @return Object(token)
	 */
	function getTrxByToken($token) {
		$res = $this->db->get_where("wptrxpatpass", array("token" => $token));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	function getTrxByBuyOrder($buyOrder) {
		$res = $this->db->get_where("wptrxpatpass", array("buyOrder" => $buyOrder));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	
	function getTrxByIdTrx($idTrx) {
		$res = $this->db->get_where("wptrxpatpass", array("idTrx" => $idTrx));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	
	/**
	 * Obtiene el Type de alguna de las tablas maestras para Webpay
	 *
	 * @param	$ref	Referencia para apuntar a la tabla correspondiente
	 * @param	$code	Código de webpay por el que se buscará el id
 	 *
	 * @return Object
	 */
	function getTypeXXByCode($ref, $code) {
		
		$tabla = "";
		
		switch($ref) {
			case "ptc":
				$tabla = "wppaymenttypecode";
				break;
			case "vci":
				$tabla = "wpvci";
				break;
			case "rc":
				$tabla = "wpresponsecode";
				break;
		}
		
		$res = $this->db->get_where($tabla, array("code" => $code));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	function getPTById($idPT) {
		$res = $this->db->get_where("wppaymenttypecode", array("idWPPaymentTypeCode" => $idPT));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}

	function getTrxByField($field, $value) {
		$res = $this->db->get_where("wptrxpatpass", array($field => $value, "idWPResponseCode" => 1));
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	function getTokenLastRow() {
		$this->db->select("token");
		$this->db->from("wptrxpatpass");
		$this->db->where("creationDate = (SELECT MAX(creationDate) FROM wptrxpatpass)");
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
}

