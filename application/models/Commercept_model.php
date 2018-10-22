<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Commercept_model extends CI_Model 
{
    /**
     * Table's name
     *
     * @var string
     */
    private $tableName  = 'commercept';

    /**
     * Primary key's name
     *
     * @var string
     */
    private $primaryKey = 'idCommercePt';

    function __construct() {
		parent::__construct();
    }

    /**
     * Return all records
     *
     * @return mixed
     */
    function all()
    {
        $query = $this->db->get($this->tableName);
        return $this->modelResponse($query);
    }

    /**
     * Find record by Id
     *
     * @param Integer $id
     * @return mixed
     */
    function find($id)
    {
        $query = $this->db->where($this->primaryKey, $id)
            ->get($this->tableName);
        
        return $this->modelResponse($query);
    }

    /**
     * Find records by attribute
     *
     * @param String $key
     * @param String $value
     * @return mixed
     */
    function findByAttr($key, $value)
    {
        $query = $this->db->where($key, $value)
            ->get($this->tableName);
        
        return $this->modelResponse($query);
    }

    /**
     * Find records by attribute with like clause
     *
     * @param String $key
     * @param String $value
     * @return mixed
     */
    function likeByAttr($key, $value)
    {
        $query = $this->db->like($key, $value)
            ->get($this->tableName);
        
        return $this->modelResponse($query);
    }

    /**
     * Find records by attribute
     *
     * @param String $key
     * @param String $value
     * @return mixed
     */
    function findByCommerceAndPaymentType($commerce, $payment)
    {
        $query = $this->db->where('idCommerce', $commerce)
            ->where('idPaymentType', $payment)
            ->get($this->tableName);
        
        return $this->modelResponse($query, true);
    }

    /**
     * Generate response
     *
     * @param Object|Array $data
     * @return mixed
     */
    private function modelResponse($data, $row=false)
    {
        return ($data->num_rows() > 0) 
            ? ($row) ? $data->row() : $data->result() 
            : null;
    }
}