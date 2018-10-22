<?php class Payment_type_model extends CI_Model {

	private $table = "paymenttype";

    function __construct() {
		parent::__construct();
    }
	
	/**
	 * Obtiene tipo de pago por prefijo
	 */
    function getByPrefix($prefix) {	
		
		$res = $this->db->get_where($this->table, array("prefix" => $prefix));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
    }
}

