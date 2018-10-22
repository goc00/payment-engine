<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class FourtySixLib
 *
 */

/**
 * Response Dictionary
 * -1: Error Env
 */

class Fourtysixlib
{
    /**
     * HMAC algorithm
     *
     * @var string
     */
    private $hmacAlgo = 'sha256';

    /**
     * Merchant data [key, secret]
     *
     * @var array
     */
    private $merchant = [];

    /**
     * Request data [method, path]
     *
     * @var array
     */
    private $request = [];

    /**
     * Environment
     *
     * @var string
     */
    private $env = 'production';

    /**
     * API Endpoint
     *
     * @var string
     */
    private $endpoint = '';

    /**
     * Header
     *
     * @var array
     */
    private $header = [];


    public function __construct()
    {
    }

    /**
     * Merchant getter
     *
     * @return array
     */
    public function getMerchant()
    {
        return $this->merchant;
    }

    /**
     * Merchant setter
     *
     * @param $merchant
     */
    public function setMerchant($merchant)
    {
        $this->merchant = $merchant;
    }

    /**
     * Environment Getter
     *
     * @return string
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * Environment Setter
     *
     * @param string $env Environment
     *
     * @return void
     */
    public function setEnv($env)
    {
        $permitted = ['production', 'sandbox'];

        if (!in_array($env, $permitted)) {
            $this->env = false;
            return false;
        }

        if ($env == 'production') {
            $this->endpoint = 'https://api.pago46.com/';
        } elseif ($env == 'sandbox') {
            $this->endpoint = 'https://sandboxapi.pago46.com/';
        }

        $this->env = $env;
    }

    /**
     * Get transactions
     *
     * @return array
     */
    public function getOrders()
    {
        $this->request = ['method' => 'GET', 'path' => '%2Fmerchant%2Forders%2F'];
        $this->header = $this->setHeader(false);
        $api = $this->callApi('merchant/orders/', $this->request['method'], false);

        return $api;
    }

    /**
     * Get order by orderId
     *
     * @param $id
     *
     * @return array
     */
    public function getOrderByID($id)
    {
        $this->request = ['method' => 'GET', 'path' => "%2Fmerchant%2Forder%2F{$id}"];
        $this->header = $this->setHeader(false);
        $api = $this->callApi("merchant/order/{$id}", $this->request['method'], false);

        return $api;
    }

    /**
     * Get order by notificationId
     *
     * @param $id
     *
     * @return array
     */
    public function getOrderByNotificationID($id)
    {
        $this->request = ['method' => 'GET', 'path' => "%2Fmerchant%2Fnotification%2F{$id}"];
        $this->header = $this->setHeader(false);
        $api = $this->callApi("merchant/notification/{$id}", $this->request['method'], false);

        return $api;
    }

    /**
     * Create a new order
     *
     * @param array $order
     *
     * @return array
     */
    public function newOrder($order)
    {
        $this->request = ['method' => 'POST', 'path' => '%2Fmerchant%2Forders%2F'];

        $concatenateParams = '';

        foreach ($order as $k => $v) {
            $value = urlencode($v);
            $concatenateParams .= "&{$k}={$value}";
        }

        $this->header = $this->setHeader($concatenateParams);

        $api = $this->callApi("merchant/orders/", $this->request['method'], $order);

        return $api;
    }

    /**
     * Generate header to call 46 API
     *
     * @param bool|string $concatenateParams added params
     *
     * @return array
     */
    private function setHeader($concatenateParams = false)
    {
        $unixTimestamp = date_timestamp_get(date_create());

        $encryptBase = "{$this->merchant['key']}&{$unixTimestamp}&{$this->request['method']}&{$this->request['path']}";

        if ($concatenateParams) {
            $encryptBase = "{$encryptBase}{$concatenateParams}";
        }

        $hmac = hash_hmac($this->hmacAlgo, $encryptBase, $this->merchant['secret']);

        return [
            'Content-Type: application/json',
            "merchant-key: {$this->merchant['key']}",
            "message-hash: {$hmac}",
            "message-date: {$unixTimestamp}"
        ];
    }

    /**
     * Call Pago46 API
     *
     * @param string $url
     * @param string $method
     * @param bool $data
     *
     * @return array
     */
    private function callApi($url, $method = 'GET', $data = false)
    {
        if (!$this->env) {
            return ['status' => -1, 'msg' => 'Environment Error'];
        }

        $url = (substr($this->endpoint, -1) == '/') ? $this->endpoint . $url : $this->endpoint . '/' . $url;
        $data = json_encode($data);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);

        switch (strtolower($method)) {
            case 'post':
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Hack Non-SSL
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // Hack Non-SSL
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->header);

        $jsonResult = curl_exec($curl);

        curl_close($curl);

        $result = json_decode($jsonResult);

        return $result;
    }
}
