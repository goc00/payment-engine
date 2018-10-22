<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Commerceptv2_model extends CI_Model
{
    /**
     * Table's name
     *
     * @var string
     */
    private $tableName = 'commercept cpt';

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
        $query = $this->db->select('cpt.idPaymentType, cpt.ownPaymentCode, cpt.key, cpt.secret, pt.name, pt.description, pt.active')
            ->from($this->tableName)
            ->join('paymenttype pt', 'cpt.idPaymentType = pt.idPaymentType')
            ->where($attr, $value)
            ->where('pt.active', '1')
            ->group_by('pt.orden', 'ASC')
            ->get();

        return ($query->num_rows() > 0) ? $this->sanitize->setStandards($query->result()) : null;
    }

    /**
     * Find by multiple attributes
     *
     * @param $values
     * @return object|null
     */
    function findByAttrs($values)
    {
        $query = $this->db->select('cpt.idCommercePt, cpt.idPaymentType, cpt.ownPaymentCode, cpt.key, cpt.secret, pt.name, pt.description, pt.active, c.idCountry as countryId, c.name as countryName')
            ->from($this->tableName)
            ->join('paymenttype pt', 'cpt.idPaymentType = pt.idPaymentType')
            ->join('country c', 'cpt.idCountry = c.idCountry')
            ->join('commerce cm', 'cpt.idCommerce = cm.idCommerce')
            ->where($values)
            ->where('pt.active', '1')
            ->group_by('pt.orden', 'ASC')
            ->get();

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