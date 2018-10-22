<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transaction extends CI_Controller
{
    private $newTrx         = 1; // New Transaction ID
    private $maxCharsTrx    = 18; // Chars number

	// Estados para recurrencia
	const OK_PROCESS_RECURRENCE			= 41;
	const FAILED_PROCESS_RECURRENCE		= 42;

    public function __construct() {
        parent::__construct();

		$this->load->model('payment_type_model', '', true);
        $this->load->model('core_model', '', true);
        $this->load->model('commercev2_model', '', true);
        $this->load->model('transactionv2_model', '', true);
        $this->load->model('country_model', '', true);
        $this->load->model('analytic_model', '', true);

		$this->load->library('sanitize');
        $this->load->library('encryption');

		$this->load->helper('crypto');
        $this->load->helper('string');
    }
    
    /**
     * Start transaction in payment engine
     */
    public function startTransaction() {
        try {
            
            $post = $this->sanitize->inputParams(true, true, false);
            
            // Validate and obtain commerce data
            $commerce = $this->_isCommerceValid($post->commerceID);

            if ( isset($commerce['error']) ) {
                throw new Exception($commerce['error']['message'], $commerce['error']['code']);
            }

            $commerce = $commerce['data'];

            // Check if country is setted. If it is, will get ID
            // if it is not, CL will be the default option
            $iso3166_2 = isset($post->country) ? strtoupper(trim($post->country)) : "CL";
            
            $oCountry = $this->country_model->findByAttr("iso3166_2", $iso3166_2, true);
            //print_r($oCountry); exit;
            if (empty($oCountry)) {
                throw new Exception("The country doesn't exist in the system", 400);
            }

            $processParams = $this->processPostData($post);
            $post = $processParams['post'];

            // Add Params
            $post->idStage      = $this->newTrx;
            $post->idCommerce   = $commerce->idCommerce;
            $post->trx          = random_string('alnum', $this->maxCharsTrx);
            $post->idCountry    = $oCountry->idCountry;
            $post->creationDate = date('Y-m-d H:i:s');
            
            // Log init params
            log_message('debug', print_r($post, TRUE));

            // Start new trx on the engine
            $idTrx = $this->core_model->newTrx($post);

            if (is_null($idTrx)) {
                throw new Exception('Failed to start transaction on system', 400);
            }

            // Insert Analytics
            $analytic = $processParams['analytic'];

            if (!empty($analytic)) {
                $analytic['trx_id'] = $idTrx;
                $this->analytic_model->add($analytic);
            }

            $response = $this->sanitize->successResponse([
                'result'        => encode_url($idTrx),
                'campaignUrl'   => $processParams['campaignUrl']
            ]);

        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse($response);
    }

    /**
     * @param $post
     * @return array
     */
    private function processPostData($post)
    {
        $analytic       = [];
        $campaignUrl    = '';

        if (isset($post->patternId)) {
            $analytic['pattern_id'] = $post->patternId;
            $campaignUrl .= "&pattern_id={$analytic['pattern_id']}";
            unset($post->patternId);
        }

        if (isset($post->pixelId)) {
            $analytic['pixel_id'] = $post->pixelId;
            $campaignUrl .= "&pixel_id={$analytic['pixel_id']}";
            unset($post->pixelId);
        }

        if (isset($post->utmSource)) {
            $analytic['utm_source'] = $post->utmSource;
            $campaignUrl .= "&utm_source={$analytic['utm_source']}";
            unset($post->utmSource);
        }

        if (isset($post->utmMedium)) {
            $analytic['utm_medium'] = $post->utmMedium;
            $campaignUrl .= "&utm_medium={$analytic['utm_medium']}";
            unset($post->utmMedium);
        }

        if (isset($post->utmCampaign)) {
            $analytic['utm_campaign'] = $post->utmCampaign;
            $campaignUrl .= "&utm_campaign={$analytic['utm_campaign']}";
            unset($post->utmCampaign);
        }

        if (isset($post->utmContent)) {
            $analytic['utm_content'] = $post->utmContent;
            $campaignUrl .= "&utm_content={$analytic['utm_content']}";
            unset($post->utmContent);
        }

        if (isset($post->utmTerm)) {
            $analytic['utm_term'] = $post->utmTerm;
            $campaignUrl .= "&utm_term={$analytic['utm_term']}";
            unset($post->utmTerm);
        }

        if (isset($post->springSale)) {
            $analytic['spring_sale'] = $post->springSale;
            $campaignUrl .= "&spring_sale={$analytic['spring_sale']}";
            unset($post->springSale);
        }

        // Delete params for inserting into database
        unset($post->commerceID);
        unset($post->country);

        $campaignUrl = ltrim($campaignUrl, '&');
        $campaignUrl = "?{$campaignUrl}";

        return ['analytic' => $analytic, 'post' => $post, 'campaignUrl' => $campaignUrl];
    }


    public function getByExternalCode()
    {
        try {
            $post = $this->sanitize->inputParams(true);

            if ( empty((array)$post) )
                throw new Exception('Missing required parameters', 422);

            $trx = $this->transactionv2_model->findByAttrs(
                ['codExternal' => $post->externalCode]
            );

            if( is_null($trx) ) {
                throw new Exception('Empty Trx', 404);
            }

            $response = $this->sanitize->successResponse([
                'totalItems'    => count($trx),
                'items'         => (!is_array($trx)) ? [$trx] : $trx
            ]);
        } catch (Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse($response);
    }


    /**
     * Get trx by codExternal (encoded)
     */
    public function getByCodExternal()
    {
        try {
            $post = $this->sanitize->inputParams(true);

            if (empty((array)$post)) throw new Exception('Missing required parameters', 422);

            $trx = $this->transactionv2_model->findByAttrs(
                ['codExternal' => $post->codExternal]
            );
            if( is_null($trx) ) {
                throw new Exception('Empty Trx', 404);
            }

            $response = $this->sanitize->successResponse([
                'totalItems'    => count($trx),
                'items'         => (!is_array($trx)) ? [$trx] : $trx
            ]);
        } catch (Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse($response);
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
     * Get Channels (Payments Type) by CommerceCode and User
     *
     * @return void
     */
    public function getByCommerceUser()
    {
        try {
            $post = $this->sanitize->inputParams(true);

            if ( empty((array)$post) )
                throw new Exception('Missing required parameters', 422);

            $types = $this->payment_type_model->findByCommerceAndCountry(
                $post->commerceCode, $post->externalUser
            );

            if( is_null($types) )
                throw new Exception('No Records Found', 204);

            $response = $this->sanitize->successResponse(['paymentTypes' => $types]);
        } catch (Exception $e) {
            log_message("error", __METHOD__ . "(".$e->getCode().") -> ".$e->getMessage());
            $response = $this->sanitize->errorResponse($e->getCode(), $e->getMessage());
        }

        $this->sanitize->jsonResponse($response);
    }


    /**
     * Generate a PDF to the transaction
     *
     * @return string json
     */
    public function generatePdf()
    {
        $data = [
            'logo'              =>  $this->input->post('logo') ? $this->input->post('logo') : false,
            'buyOrder'          =>  $this->input->post('buyOrder') ? $this->input->post('buyOrder') : false,
            'amount'            =>  $this->input->post('amount') ? $this->input->post('amount') : false,
            'authorizationCode' =>  $this->input->post('authorizationCode') ? $this->input->post('authorizationCode') : false,
            'creationDate'      =>  $this->input->post('creationDate') ? $this->input->post('creationDate') : false,
            'last4CardDigits'   =>  $this->input->post('last4CardDigits') ? $this->input->post('last4CardDigits') : false,
            'description'       =>  $this->input->post('description') ? $this->input->post('description') : false,
            'paymentType'       =>  $this->input->post('paymentType') ? $this->input->post('paymentType') : false,
            'sharesType'        =>  $this->input->post('sharesType') ? $this->input->post('sharesType') : false,
            'sharesNumber'      =>  $this->input->post('sharesNumber') ? $this->input->post('sharesNumber') : false
        ];

        $html = html_entity_decode($this->load->view('oneclick/print', $data, true));

        //$filename   =   date('His').'.pdf';             // PDF Filename
        $filename   =   $data['buyOrder'].'.pdf';
        $fullPath   =   "./files/pdfs/{$filename}";            // PDF Path

        $this->load->library('dompdf'); // Load Library
        $dompdf = new Dompdf();         // Init Library
        $dompdf->set_options([          // Set Options
            'isRemoteEnabled' => true
        ]);
        $dompdf->loadHtml($html);       // Load HTML on PDF
        $dompdf->setPaper('A4', 'portrait'); // Set the paper size and orientation
        $dompdf->render();              // Render the HTML as PDF
        //$dompdf->stream();            // Output the generated PDF to Browser
        $output = $dompdf->output();    // Get PDF
        file_put_contents($fullPath, $output);   // Save on server

        $response = (!file_exists($fullPath))
            ? ['status' => 0, 'path' => null]
            : ['status' => 1, 'path' => ltrim($fullPath, './')];

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
	
	
	/**
     * OneClick : Authorize()
	 * Freemium : ResetSubscription();
     *
     * @return string json
     */
	public function MakeRecurrencePayment()
    {
        try {
			
            $post = $this->sanitize->inputParams(true, true, false);
			
			$commerceCode = $post->commerceCode;
			$idUserExternal = $post->idUser;		// idUserExternal (User Master ID)
			$idProduct = $post->idProduct;
			$amount = $post->amount;
			$date = $post->date;
	
			// Check for commerce
			$commerce = $this->_isCommerceValid($commerceCode);
			if ( isset($commerce['error']) ) {
                throw new Exception($commerce['error']['message'], $commerce['error']['code']);
            }
			//$idCommerce = $commerce['error'];
			$oComm = $commerce["data"];
			$idCommerce = $oComm->idCommerce;
			$prefixCommerce = $oComm->prefix;
            
            // Check profile is premium (paid) or not
            /*$freemium = new stdClass();
			$freemium->idProduct = $idProduct;
			$freemium->idClient = $idUserExternal;
			
			$dataString = json_encode((array)$freemium);*/
			
            //$curl = curl_init($this->config->item("ResetSubscriptionFreemium"));
            $sGetUserFreemium = $this->config->item("GetUserFreemium");
            $sGetUserFreemium = str_replace("{ID_PRODUCT}", $idProduct, $sGetUserFreemium);
            $sGetUserFreemium = str_replace("{ID_CLIENT}", $idUserExternal, $sGetUserFreemium);

            $call = $this->_callApiGateway($sGetUserFreemium, NULL, "GET");

            if(empty($call)) {
                //$this->core_model->updateStageTrx($idTrx, self::FAILED_PROCESS_RECURRENCE);
				throw new Exception("No se pudo obtener información del usuario", 1004);
            }
            $oCall = json_decode($call);
      
			if(isset($oCall->error)) {
				//$this->core_model->updateStageTrx($idTrx, self::FAILED_PROCESS_RECURRENCE);
				throw new Exception($oCall->error->message, 1005);
            }

            // Check if user profile is paid or not
            $isPaid = (int)$oCall->data->profile->paid == 1 ? true : false;
            if(!$isPaid) {
                //$this->core_model->updateStageTrx($idTrx, self::FAILED_PROCESS_RECURRENCE);
				throw new Exception("El perfil del usuario no es de pago. No se ha aplicado cobro recurrente", 1006);
            }
            

			// Find for oneclick account
			// Verify if the user already has an enrollment
			$response = json_decode(
                $this->sanitize->callController(
                    base_url('oneclick/getDetailsByUserExtAndComm'),
                    (object)[
                        'idCommerce'		=> $idCommerce,
						'idUserExternal'	=> $idUserExternal
                    ]
                )
            );
			
			$ok = FALSE;
			$error = "No se pudo obtener información de la cuenta del usuario";
			if(!empty($response)) {
				$error = $response->message;
				if((int)$response->code == 0) {
					if(!empty($response->result)) {
						$ok = TRUE;
					}
				}
			}
			if(!$ok) { throw new Exception($error, 1001); }
			
			// With account detected, will try to make a payment
			$account = $response->result;
			// Firstly, initial attributes
			$newTrx = new stdClass();
	
			$newTrx->idCommerce   				= $idCommerce;
			$newTrx->trx						= random_string('alnum', $this->maxCharsTrx);
			$newTrx->amount						= $amount;
			$newTrx->idUserExternal				= $idUserExternal; // idUserExternal
			$newTrx->codExternal				= "REC".str_replace(".", "", microtime(TRUE));
			$newTrx->creationDate				= date('Y-m-d H:i:s');
	
			$newTrx->tbkUser					= $account->tbkUser;
			$newTrx->prefixCommerce				= $prefixCommerce;

			// New transaction
			$responseNewTrx = json_decode(
                $this->sanitize->callController(
                    base_url('oneclick/authorizeSimplified'),
                    $newTrx
                )
            );
			
			// Response from payment try (authorize)
			if(empty($responseNewTrx)) {
				 throw new Exception("No se pudo procesar la solicitud de cobro", 1002);
			}
			if($responseNewTrx->code != 0) {
				throw new Exception($responseNewTrx->message, 1003);
			}
			
			// ID transaction created
			$idTrx = $responseNewTrx->result;

			// If payment was successful, will call Freemium service
			$freemium = new stdClass();
			$freemium->idProduct = $idProduct;
			$freemium->idClient = $idUserExternal;
			
			$dataString = json_encode((array)$freemium);
            
            $call = $this->_callApiGateway(
                                            $this->config->item("ResetSubscriptionFreemium"),
                                            $dataString,
                                            "PUT"
                                        );

			/*$curl = curl_init($this->config->item("ResetSubscriptionFreemium"));
			curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-api-key:'.$this->config->item("ApiKeyGateway")]);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);

			$exec = curl_exec($curl);
			curl_close($curl);*/
			
			// Check freemium response
			if(empty($call)) {
				$this->core_model->updateStageTrx($idTrx, self::FAILED_PROCESS_RECURRENCE);
				throw new Exception("No se pudo procesar la solicitud a servicio de Freemium", 1004);
			}
			
			$oFreemium = json_decode($call);
			if(isset($oFreemium->error)) {
				$this->core_model->updateStageTrx($idTrx, self::FAILED_PROCESS_RECURRENCE);
				throw new Exception($oFreemium->error->message, 1005);
			}
            
			// At this point, everything is OK
			$this->core_model->updateStageTrx($idTrx, self::OK_PROCESS_RECURRENCE);
            
            $response = $this->sanitize->successResponse([
                'result'    => TRUE
            ]);

        } catch(Exception $e) {
            $response = $this->sanitize->errorResponse(
                $e->getCode(), $e->getMessage(), ['apiVersion' => API_VERSION_2], __METHOD__
            );
        }

        $this->sanitize->jsonResponse($response);
    }
    
    /**
     * Call external services (API Gateway), because it's not available
     * direct calls to core services.
     *
     * @param $service
     * @param data
     * @param type
     *
     * @return object
     */
    private function _callApiGateway($service, $data, $type) {

        $curl = curl_init($service);

        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-api-key:'.$this->config->item("ApiKeyGateway")]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        $method = strtoupper($type);

        if($method != "GET") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        
        $exec = curl_exec($curl);
        curl_close($curl);

        return $exec;
    }

	/**
     * Validate if commerce is valid
     *
     * @param string
     *
     * @return object
     */
    private function _isCommerceValid($keyCommerce)
    {
        try {
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

        return $response;
    }
	
}