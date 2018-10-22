<?php class Paypal_model extends CI_Model {

	private $table = "paypaltrx";
	private $tableNvp = "paypaltrxnvp";
	private $tableNvpDetail = "paypaltrxnvpdetail";
	private $tableNvpProfile = "paypaltrxnvpprofile";

    function __construct() {
		parent::__construct();
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
	function initTrxNvp($data) {	
		if($this->db->insert($this->tableNvp, $data))
			return $this->db->insert_id();
            
        return NULL;
    }
	
	/**
	 * Guarda los detalles de la transacción
	 */
	function saveDetails($data) {	
		if($this->db->insert($this->tableNvpDetail, $data))
			return $this->db->insert_id();
            
        return NULL;
    }
	
	function saveProfile($data) {	
		if($this->db->insert($this->tableNvpProfile, $data))
			return $this->db->insert_id();
            
        return NULL;
    }
	
	
	/**
	 * Actualiza valores de la trx
	 *
	 * @param	$id					ID de la transacción
	 * @param	$o					Valores (atributos) a actualizar
 	 *
	 * @return boolean
	 */
	function updateTrx($id, $o, $nvp = NULL) {
		$this->db->where("idTrx", $id);
		return $this->db->update(is_null($nvp) ? $this->table : $this->tableNvp, $o);
	}
	
	
	/**
	 * Obtengo PayPalTrx por el idTrx
	 */
	function getByIdTrx($idTrx, $nvp = NULL) {
		$res = $this->db->get_where(is_null($nvp) ? $this->table : $this->tableNvp, array("idTrx" => $idTrx));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	function getByToken($token, $nvp = NULL) {
		$res = $this->db->get_where(is_null($nvp) ? $this->table : $this->tableNvp, array("token" => $token));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
}

