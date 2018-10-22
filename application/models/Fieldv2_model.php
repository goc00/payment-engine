<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Fieldv2_model extends CI_Model
{
    /**
     * Table's name
     *
     * @var string
     */
    private $tableName = 'field f';

    /**
     * Primary Key's name
     *
     * @var string
     */
    private $primaryKey = 'idField';

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
        $query = $this->db->select('f.*, ft.name')
            ->from($this->tableName)
            ->join('commercept cpt', 'cpt.idCommercePt = f.idCommercePt')
            ->join('fieldtype ft', 'f.idFieldType = ft.idFieldType')
            ->where($attr, $value)
            //->group_by('pt.orden', 'ASC')
            ->get();

        return ($query->num_rows() > 0) ? $this->sanitize->setStandards($query->result()) : null;
    }

}