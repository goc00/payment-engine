<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Stage_model extends CI_Model
{
    /**
     * Table's name
     *
     * @var string
     */
    private $tableName = 'stage';

    /**
     * Primary Key's name
     *
     * @var string
     */
    private $primaryKey = 'idStage';

    function __construct()
    {
        parent::__construct();
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
        return ($query->num_rows() > 0) ? $query->row() : null;
    }

}