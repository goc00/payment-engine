<?php

class Payu_rec_model extends CI_Model
{
    private $table = 'payurectrx';

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


	function addError($data) {
		if($this->db->insert($this->table."_error", $data))
			return $this->db->insert_id();

        return NULL;
	}

	function addErrorDetail($data) {
		if($this->db->insert($this->table."_detail_error", $data))
			return $this->db->insert_id();

        return NULL;
	}
	

	/**
	 * Actualiza valores de la trx
	 *
	 * @param	$idPayUTrx		ID de la transacción
	 * @param	$o				Valores (atributos) a actualizar
 	 *
	 * @return boolean
	 */
	function updateTrx($idPayURecTrx, $o) {
		$o->modificationDate = date("Y-m-d H:i:s");
		$this->db->where("idPayURecTrx", $idPayURecTrx);

		return $this->db->update($this->table, $o);
	}


	/**
     * Find by attribute
     *
     * @param string $attr
     * @param string $value
     * @return object|null
     */
    function findByAttr($attr, $value)
    {
        $query = $this->db->where($attr, $value)->get($this->table);
        return ($query->num_rows() > 0) ? $query->row() : null;
    }

}