<?php class Core_model extends CI_Model {

    function __construct() {
		parent::__construct();
		//$this->load->database();
    }
	
	/**
	 * Obtiene los métodos de pago disponible
	 *
	 * @return ArrayList Listado de PaymentType
	 */
    function getAllPaymentType() {	
		$res = $this->db->get_where("paymenttype", array("active" => 1));
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
    }
	
	/**
	 * Obtiene todos los campos de un método de pago
	 *
	 * @param	$idPaymentType	ID del PaymentType
	 * @return ArrayList Listado de PaymentType
	 */
	function getFieldsByPayment($idPaymentType) {
		
		$this->db->select("f.*, ft.name as 'nameFt'");
		$this->db->from("field f");
		$this->db->join("fieldtype ft", "f.idFieldType = ft.idFieldType");
		$this->db->where(array("f.idPaymentType" => $idPaymentType));
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	/**
	 * Obtiene todos las clases relacionadas al field
	 *
	 * @param	$idField	ID del Field
	 * @return ArrayList Listado de Class
	 */
	function getClassesByIdField($idField) {
		
		$this->db->select("c.*");
		$this->db->from("class c");
		$this->db->join("fieldclass fc", "fc.idClass = c.idClass");
		$this->db->where(array("fc.idField" => $idField));
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	/**
	 * Verifica si el método de pago está habilitado para el comercio
	 *
	 * @param	$idPaymentType	ID del PaymentType
	 * @param	$commerceId		Código de comercio
 	 *
	 * @return Object
	 */
	function commHasPT($idPaymentType, $commerceId) {
		$res = $this->db->get_where("commercepayment",
									array("idPaymentType" => $idPaymentType, "commerceId" => $commerceId));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	/**
	 * Devuelve Commerce
	*/
	function getCommerceByCode($code) {
		$res = $this->db->get_where("commerce", array("code" => $code));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	function getCommerceById($idComm) {
		$res = $this->db->get_where("commerce", array("idCommerce" => $idComm));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	/**
	 * Comienza nueva transacción
	 *
	 * @param	$o	Objeto con la data para inserción
 	 *
	 * @return ID last insert / NULL
	 */
	function newTrx($o) {
		if($this->db->insert("trx", $o))
			return $this->db->insert_id();
            
        return NULL;
	}
	/**
	 * Obtiene el TRX por el código trx
	 *
	 * @param	$trx
 	 *
	 * @return Object(Trx)
	 */
	function getTrx($trx) {
		$res = $this->db->get_where("trx", array("trx" => $trx));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	function getTrxById($idTrx) {
		$res = $this->db->get_where("trx", array("idTrx" => $idTrx));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	/**
	 * Actualiza el stage de la transacción
	 *
	 * @param	$idTrx		ID de la transacción
	 * @param	$idStage	ID del stage al que será actualizada la trx
 	 *
	 * @return boolean
	 */
	function updateStageTrx($idTrx, $idStage) {
		$data = array("idStage" => $idStage, "modificationDate" => date("Y-m-d H:i:s"));
		$this->db->where("idTrx", $idTrx);
		return $this->db->update("trx", $data);
	}
	
	
	/**
	 * Actualiza valores de trx
	 *
	 * @param	$idTrx		ID de la transacción
	 * @param	$o			Valores (atributos) a actualizar
 	 *
	 * @return boolean
	 */
	function updateTrx($idTrx, $o) {
		$o->modificationDate = date("Y-m-d H:i:s");
		$this->db->where("idTrx", $idTrx);
		return $this->db->update("trx", $o);
	}
	
	
	/**
	 * Obtiene tipo de pago por id
	 */
	function getPTById($idPaymentType) {
		$res = $this->db->get_where("paymenttype", array("idPaymentType" => $idPaymentType));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	function getCommByCode($code) {
		$res = $this->db->get_where("commerce", array("code" => $code));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	function getCommById($idCommerce) {
		$res = $this->db->get_where("commerce", array("idCommerce" => $idCommerce));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	
	/**
	 * Transacciones
	 */
	function inicioTrx() {
		$this->db->trans_begin();
	}
	function commitTrx() {
		$this->db->trans_commit();
	}
	function rollbackTrx() {
		$this->db->trans_rollback();
	}
}

