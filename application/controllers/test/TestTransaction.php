<?php

/**
 * Created by PhpStorm.
 * User: normeno
 * Date: 23-03-18
 * Time: 10:37
 */
class TestTransaction extends CI_Controller
{

    private $commerces = [100001, 100002, 100005, 100006, 100003, 100010, 100007, 100008];

    public function __construct()
    {
        parent::__construct();
        $this->load->library('unit_test');
        $this->load->library('sanitize');
    }

    /**
     * Generate "Notes" response
     *
     * @param $data
     * @return string
     */
    private function initTransactionTemplate($call) {

        if (!isset($call->data))
            return '';

        $data = $call->data;

        return (!isset($data)) ? '' :
            "<b>Encoded:</b> {$data->encoded} <br><br>".
            "<b>Campaign:</b> {$data->campaign} <br><br>".
            "<b>Url:</b> {$data->url}";
    }

    public function index() {
        $this->testInitTransactionV2Basic();
        $this->testInitTransactionV2WithAnalytics();
    }

    /**
     * Execute testInitTransactionV2Basic
     *
     * return @void
     */
    public function testInitTransactionV2Basic() {
        echo "<h1>testInitTransactionV2Basic</h1>";
        foreach ($this->commerces as $commerce) {
            $data = [
                'commerceID' => "{$commerce}",
                'idUserExternal' => "20180319-002",
                'codExternal' => "20180319-002",
                'urlOk' => "https://payments-ok.com",
                'urlError' => "https://payments-error.com",
                'urlNotify' => "https://payments-notify.com",
                'amount' => "50"
            ];

            $call = json_decode(
                $this->sanitize->callController(base_url('Apiv2/initTransaction'), $data)
            );

            echo "<h2>Commerce: {$commerce}</h2>";

            echo $this->unit->run(
                isset($call->data), 'is_true',
                "{$commerce} - testInitTransactionV2Basic",
                $this->initTransactionTemplate($call)
            );

            echo "<br><br>";
        }
    }


    /**
     * Execute testInitTransactionV2WithAnalytics
     *
     * return @void
     */
    public function testInitTransactionV2WithAnalytics()
    {

        echo "<h1>testInitTransactionV2WithAnalytics</h1>";
        foreach ($this->commerces as $commerce) {
            $data = [
                'commerceID' => "{$commerce}",
                'idUserExternal' => "20180319-002",
                'codExternal' => "20180319-002",
                'urlOk' => "https://payments-ok.com",
                'urlError' => "https://payments-error.com",
                'urlNotify' => "https://payments-notify.com",
                'amount' => "50",
                'analytics' => "?utm_source=google&utm_medium=cpc&utm_campaign=spring_sale&utm_term=my_terms&utm_content=campaign_content"
            ];

            $call = json_decode(
                $this->sanitize->callController(base_url('Apiv2/initTransaction'), $data)
            );

            echo "<h2>Commerce: {$commerce}</h2>";

            echo $this->unit->run(
                $call->data, 'is_object',
                "{$commerce} - testInitTransactionV2Basic",
                $this->initTransactionTemplate($call->data)
            );

            echo "<br><br>";
        }
    }
}