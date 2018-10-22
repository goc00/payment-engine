<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Commerce extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->library('sanitize');
        $this->load->library('encryption');

        $this->load->helper('crypto');
        $this->load->helper('string');

        $this->load->model('commercev2_model', '', true);
    }

    public function index()
    {
        exit('No direct script access allowed');
    }

    /**
     * Validate if commerce is valid
     *
     */
    public function validate()
    {
        try {
            $post = $this->sanitize->inputParams(true);

            if ( !isset($post->keyCommerce) ) {
                throw new Exception('Missing Key Commerce', 204);
            }

            $keyCommerce = $post->keyCommerce;

            $datetimeFormat = 'Y-m-d H:i:s';

            $commerce = $this->commercev2_model->findByOrWhere(['idCommerce' => $keyCommerce], ['code' => $keyCommerce]);

            if ( !isset($commerce) || is_null($commerce) ) { // Check if commerce is valid
                throw new Exception('Commerce provided does not exist in the system', 204);
            }

            if($commerce->active < 1) {
                throw new Exception("The commerce is inactive", 204);
            }

            // Expirado o no
            $startDate  = date($datetimeFormat, strtotime($commerce->contractStartDate));
            $endDate    = date($datetimeFormat, strtotime($commerce->contractEndDate));
            $now = date($datetimeFormat, time());

            if ( ($now < $startDate) || ($now > $endDate) ) {
                throw new Exception("Commerce is not available", 204);
            }

            $response = $this->sanitize->successResponse($commerce);

        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse($response);
    }
}