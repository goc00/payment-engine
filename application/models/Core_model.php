<?php class Core_model extends CI_Model {

    function __construct() {
		parent::__construct();
		$this->load->library('sanitize');
    }
	
	/**
     * Find trxs related to user and commerce
     *
     * @param $values
     * @return object|null
     */
	function getTrxsByUserAndComm($idUserExternal, $idCommerce) {
		$query = $this->db->where(array(
							"idUserExternal" => $idUserExternal,
							"idCommerce" => $idCommerce))
				->order_by("idTrx", "desc")
				->get("trx");
					
		return ($query->num_rows() > 0)
            ? $this->sanitize->setStandards($query->result()) : null;
	}
	
	/**
	 * Obtiene los métodos de pago disponible
	 *
	 * @return ArrayList Listado de PaymentType
	 */
    function getAllPaymentType() {
		$this->db->select("*");
		$this->db->from("paymenttype");
		$this->db->where(array("active" => 1));
		$this->db->order_by("orden", "asc");
		//$res = $this->db->get_where("paymenttype", array("active" => 1));
		$res = $this->db->get();
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
	 * Obtiene transacción por su codExternal
	 *
	 * @param	$codExternal		Código externo de la transacción
 	 *
	 * @return Object
	 */
	function getTrxByCodExternal($codExternal) {
		$res = $this->db->get_where("trx", array("codExternal" => $codExternal));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	
	function getAllTrx() {
		$this->db->where("DATE(creationDate) >= '2016-07-07' AND modificationDate IS NOT NULL AND idPaymentType IS NOT NULL");
		$res = $this->db->get("trx");
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	/**
	 * Todas las transacciones de PatPass
	 * Se puede pasar como filtro el comercio
	 */
	function getAllTrxPatPass($idCommerce = NULL) {
		
		$this->db->select("t.*, w.cardHolderId, w.cardHolderName, w.cardHolderLastName1, w.cardHolderLastName2, w.cardHolderMail, w.serviceId, s.name, comm.name as 'name_commerce', pt.name as 'name_pt'");
		$this->db->from("trx t");
		$this->db->join("wptrxpatpass w", "w.idTrx = t.idTrx");
		$this->db->join("stage s", "s.idStage = t.idStage");
		$this->db->join("commerce comm", "comm.idCommerce = t.idCommerce");
		$this->db->join("paymenttype pt", "pt.idPaymentType = t.idPaymentType");
		
		$where = "t.modificationDate IS NOT NULL
				AND t.idStage IN(8,9,19,20)
				AND w.authorizationCode IS NOT NULL AND w.authorizationCode <> '000000'
				AND w.idWPResponseCode = 1";
		
		if(!is_null($idCommerce))
			$where .= " AND t.idCommerce = $idCommerce";
		
		$this->db->where($where);
		$this->db->order_by("t.creationDate", "DESC");
		
		$res = $this->db->get();
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	/**
	 * Datos de boleta BSale
	 */
	function getTrxBSale($idTrx) {
		
		$this->db->order_by("creationDate", "DESC");
		$res = $this->db->get_where("trxbsale", array("idTrx" => $idTrx));
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
	}
	
	// Encuentra boleta por idTrxBSale
	function getTrxBSaleByIdTrxBSale($idTrxBsale) {
		$res = $this->db->get_where("trxbsale", array("idTrxBsale" => $idTrxBsale));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	// Actualiza estado de trxbsale
	function markAsSent($idTrxBsale) {
		$data = array("sent" => 1);
		$this->db->where("idTrxBsale", $idTrxBsale);
		return $this->db->update("trxbsale", $data);
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
	 * Inserta información de boleta generada
	 */
	function newSaleTicket($o) {
		if($this->db->insert("trxbsale", $o))
			return $this->db->insert_id();
            
        return NULL;
	}
	
	
	/**
	 * Obtiene listado de métodos de pago por idCommerce
	 *
	 * @param	$idCommerce		ID de comercio
 	 *
	 * @return Array
	 */
	function listPaymentMethodsByIdCommerce($idCommerce) {
		
		$this->db->select("cpt.*, pt.name");
		$this->db->from("commercept cpt");
		$this->db->join("paymenttype pt", "cpt.idPaymentType = pt.idPaymentType");
		$this->db->where("cpt.idCommerce = $idCommerce AND pt.active = 1");
		$this->db->group_by("pt.orden", "ASC");
		
		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->result();
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

