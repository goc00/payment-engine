<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Apiv2 extends MY_Controller
{
    public function __construct()
    {	
		parent::__construct();
		
		// Set Public API
		header('Access-Control-Allow-Origin: *');
        
        // Load Helpers
		$this->load->helper('string');
		$this->load->helper('crypto');
        
        // Load Libraries
        $this->load->library('encryption');
		$this->load->library('funciones');
		$this->load->library('sanitize');
	}

    /**
     * Init a transaction
     *
     * @uses $_POST['idProduct'] to identify a product
     * @uses $_POST['idClient'] to identify a external user
     * @uses $_POST['urlOk'] to redirect on success
     * @uses $_POST['urlError'] to redirect on error
     * @uses $_POST['urlNotify'] to notify associated commerce
     * @uses $_POST['amount'] to set the amount
     * @uses (optional) $_POST['try'] set if we want transaction will be try & buy
     */
    public function InitTransaction()
    {
        try {
            $post = $this->sanitize->inputParams(true, true, false);
 
            if ( empty((array)$post) ) {
                throw new Exception('Missing required parameters', 400);
            }

            if ( !isset($post->idUserExternal) || empty($post->idUserExternal) ) {
                throw new Exception('Missing External User', 400);
            }

            if ( !isset($post->codExternal) || empty($post->codExternal) ) {
                throw new Exception('Missing External Code', 400);
            }

            if ( !isset($post->urlOk) || empty($post->urlOk) ) {
                throw new Exception('Missing URL OK', 400);
            }

            if ( !isset($post->urlError) || empty($post->urlError) ) {
                throw new Exception('Missing URL Error', 400);
            }

            if ( !isset($post->urlNotify) || empty($post->urlNotify) ) {
                throw new Exception('Missing URL Notify', 400);
            }

            if ( !isset($post->commerceID) || empty($post->commerceID) ) {
                throw new Exception('Missing Commerce', 400);
            }

            if ( !isset($post->amount) || empty($post->amount) ) {
                throw new Exception('Missing Amount', 400);
            }
			
			// Now, it can receive own commerce code. If it's setted, will use it, otherwise
            // default code (3GMotion) will be used.
            $trx = json_decode(
                $this->sanitize->callController(base_url('v2/Transaction/startTransaction'), $post)
            );
           
            if (!array_key_exists('data', (array)$trx)) {
                throw new Exception('Failed to start transaction on system: ' . $trx->error->message, 400);
            }

            $response = [
                'data' => [
                    'encoded'   => $trx->data->result,
                    'campaign'  => (isset($trx->data->campaignUrl) && $trx->data->campaignUrl != '?') ? $trx->data->campaignUrl : null,
                    'url'       => base_url("apiv2/ShowPaymentFormGet/{$trx->data->result}/").'/'
                ]
            ];

        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse(
            $response,
            ['apiVersion' => API_VERSION_2]
        );
    }

    /**
     * Open Payment Form
     *
     * @param $trx
     * @param null $opts
     */
    public function ShowPaymentFormGet($trx, $opts = NULL)
    {
        $params         =   new stdClass();
        $params->trx    =   $trx;    // Encrypted idTrx
        $params->opts   =   $opts;  // Options

        echo $this->sanitize->callController( base_url('v2/Channel/showPaymentChannels'), (array)$params );
    }

    /**
     * List Payments
     * @param null|integer $commerceId
     * @param null|integer $idCountry
     */
    public function listPayments($commerceId=null, $idCountry=null)
    {
        try {
            if ( !isset($commerceId) || empty($commerceId) ) {
                throw new Exception('Missing Commerce', 400);
            }

            $response = json_decode(
                $this->sanitize->callController(
                    base_url().'v2/Channel/listPayments',
                    (object)['commerceId'=> $commerceId, 'idCountry' => $idCountry]
                )
            );

        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse(
            $response,
            ['apiVersion' => API_VERSION_2]
        );
    }

    /**
     * List Countries
     *
     * @param null $commerceId
     */
    public function listCountries($commerceId=null)
    {
        try {
            if ( is_null($commerceId) ) {
                throw new Exception('Missing Commerce', 400);
            }

            $this->load->model('commercev2_model', '', true);
            $checkCommerce = $this->commercev2_model->findByAttr('code', $commerceId);

            if ( is_null(($checkCommerce)) ) {
                throw new Exception('Invalid Commerce', 400);
            }

            $this->load->model('country_model', '', true);
            $list       = $this->country_model->all();
            $countList  = count($list);

            if ($countList < 1) {
                throw new Exception('No Records Found', 204);
            }

            $response = $this->sanitize->successResponse([
                'totalItems' => count($list),
                'items'      => $list
            ]);
        } catch (Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse(
            $response,
            ['apiVersion' => API_VERSION_2]
        );
    }


	/**
	 * Get User's de Trx
	 *
	 * @return void
	 */
	public function GetUserTrx($commerceCode, $idUserExternal, $offset=null, $total=null)
	{
		try {
			if ( empty($commerceCode) ) {
                throw new Exception('Missing Commerce', 400);
            }

            if ( empty($idUserExternal) ) {
                throw new Exception('Missing External User', 400);
            }


			$response = json_decode(
				$this->sanitize->callController(
				    base_url('user/GetTrx'),
                    (object)[
                        'commerceCode' => $commerceCode, 'idUserExternal' => $idUserExternal,
                        'offset' => $offset, 'total' => $total
                    ]
                )
			);
		} catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
		}

        $this->sanitize->jsonResponse(
            $response,
            ['apiVersion' => API_VERSION_2]
        );
	}

    /**
     * Get User's de Trx
     *
     * @return void
     */
    public function GetTrxByExternalCode($commerceCode=null, $externalCode=null)
    {
        try {
            if ( empty($commerceCode) ) {
                throw new Exception('Missing Commerce', 400);
            }

            if ( empty($externalCode) ) {
                throw new Exception('Missing External Code', 400);
            }


            $response = json_decode(
                $this->sanitize->callController(
                    base_url('v2/Transaction/getByExternalCode'),
                    (object)[
                        'commerceCode' => $commerceCode, 'externalCode' => $externalCode
                    ]
                )
            );
        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse(
            $response,
            ['apiVersion' => API_VERSION_2]
        );
    }


    /**
     * Get User's de Trx
     *
     * @return void
     */
    public function GetTrxByCodExternal($codExternal = null)
    {
        try {
            if (empty($codExternal)) throw new Exception('Missing Code', 400);
          
            $response = json_decode(
                $this->sanitize->callController(
                    base_url('v2/Transaction/getByCodExternal'),
                    (object)[
                        'codExternal' => $codExternal
                    ]
                )
            );
        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse(
            $response,
            ['apiVersion' => API_VERSION_2]
        );
    }
	
	
	/**
     * Get grouped commerce's trxs
     *
     * @return void
     */
	public function GetGroupedTrxByCommerce($commerceCode=null)
    {
        try {
            if ( empty($commerceCode) ) {
                throw new Exception('Missing Commerce', 400);
            }

            $response = json_decode(
                $this->sanitize->callController(
                    base_url('trx/getGroupedByCommerce'),
                    (object)[
                        'commerceCode' => $commerceCode
                    ]
                )
            );
        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse(
            $response,
            ['apiVersion' => API_VERSION_2]
        );
    }
	
	/**
     * Will try to make a charge through OneClick : authorize
     *
     * @return void
     */
	public function MakeRecurrencePayment($idUser, $idProduct, $commerceCode, $amount, $date = NULL)
    {
        try {
			
			// Simple validations
			if (empty($idUser)) { throw new Exception('Missing User ID', 400); }
			if (empty($idProduct)) { throw new Exception('Missing Product ID', 400); }
			if (empty($commerceCode)) { throw new Exception('Missing Commerce Code', 400); }
			if (empty($amount)) { throw new Exception('Missing Transaction Amount', 400); }
		
			
			// Find for oneclick account
			// Verify if the user already has an enrollment
			$response = json_decode(
                $this->sanitize->callController(
                    base_url('v2/Transaction/MakeRecurrencePayment'),
                    (object)[
                        'idUser'		=> $idUser,
						'idProduct'		=> $idProduct,
						'commerceCode'	=> $commerceCode,
						'amount'		=> $amount,
						'date'			=> $date
                    ]
                )
            );
			

        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse(
            $response,
            ['apiVersion' => API_VERSION_2]
        );
    }
	
}