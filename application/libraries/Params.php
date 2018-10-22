<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Params 
{
    /**
     * Check if required params exist
     *
     * @param array $required
     * @param array $params
     * @return void
     */
    public function validRequired($required, $params)
    {
        $params		= 	(object)$params;
		$msgError 	= 	'No se ha detectado el parámetro [PARAM]';
		$length 	= 	count($required);
		
		for ( $i=0; $i<$length; $i++ ) {
			$key = $required[$i];
			
			if (empty($params->$key)) {
				return ['status' => 0, 'msg' => str_replace('PARAM', $key, $msgError)];
			} 
		}

		return ['status' => 1, 'msg' => ''];
    }

    /**
     * Generate JSON Reponse
     *
     * @param Object/Array $data
     * @return void
     */
    public function jsonResponse($data)
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    /**
     * Check if required params exist
     *
     * @param array $required
     * @param array $params
     * @return void
     */
    public function trimmer($params)
    {
        if (!is_array($params))
            $params = (array)$params;

        $trimmer = array_map('trim', $params);
        return $trimmer;
    }
}