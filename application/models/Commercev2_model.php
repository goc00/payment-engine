<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Commercev2_model extends CI_Model
{
    /**
     * Table's name
     *
     * @var string
     */
    private $tableName = 'commerce';

    /**
     * Primary Key's name
     *
     * @var string
     */
    private $primaryKey = 'idCommerce';

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
        return ($query->num_rows() > 0) ? $query->row() : null;
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
        $query = $this->db->where($attr, $value)->get($this->tableName);
        return ($query->num_rows() > 0) ? $query->row() : null;
    }

    /**
     * Find by multiple attributes
     *
     * @param $values
     * @return object|null
     */
    function findByAttrs($values)
    {
        $query = $this->db->where($values)
            ->get($this->tableName);

        return ($query->num_rows() > 0)
            ? $this->sanitize->setStandards($query->result()) : null;
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
}