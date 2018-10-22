<?php class Pin_model extends CI_Model {

    function __construct() {
		parent::__construct();
    }
	
	// Pines por el tipo
    function getPinsByType($idType, $idState) {
		
		$arr = array("idType" => $idType, "idState" => $idState);
		
		$res = $this->db->get_where("pin", $arr);
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
    }
	
	// Reserva pin, cambiando su estado y relacionándolo a trx
	function reservePin($idPin, $idState, $token, $externalCode) {
		$data = array("idState" => $idState, "token" => $token, "externalCode" => $externalCode, "used_date" => date("Y-m-d H:i:s"));
		$this->db->where("idPin", $idPin);
		return $this->db->update("pin", $data);
	}
	
	// Obtiene pin por su ID
	function getPinById($idPin) {
		$res = $this->db->get_where("pin", array("idPin" => $idPin));
		if($res->num_rows() > 0) return $res->row();
		else return NULL;
	}
	
	// Obtiene tickets por token
	function getPinsByToken($token) {
		
		$arr = array("token" => $token);
		
		$res = $this->db->get_where("pin", $arr);
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
    }
	
	
	// Obtiene tickets por externalCode
	function getPinsByExternalCode($externalCode) {
		
		$this->db->select("*");
		$this->db->from("pin");
		$this->db->join("type", "pin.idType = type.idType");
		$this->db->join("contentprovider", "type.idContentProvider = contentprovider.idContentProvider");
		$this->db->where("externalCode = $externalCode AND
						token = (
									SELECT MAX(token)
									FROM pin
									WHERE externalCode = $externalCode
								)
						");
		
		$res = $this->db->get();
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
    }
	
	// Tickets por fecha y estado
	function getPinsReservedByDateExtCode($externalCode, $actualDate, $idState, $idType = NULL) {
		
		$arr = array();
		if(is_null($idType)) {
			$arr = array("DATE(used_date)" => $actualDate, "idState" => $idState, "externalCode" => $externalCode);
		} else {
			$arr = array("DATE(used_date)" => $actualDate, "idState" => $idState, "externalCode" => $externalCode, "idType" => $idType);
		}
		
		
		$res = $this->db->get_where("pin", $arr);
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
    }
	
	
	// Se trae todos los pines de un externalCode, además, puede traer por intervalos a través
	// del total y offset recibidos.
	function getPinesByExtCode($externalCode, $total = NULL, $offset = NULL) {
		
		$this->db->select("p.*");
		$this->db->from("pin p");
		$this->db->where("p.externalCode = $externalCode");
		$this->db->order_by("p.used_date", "DESC");
		
		if(is_null($total) && is_null($offset)) {
			// Significa que se debe traer todo, así que no hace ningún filtro
		} else if(!is_null($total) && is_null($offset)) {
			// Se trae una X cantidad del total, desde el inicio del dataset
			$this->db->limit($total);
		} else if(!is_null($total) && !is_null($offset)) {
			$this->db->limit($total, $offset);
		}

		$res = $this->db->get();
		
		if($res->num_rows() > 0) return $res->result();
		else return NULL;
		
	}
	
	
	function createPin($o) {
		if($this->db->insert("pin", $o))
			return $this->db->insert_id();
            
        return NULL;
	}
	
	
	/**
	 * Transacciones
	 */
	function startTrx() {
		$this->db->trans_begin();
	}
	function commitTrx() {
		$this->db->trans_commit();
	}
	function rollbackTrx() {
		$this->db->trans_rollback();
	}
}

