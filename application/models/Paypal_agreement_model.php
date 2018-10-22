<?php class Paypal_agreement_model extends CI_Model {

	private $table = "paypalagreement";

    function __construct() {
		parent::__construct();
    }
	
	/**
	 * Regista el inicio de la transacción en el sistema
	 *
	 * @param	$data	Objeto con valores a insertar en la tabla
	 * @return ID del registro insertado
	 */
    function insert($data) {	
		if($this->db->insert($this->table, $data))
			return $this->db->insert_id();
            
        return NULL;
    }
}

