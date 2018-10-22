<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sanitize
{
    private $CI;
    private $staticPathS3 = 'https://s3.amazonaws.com';
    private $imgLimitSize = 1000000; // in bytes
    private $pdfLimitSize = 1000000; // in bytes

    public function __construct()
    {
        $this->CI =& get_instance();
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

    /**
     * Return Post Params
     *
     * @return void
     */
    public function inputParams($objResponse=false, $trimmer=true, $explode=true)
    {
        $raw = file_get_contents('php://input');

        $o = new stdClass();

        if (strpos($raw,'=') !== false && $explode) {
            // = exists
            $arr = explode("&", $raw);
            $l = count($arr);

            if ($l < 2) {
                // One element
                $arrVal = explode("=", $arr[0]);
                list($key, $value) = $arrVal;
                $o->$key = $value;
            } else {
                // More than 1 element
                for ($i=0; $i<$l; $i++) {
                    $val = $arr[$i];
                    $arrVal = explode("=", $val);

                    list($key, $value) = $arrVal;

                    $o->$key = $value;
                }
            }

            // At this point we got an object
            $params = $o;
        } else {
            // normal
            $params = json_decode($raw);
        }

        if (!$trimmer)
            return (!$objResponse) ? $params : (object)$params;

        return (!$objResponse) ? $this->trimmer($params) : (object)$this->trimmer($params);
    }

    public function errorResponse($code, $msg, $extraData=null, $method=__METHOD__, $errors=[])
    {
        log_message('error', $method . ' ('. $code .')-> ' . $msg);

        $response = [
            'error' => [
                'code' 		=> $code,
                'message' 	=> $msg
            ]
        ];

        return (is_null($extraData)) ? $response : array_merge($extraData, $response, $errors);
    }

    public function successResponse($data)
    {
        $response = [
            'data' => $data
        ];

        return $response;
    }

    /**
     * Generate a Json response
     *
     * @param array $data
     * @param array $extraData
     * @return void
     */
    public function jsonResponse($data, $extraData=null)
    {
        $data = (is_array($data)) ? $data : (array)$data;

        if (!is_null($extraData)) {
            $extraData += ['context' => 'payments'];
        }

        $response = (is_null($extraData)) ? $data : $extraData+$data;

        return $this->CI->output
            ->set_content_type('application/json')
            ->set_output(
                json_encode($response)
            );
    }

    /**
     * Connection between controllers
     *
     * @param string $endpoint
     * @param object|array $params
     * @return void
     */
    public function callController($endpoint, $params, $transfer = TRUE)
    {
        $curl = curl_init($endpoint);
        $dataString = (is_array($params)) ? json_encode($params) : json_encode((array)$params);

        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, $transfer);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);

        $exec = curl_exec($curl);
        curl_close($curl);

        return $exec;
    }

    /**
     * Set standards on response
     *
     * @param array $query
     * @return void
     */
    public function setStandards($query)
    {
        if (count($query) > 1){
            foreach ($query as $objk => $objv) {
                foreach ($objv as $k => $v) {

                    if (strpos($k, '_') !== false) {
                        $newK = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $k))));
                        $query[$objk]->$newK = $v;
                        unset($query[$objk]->$k);
                        $k = $newK;
                    }

                    /*if (empty($v))
                        unset($query[$objk]->$k);*/

                    if (DateTime::createFromFormat('Y-m-d G:i:s', $v) !== false) {
                        $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $v);
                        $query[$objk]->$k = $datetime->format('Y-m-d\TH:i:s.uP');
                    }

                }
            }
        } else if (count($query) == 1) {
            $query = array_key_exists(0, $query) ? $query[0] : $query;

            foreach ($query as $k => $v) {
                if (strpos($k, '_') !== false) {
                    $newK = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $k))));
                    $query->$newK = $v;
                    unset($query->$k);
                    $k = $newK;
                }

                /*if (empty($v))
                    unset($query[$objk]->$k);*/

                if (DateTime::createFromFormat('Y-m-d G:i:s', $v) !== false) {
                    $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $v);
                    if ( isset($query->$k)) {
                        $query->$k = $datetime->format('Y-m-d\TH:i:s.uP');
                    } else if ($k == 'updated') {
                        $query = ['updated' => $datetime->format('Y-m-d\TH:i:s.uP')];
                    }
                }
            }
        }

        return $query;
    }

    public function validateParams($param, $null=true, $type=false)
    {
        if ( $null && is_null($param) )
            return false;

        if ( $type ) {
            if ($type == 'string' && !is_string($param) )
                return false;
            else if ($type == 'int' && !is_integer($param) )
                return false;
        }

        return true;
    }

    /**
     * Upload an image to AWS S3
     *
     * @param $path
     * @return array|bool|string
     */
    public function uploadImgBase64ToS3($fileBase64, $path, $bucket)
    {
        $this->CI->load->library('s3');
        $buckets    = $this->CI->s3->listBuckets();
        $imageName  = str_replace ('.', '', microtime(TRUE));

        if (getimagesizefromstring(base64_decode($fileBase64))) {
            return ['error' => 'base64 is not an image'];
        }

        if (strpos($fileBase64, 'png') !== false) {
            $extension = 'png';
        } else if ( strpos($fileBase64, 'jpg') !== false || strpos($fileBase64, 'jpeg') !== false ) {
            $extension = 'jpg';
        } else {
            return false;
        }

        $fullFilePath = "./uploads/{$imageName}.{$extension}";

        list($type, $dataImg) = explode(';', $fileBase64);
        list(, $fileBase64)      = explode(',', $fileBase64);
        $dataImg = base64_decode($fileBase64);

        file_put_contents($fullFilePath, $dataImg);

        $decodedFile = base64_decode($fileBase64);

        // Check Size
        if ( $this->imgLimitSize < strlen($decodedFile)) {
            return ['error' => 'Exceeded limit 1mb'];
        }

        // Check bucket
        if (!in_array($bucket, $buckets)) {
            $this->CI->s3->putBucket($bucket, 'private');
        }

        $uploadFile = S3::inputFile( $fullFilePath);
        $uri        = "{$path}{$imageName}.{$extension}";

        if (S3::putObject($uploadFile, $bucket, $uri, 'public-read')) {
            unlink($fullFilePath);
            return "{$this->staticPathS3}/{$bucket}/{$path}{$imageName}.{$extension}";
        } else {
            return false;
        }
    }

    /**
     * Upload a PDF to AWS S3
     *
     * @param $path
     * @param $bucket
     * @return array|bool|string
     * @throws Exception
     */
    public function uploadPdfBase64ToS3($fileBase64, $path, $bucket)
    {
        $this->CI->load->library('s3');
        $buckets    = $this->CI->s3->listBuckets();
        $fileName   = str_replace ('.', '', microtime(TRUE));

        if (strpos($fileBase64, 'pdf') === false) {
            throw new Exception('Missing PDF', 400);
        } else {
            $extension = 'pdf';
        }

        $fullFilePath = "./uploads/{$fileName}.pdf";

        list($type, $dataFile)  = explode(';', $fileBase64);
        list(, $fileBase64)     = explode(',', $fileBase64);
        $dataFile               = base64_decode($fileBase64);

        file_put_contents($fullFilePath, $dataFile);

        $decodedFile = base64_decode($fileBase64);

        // Check Size
        if ( $this->pdfLimitSize < strlen($decodedFile)) {
            throw new Exception('Exceeded limit 1mb', 400);
        }

        // Check bucket
        if (!in_array($bucket, $buckets)) {
            $this->CI->s3->putBucket($bucket, 'private');
        }

        $uploadFile = S3::inputFile( $fullFilePath);
        $uri        = "{$path}{$fileName}.{$extension}";

        if (S3::putObject($uploadFile, $bucket, $uri, 'public-read')) {
            unlink($fullFilePath);
            return "{$this->staticPathS3}/{$bucket}/{$path}{$fileName}.{$extension}";
        } else {
            return false;
        }
    }

    /**
     * Generate a log
     *
     * @param $method
     * @param $msg
     * @param string $lvl
     *
     * @return void
     */
    public function generateLog($method, $msg, $lvl='debug')
    {
        log_message($lvl, $method . ' -> ' . $msg);
    }
}