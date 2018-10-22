<?php class Braintree_model extends CI_Model {

	private $table = "braintreetrx";

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
		return $this->db->update("wptrxpatpass", $o);
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
	
}

