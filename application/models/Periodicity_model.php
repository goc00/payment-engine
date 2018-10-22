<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Periodicity_model extends CI_Model {

	private $tableName 	= 'periodicity';
	private $primaryKey	= 'idPeriodicity';

	function __construct()
	{
		parent::__construct();
    }
	
	/**
	 * Retrieve all records
	 *
	 * @return void
	 */
	function all()
	{
		return $this->db->get($this->tableName)->result();
	}

	/**
	 * Get record by id
	 *
	 * @param integer $id
	 * @return void
	 */
	function find($id)
	{
		return $this->db->where($this->primaryKey, $id)
			->get($this->tableName)
			->row();
	}

	/**
	 * Get record by id
	 *
	 * @param string $tag
	 * @return void
	 */
	function findByTag($tag)
	{
		return $this->db->where('tag', $tag)
			->get($this->tableName)
			->row();
	}
}

