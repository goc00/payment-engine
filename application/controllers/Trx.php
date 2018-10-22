<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Trx extends CI_Controller 
{
    public function __construct()
    {
        parent::__construct();

		// Load Models
		$this->load->model('payment_type_model', '', true);
		$this->load->model('transactionv2_model', '', true);

        // Load Libraries
		$this->load->library('sanitize');
		
		// Load Helpers
		$this->load->helper('crypto');
    }

    /**
	 * Get Channels (Payments Type) by CommerceCode and CountryAlpha2
	 *
	 * @return void
	 */
	public function getByCommerceCountry()
	{
		try {
			$post = $this->sanitize->inputParams(true);
			
			if ( empty((array)$post) )
				throw new Exception('Missing required parameters', 422);
			
			$types = $this->payment_type_model->findByCommerceAndCountry(
				$post->commerceCode, $post->countryAlpha2
			);

			if( is_null($types) ) 
				throw new Exception('Empty Types', 404);
		
			$response = $this->sanitize->successResponse(['paymentTypes' => $types]);
		} catch (Exception $e) {
			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
			$response = $this->sanitize->errorResponse($e->getCode(), $e->getMessage());
		}

		$this->sanitize->jsonResponse($response);
	}
	
	/**
	 * Get all trxs grouped by commerce
	 *
	 * @return void
	 */
	public function getGroupedByCommerce()
	{
		try {
			$post = $this->sanitize->inputParams(true);
			
			if ( empty((array)$post) )
				throw new Exception('Missing required parameters', 422);

			$trxs = $this->transactionv2_model->findGrouped($post->commerceCode);

			if( is_null($trxs) ) 
				throw new Exception('Empty Trxs', 404);
		
			$response = $this->sanitize->successResponse(
				['trxs' => $trxs]
			);
			
		} catch (Exception $e) {
			log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
			$response = $this->sanitize->errorResponse($e->getCode(), $e->getMessage());
		}

		$this->sanitize->jsonResponse($response);
	}
	
}