<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transactionv2_model extends CI_Model
{
    /**
     * Table's name
     *
     * @var string
     */
    private $tableName = 'trx';

    /**
     * Primary Key's name
     *
     * @var string
     */
    private $primaryKey = 'idTrx';

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
    function findByAttrs($values) {

        $where = [];
        $select = "";
        foreach($values as $key => $val) { $where["t.$key"] = $val; }

        $query = $this->db->select("t.*,
                                    oc.buyOrder as 'buyOrderOneclick', oc.authorizationCode as 'authCodeOneclick', oc.authCode as 'authCodeEnrollment',
                                    wp.buyOrder as 'buyOrderWebpay', wp.authorizationCode as 'authCodeWebpay'"
                                )
                            ->from($this->tableName . " t")
                            ->join("oneclicktrx oc", "oc.idTrx = t.idTrx", "left")
                            ->join("wptrxpatpass wp", "wp.idTrx = t.idTrx", "left")
                            ->where($where)
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
	
	
	/**
     * Find grouped trxs by commerce
     *
     * @param $where
     * @return null|object.

     */
    function findGrouped($commerceCode)
    {
        $query = $this->db
            ->select("t.idStage, DATE(t.creationDate) 'date', DATE_FORMAT(t.creationDate,'%H') 'time', s.name, COUNT(1) 'total', SUM(t.amount) 'accumulatedAmount'")
            ->from('trx t')
			->join('stage as s', 's.idStage = t.idStage')
            ->join('commerce as c', 'c.idCommerce = t.idCommerce')
            //->join("paymenttype as pt", 'pt.idPaymentType = t.idPaymentType')
            ->where('c.code', $commerceCode)
			->group_by("DATE(t.creationDate), DATE_FORMAT(t.creationDate,'%H'), t.idStage, s.name")
            ->get();

        return ($query->num_rows() > 0)
            ? $this->sanitize->setStandards($query->result()) : null;
    }
	
}