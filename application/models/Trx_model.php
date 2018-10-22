<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Trx_model extends CI_Model 
{
    /**
     * Table's name
     *
     * @var string
     */
    private $tableName  = 'trx';

    /**
     * Primary key's name
     *
     * @var string
     */
    private $primaryKey = 'idTrx';

    function __construct() {
		parent::__construct();

		$this->load->library('sanitize');
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
     * Find records by Commerce Code and Id User External
     *
     * @param String $commerceCode
     * @param String $idUserExternal
     * @return mixed
     */
    function findByCommerceCodeAndIdUserExternal($commerceCode, $idUserExternal, $paginate, $count=false)
    {
        $this->db->select('trx.idTrx, trx.amount, trx.idUserExternal, trx.codExternal, trx.creationDate, trx.modificationDate');
        $this->db->select('c.idCommerce, c.name as nameCommerce, c.code as codeCommerce, c.active as activeCommerce');
        $this->db->select('s.idStage, s.name as nameStage, s.description as descStage');
        $this->db->select('p.idPaymentType, p.name as namePaymentType, p.description as descPaymentType, p.active as activePaymentType, p.codPaymentTypeExternal as codePaymentType');
        $this->db->select('oc.buyOrder as buyOrderOneclick, oc.authorizationCode as authCodeOneclick, oc.authCode as authCodeEnrollment');
        $this->db->select('wp.buyOrder as buyOrderWebpay, wp.authorizationCode as authCodeWebpay');
        $this->db->from($this->tableName);
        $this->db->join('commerce as c', 'c.idCommerce = trx.idCommerce');
        $this->db->join('stage as s', 's.idStage = trx.idStage');
        $this->db->join('paymenttype as p', 'p.idPaymentType = trx.idPaymentType', 'left');

        $this->db->join("oneclicktrx oc", "oc.idTrx = trx.idTrx", "left");
        $this->db->join("wptrxpatpass wp", "wp.idTrx = trx.idTrx", "left");

        $this->db->where('c.code', $commerceCode);

        if ( $idUserExternal != '-' ) {
            $this->db->where('idUserExternal', $idUserExternal);
        }

        if ($count) {
            $qCount = $this->db->get();
            return $qCount->num_rows();
        }

        if (null == $paginate[0] || null == $paginate[1]) {
            $paginate[0] = 0;
            $paginate[1] = 50;
        }

        $this->db->limit($paginate[1], $paginate[0]);

        $this->db->order_by('trx.idTrx', 'DESC');

        $query = $this->db->get();
        return ( $query->num_rows() > 0) ? $this->sanitize->setStandards($query->result()) : null;
    }

    /**
     * Find records by CommerceCode, PaymentTypeCode and IdUserExternal
     *
     * @param String $commerceCode
     * @param String $idUserExternal
     * @return mixed
     */
    function findByCommerceCodePaymentTypeCodeAndIdUserExternal($commerceCode, $paymentTypeCode, $idUserExternal)
    {
        $query = $this->db
            ->select('idTrx, amount, idUserExternal, codExternal, trx.creationDate, trx.modificationDate')
            ->select('c.idCommerce, c.name as nameCommerce, c.code as codeCommerce, c.active as activeCommerce')
            ->select('s.idStage, s.name as nameStage, s.description as descStage')
            ->select('p.idPaymentType, p.name as namePaymentType, p.description as descPaymentType, p.active as activePaymentType, p.codPaymentTypeExternal as codePaymentType')
            ->from($this->tableName)
            ->join('commerce as c', 'c.idCommerce = trx.idCommerce')
            ->join('stage as s', 's.idStage = trx.idStage')
            ->join('paymenttype as p', 'p.idPaymentType = trx.idPaymentType', 'left')
            ->where('c.code', $commerceCode)
            ->where('idUserExternal', $idUserExternal)
            ->where('p.codPaymentTypeExternal', $paymentTypeCode)
            ->order_by('trx.idTrx', 'DESC')
            ->get();
        
        return $this->modelResponse($query);
    }

    /**
     * find records by IdUserExternal and Dates
     * 
     * With $opts you can get paginate records
     *
     * @param Integer $idUserExternal
     * @param Array $date
     * @param Array $opts
     * @return void
     */
    function findByDateAndIdUserExternal($idUserExternal, $date, $opts=null)
    {
        $this->db->where('idUserExternal', $idUserExternal);
        
        // Validate by date
        if (array_key_exists('endDate', $date)) {
            $this->db->where('creationDate >=', $date['startDate']);
            $this->db->where('creationDate <=', $date['endDate']);
        } else if (array_key_exists('startDate', $date)) {
            $this->db->where('creationDate', $date['startDate']);
        }

        // Validate by opts
        if( array_key_exists('total', $opts) && array_key_exists('offset', $opts) ) {
			$this->db->limit($opts['total'], $opts['offset']);
		} else if( array_key_exists('total', $opts) ) {
			$this->db->limit($opts['total']);
        }
        
        $query = $this->db->get($this->tableName);

        return $this->modelResponse($query);
    }

    /**
     * Find records by CommerceCode and IdStage
     *
     * @param Integer $commerceCode
     * @param Integer $idStage
     * @return mixed
     */
    function findByCommerceCodeAndIdStage($commerceCode, $idStage)
    {
        $query = $this->db
        ->from($this->tableName)
        ->join('commerce as c', 'c.idCommerce = trx.idCommerce')
        ->where('c.code', $commerceCode)
        ->where('idStage', $idStage)
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