<?php
class Fortysix_model extends CI_Model {

	private $table = "fortysixdegreestrx";

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
	 * Actualiza valores de la trx 46 grados
	 *
	 * @param	$idFortySixDegreesTrx		ID de la transacción
	 * @param	$o							Valores (atributos) a actualizar
 	 *
	 * @return boolean
	 */
	function updateTrx($idFortySixDegreesTrx, $o) {
		$o->modificationDate = date("Y-m-d H:i:s");
		$this->db->where("idFortySixDegreesTrx", $idFortySixDegreesTrx);

		return $this->db->update($this->table, $o);
	}

	/**
	 * Obtiene el TRX por el código trx
	 *
	 * @param	$idTrx
 	 *
	 * @return Object(FortySixDegreesTrx)
	 */
	function getByIdTrx($idTrx) {
		$res = $this->db->get_where($this->table, array("idTrx" => $idTrx));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}


	
}

