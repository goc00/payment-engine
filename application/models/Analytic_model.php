<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Analytic_model extends CI_Model
{
    /**
     * Table's name
     *
     * @var string
     */
    private $tableName = 'analytics anal';

    /**
     * Primary Key's name
     *
     * @var string
     */
    private $primaryKey = 'idCommercePt';

    function __construct()
    {
        parent::__construct();

        $this->load->library('sanitize');
    }

    /**
     * Get all records
     *
     * @return object|null
     */
    function all()
    {
        $query = $this->db->get( $this->tableName );
        return ($query->num_rows() > 0) ? $this->sanitize->setStandards($query->result()) : null;
    }

    /**
     * Get by id
     *
     * @param integer $id
     * @return object|null
     */
    function find($id) {
        $query = $this->db->where($this->primaryKey, $id)->get($this->tableName);
        return ($query->num_rows() > 0) ?$this->sanitize->setStandards($query->row()) : null;
    }

    /**
     * Find between two keys
     *
     * @param $where
     * @param $orWhere
     * @return null|object
     */
    function findByOrWhere($where, $orWhere)
    {
        $query = $this->db
            ->where($where)
            ->or_where($orWhere)
            ->get($this->tableName);

        return ($query->num_rows() > 0)
            ? $this->sanitize->setStandards($query->result()) : null;
    }

    /**
     * @param array $data
     * @return null
     */
    function add($data) {
        $data['created_at'] = date('Y-m-d H:i:s');

        if ($this->db->insert('analytics', $data))
            return $this->db->insert_id();

        return null;
    }
}