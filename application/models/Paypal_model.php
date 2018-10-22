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
	
	/**
	 * Obtiene todos los perfiles de pago recurrente (paypal) del sistema
	 */
	function getAllProfiles() {
		$this->db->select("ppp.*");
		$this->db->from("paypaltrxnvpprofile ppp");
		$this->db->join("paypaltrxnvpdetail ppd", "ppp.idPayPalTrxNvpDetail = ppd.idPayPalTrxNvpDetail");
		$this->db->join("paypaltrxnvp pp", "ppd.idPayPalTrxNvp = pp.idPayPalTrxNvp");
		$this->db->join("trx t", "pp.idTrx = t.idTrx");
		$this->db->where("ppp.profileId IS NOT NULL AND t.idCommerce > 2");
		$res = $this->db->get();
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	/**
	 * Busca en la paypal history
	 */
	/*function getHistory($idPayPalTrxNvpProfile, $status, $nextBillingDate, $lastPaymentDate) {
		$where =  array(
					"idPayPalTrxNvpProfile" => $idPayPalTrxNvpProfile,
					"status" => $status,
					"nextBillingDate" => $nextBillingDate,
					"lastPaymentDate" => $lastPaymentDate
				);
		
		$res = $this->db->get_where("paypaltrxnvpprofilehistory", $where);
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}*/
	function getHistory($idPayPalTrxNvpProfile, $status, $nextBillingDate, $lastPaymentDate) {
		
		$this->db->select("pph.*");
		$this->db->from("paypaltrxnvpprofilehistory pph");
		$this->db->join("paypaltrxnvpprofile ppp", "pph.idPayPalTrxNvpProfile = ppp.idPayPalTrxNvpProfile");
		$this->db->join("paypaltrxnvpdetail ppd", "ppp.idPayPalTrxNvpDetail = ppd.idPayPalTrxNvpDetail");
		$this->db->join("paypaltrxnvp pp", "ppd.idPayPalTrxNvp = pp.idPayPalTrxNvp");
		$this->db->join("trx t", "pp.idTrx = t.idTrx");
		$this->db->where("pph.idPayPalTrxNvpProfile = $idPayPalTrxNvpProfile
							AND pph.status = '$status'
							AND pph.nextBillingDate = '$nextBillingDate'
							AND pph.lastPaymentDate = '$lastPaymentDate'
							AND t.idCommerce > 2"); // ids anteriores son solo de pruebas
							
		$res = $this->db->get();
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	
	
	
	
	
	/**
	 * Graba en la paypal history
	 */
	function saveHistory($data) {	
		if($this->db->insert("paypaltrxnvpprofilehistory", $data))
			return $this->db->insert_id();
		log_message("error", $this->db->_error_message());
        return NULL;
    }
	
	/**
	 * Billing PayPal
	 */
	function billing() {
		$this->db->select("pph.*, t.idCommerce, t.idCountry");
		$this->db->from("paypaltrxnvpprofilehistory pph");
		$this->db->join("paypaltrxnvpprofile ppp", "pph.idPayPalTrxNvpProfile = ppp.idPayPalTrxNvpProfile");
		$this->db->join("paypaltrxnvpdetail ppd", "ppp.idPayPalTrxNvpDetail = ppd.idPayPalTrxNvpDetail");
		$this->db->join("paypaltrxnvp pp", "ppd.idPayPalTrxNvp = pp.idPayPalTrxNvp");
		$this->db->join("trx t", "pp.idTrx = t.idTrx");
		$this->db->where("DATE(pph.creationDate) = DATE(now()) - 1");
		$res = $this->db->get("");
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
}

