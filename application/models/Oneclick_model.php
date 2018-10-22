<?php
class Oneclick_model extends CI_Model {

	private $table = "oneclicktrx";

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
	 * Actualiza valores de la trx oneclick
	 *
	 * @param	$idOneclickTrx		ID de la transacción
	 * @param	$o					Valores (atributos) a actualizar
 	 *
	 * @return boolean
	 */
	function updateTrx($idOneclickTrx, $o) {
		$o->modificationDate = date("Y-m-d H:i:s");
		$this->db->where("idOneclickTrx", $idOneclickTrx);

		return $this->db->update($this->table, $o);
	}

	/**
	 * Obtiene el TRX por el código trx
	 *
	 * @param	$token
 	 *
	 * @return Object(token)
	 */
	function getTrxByToken($token) {
		$res = $this->db->get_where($this->table, array("token" => $token));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}


	/**
	 * Obtiene detalle del oneclick, en función del idTrx
	 * Este método se utiliza en la API
	 *
	 * @param	$idTrx
 	 *
	 * @return Object
	 */
	function getTrxDetailsByIdTrx($idTrx) {

		$this->db->select("oc.buyOrder, t.amount, oc.authorizationCode, oc.creationDate, oc.last4CardDigits, c.description");
		$this->db->from($this->table." oc");
		$this->db->join("trx t", "t.idTrx = oc.idTrx");
		$this->db->join("commerce c", "t.idCommerce = c.idCommerce");
		$this->db->where("oc.idTrx", $idTrx);

		$res = $this->db->get();

		return ( $res->num_rows() > 0 ) ? $res->row() : null;
	}

	/**
	 * Obtiene detalle del oneclick, en función del buyOrder
	 *
	 * @param	$idTrx
 	 *
	 * @return Object
	 */
	function getTrxDetailsByBuyOrder($buyOrder) {

		//$this->db->select("oc.idTrx, t.amount, oc.authorizationCode, t.codExternal");
		$this->db->select("t.codExternal, t.amount, t.idCommerce, t.idPaymentType, oc.idTrx, oc.authorizationCode");
		$this->db->from($this->table." oc");
		$this->db->join("trx t", "t.idTrx = oc.idTrx");
		$this->db->join("commerce c", "t.idCommerce = c.idCommerce");
		$this->db->where("oc.buyOrder", $buyOrder);

		$res = $this->db->get();

		if($res->num_rows() > 0) return $res->row();
		else return NULL;

	}


	/**
	 * Obtiene información de, si aplica, enrrolamiento del user external
	 * en el sistema de pagos. Debe verificar además con el comercio
	 *
	 * @param	$idCommerce			ID del comercio
	 * @param	$idUserExternal		ID de usuario externo
	 * @param	$idStage			(OPCIONAL) ID de la etapa de la transacción
 	 *
	 * @return Object
	 */
	function getDetailsByUserExternalAndComm($idCommerce, $idUserExternal, $idStage = NULL)
    {
		$this->db->select("oc.idTrx, oc.authorizationCode, oc.tbkUser, t.amount, t.codExternal");
		$this->db->from("trx t");
		$this->db->join($this->table." oc", "t.idTrx = oc.idTrx");
		$this->db->where('t.idCommerce', $idCommerce);
        $this->db->where('t.idUserExternal', $idUserExternal);
        $this->db->where('oc.tbkUser IS NOT NULL', NULL, FALSE);

		$res = $this->db->get();

		log_message("debug", $this->db->last_query());

		if($res->num_rows() > 0) {
			return $res->row(); // debe ser solo 1 registro
		} else {
			return NULL;
		}
	}
}

