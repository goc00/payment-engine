<?php class Paypal_plan_model extends CI_Model {

	private $table = "paypalplan";

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
	 * Actualiza valores de la trx
	 *
	 * @param	$id					ID de la transacción
	 * @param	$o					Valores (atributos) a actualizar
 	 *
	 * @return boolean
	 */
	function updateTrx($id, $o) {
		$this->db->where("idPayPalPlan", $id);
		return $this->db->update($this->table, $o);
	}
	
	/**
	 * Obtiene plan por id y tipo
	 */
	function getPayPalPlan($idPayPalTrx, $type) {
		$res = $this->db->get_where($this->table, array("idPayPalTrx" => $idPayPalTrx, "type" => $type));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
}

