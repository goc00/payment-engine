<?php class Payment_type_model extends CI_Model 
{
	private $table = "paymenttype";
	private $tableName = "paymenttype";

    function __construct() 
    {
		parent::__construct();
	    $this->load->library('sanitize');
    }

	/**
	 * Find record by Id
	 *
	 * @param integer $id primary key
	 *
	 * @return object|boolean
	 */
	public function find($id)
	{
		$query = $this->db->where($this->primaryKey, $id)
		                  ->get($this->tableName);

		return ($query->num_rows() > 0) ? $this->sanitize->setStandards($query->result()) : null;
	}

	/**
	 * Find records by attribute
	 *
	 * @param string $key key of the where
	 * @param string $value value of the where
	 *
	 * @return object|boolean
	 */
	public function findByAttr($key, $value)
	{
		$query = $this->db->where($key, $value)
		                  ->get($this->tableName);

		return ($query->num_rows() > 0) ? $this->sanitize->setStandards($query->result()) : null;
	}

	public function getStages($id)
	{
		$query = $this->db->select('s.name, s.description')
			->from("{$this->tableName} as pt")
			->join('stages as s', 'pt.idPaymentType = s.idPaymentType')
			->where('pt.idPaymentType', $id)
			->get();

		return ($query->num_rows() > 0) ? $this->sanitize->setStandards($query->result()) : false;
	}
	
	/**
	 * Obtiene tipo de pago por prefijo
	 */
    function getByPrefix($prefix)
    {
		$res = $this->db->get_where($this->table, array("prefix" => $prefix));
        
        if ($res->num_rows() > 0) 
            return $res->row();
        else 
            return NULL;
    }
	
	// Obtiene tipo de pago por ID
    function getById($idPaymentType) 
    {	
		$res = $this->db->get_where($this->table, array("idPaymentType" => $idPaymentType));
        
        if ( $res->num_rows() > 0 )
            return $res->row();
        else 
            return NULL;
	}
		
    function findByCommerceAndCountry($commerceCode, $countryAlpha2)
    {
        $query = $this->db
            ->select('pt.idPaymentType, pt.name, pt.description, pt.active')
            ->from('commercept')
            ->join('commerce as c', 'c.idCommerce = commercept.idCommerce')
            ->join("$this->table as pt", 'pt.idPaymentType = commercept.idPaymentType')
            ->join('country as c2', 'c2.idCountry = commercept.idCountry')
            ->where('c.code', $commerceCode)
            ->where('c2.iso3166_2', $countryAlpha2)
            ->get();
    
        return $this->modelResponse($query);
    }

    /**
     * Generate response
     *
     * @param Object|Array $data
     * @return mixed
     */
    private function modelResponse($data)
    {
        return ($data->num_rows() > 0) ? $data->result() : null;
    }
}

