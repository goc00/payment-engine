<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User extends CI_Controller 
{
    public function __construct()
    {
        parent::__construct();

		// Load Models
		$this->load->model('trx_model', '', true);

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
	 * Get User's TRX
	 *
	 * @return void
	 */
	public function GetTrx()
	{
		$trx = null;

		try {
			$post = $this->sanitize->inputParams(true);
			
			if ( empty((array)$post) ) {
				throw new Exception('Missing required parameters', 400);
            }

            $trx = $this->trx_model->findByCommerceCodeAndIdUserExternal($post->commerceCode, $post->idUserExternal, [$post->offset, $post->total]);

			if (is_null($trx)) {
				throw new Exception('No Records Found', 204);
            }

            $count = $this->trx_model->findByCommerceCodeAndIdUserExternal($post->commerceCode, $post->idUserExternal, [$post->offset, $post->total], true);

			$response = $this->sanitize->successResponse([
                'totalItems'    => $count,
                'currentItems'  => count($trx),
                'items'         => (!is_array($trx)) ? [$trx] : $trx
            ]);

		} catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
		}

		$this->sanitize->jsonResponse($response);
	}
}