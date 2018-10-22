<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| ----------------------------
| Api Motor de Pagos v.1.0
| ----------------------------
| Autor: Gastón Orellana
| Descripción: Disponibilidad de servicios para acceso de 3rd party al motor
| Fecha creación: 06/02/2017
|
*/

class Api extends MY_Controller {


    // IDs de método de pagos
    const WEBPAY_PLUS_ID		= 5;
    const ONECLICK_ID			= 8;
    const TUSALDO_ID			= 9;
    const REDCOMPRA_ID			= 10;


    public function __construct() {

        parent::__construct();

        // Permite el acceso de manera pública a la API
        header('Access-Control-Allow-Origin: *');

        $this->load->helper('string');
        $this->load->helper('crypto');
        $this->load->library('encryption');
        $this->load->library('funciones');
        $this->load->model('core_model', '', TRUE);
        $this->load->model('oneclick_model', '', TRUE);

    }

    public function index() {
        echo "3GMotion Payment's API v1.0 by Digevo Group - Chile (last modification: 07/06/2017)"."<br />";
        /*$o = new stdClass();
        $o->nothing = "";
        echo "hola mundo! -> ".$this->_doPost(base_url()."core", (array)$o);*/
    }

    public function test() {

        $obj = new stdClass();
        $obj->idUserExternal	= "1234";
        $obj->codExternal		= "TEST_".rand(0,1000);
        $obj->urlOk				= base_url()."api/testing/ok";
        $obj->urlError			= base_url()."api/testing/error";
        $obj->urlNotify			= base_url()."api/testing/notify";
        $obj->commerceID		= "1234";
        $obj->amount			= 1000;

        $oTrx = $this->_doPost(base_url()."api/InitTransaction", (array)$obj);

        if($oTrx->code == 0) {

            // Obtengo trx encriptada, que se envía a método para despliegue de formulario
            $obj = new stdClass();
            $obj->trx = $oTrx->result;
            //$obj->opts = json_encode(array(5,8,1));

            $this->_doPost($oTrx->paymentForm, (array)$obj, FALSE);

        } else {
            echo "ERROR en InitTransaction";
        }


    }


    /**
     * Inicio de transacción y donde se deben validar todos los
     * datos de entrada para la integridad de la transacción
     *
     * @return json
     */
    public function testing($res) {
        if($res == "ok" || $res == "error") {
            echo "Transacción terminó en ".strtoupper($res);
        } else {
            // Hace de notify
            echo "OK";
        }
    }
    public function InitTransaction() {

        $o = new stdClass();
        $o->code = -1;
        $o->message = "";
        $o->result = NULL;

        try {

            $obj = new stdClass();
            $obj->idUserExternal	= trim($this->input->post("idUserExternal"));
            $obj->codExternal		= trim($this->input->post("codExternal"));
            $obj->urlOk				= trim($this->input->post("urlOk"));
            $obj->urlError			= trim($this->input->post("urlError"));
            $obj->urlNotify			= trim($this->input->post("urlNotify"));
            $obj->commerceID		= trim($this->input->post("commerceID"));
            $obj->amount			= trim($this->input->post("amount"));

            // Inicio de transacción en el motor de pagos
            $post = $this->_doPost($this->config->item("InitTransaction"), (array)$obj);
            if($post->code != 0) throw new Exception($post->message, $post->code);

            // La transacción fue creada correctamente
            // Devuelve la
            $o->code = 0;
            $o->message = "Transacción OK 2";
            $o->result = $post->result; // recibe el idTrx encriptado
            $o->paymentForm = base_url()."api/ShowPaymentFormGet/".$o->result;

        } catch(Exception $e) {
            $o->code = $e->getCode();
            $o->message = $e->getMessage();
            log_message("error", __METHOD__ . " (". $o->code .")-> " . $e->getMessage());
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($o));

    }

    /**
     * Carga formulario con canales de pago habilitados para el comercio
     *
     * @return void
     */
    public function ShowPaymentForm() {

        $obj = new stdClass();
        $obj->trx = trim($this->input->post("trx")); // corresponde al idTrx encriptado
        $obj->opts = trim($this->input->post("opts"));

        // No espera respuesta, debe redireccionar desde el core del motor de pagos
        $this->_doPost($this->config->item("ListPaymentChannels"), (array)$obj, FALSE);
    }

    /**
     * Permite la carga de formulario de pago, pasando parámetros por GET
     *
     * @return void
     */
    public function ShowPaymentFormGet($trx, $opts = NULL) {

        $obj = new stdClass();
        $obj->trx = $trx; // corresponde al idTrx encriptado
        $obj->opts = $opts;

        // No espera respuesta, debe redireccionar desde el core del motor de pagos
        $this->_doPost($this->config->item("ListPaymentChannels"), (array)$obj, FALSE);
    }


    /**
     * Obtiene el listado de métodos de pago disponibles
     * para el id de comercio
     *
     * @return json
     */
    public function ListPaymentMethods() {

        $o = new stdClass();
        $o->code = 0;
        $o->message = "";
        $o->result = NULL;

        try {

            // Recibe el id de comercio
            $comm = trim($this->input->post("CommerceID"));
            if(empty($comm)) throw new Exception("No se ha recibido ningún identificador de comercio", 1000);

            // Verifica si el comercio es válido o no
            $format = "Y-m-d H:i:s";
            $oComm = $this->core_model->getCommerceByCode($comm);
            if(is_null($oComm)) throw new Exception("El comercio proporcionado no existe en el sistema", 1001);
            // Si está activo
            if((int)$oComm->active == 0) throw new Exception("El comercio no se encuentra activo", 1002);

            // Expirado o no
            $fechaIni = date($format, strtotime($oComm->contractStartDate));
            $fechaFin = date($format, strtotime($oComm->contractEndDate));
            $now = date($format, time());
            if(($now < $fechaIni) || ($now > $fechaFin)) throw new Exception("El comercio no se encuentra disponible", 1003);

            // El comercio está activo, busca listado
            $listPayments = $this->core_model->listPaymentMethodsByIdCommerce($oComm->idCommerce);

            $o->result = $listPayments;

        } catch(Exception $e) {
            log_message("error", __METHOD__ . " -> " . $e->getMessage());
        }

        echo json_encode($o);

    }



    // ------------------------- DEPRECADA -------------------------



    /**
     * Obtiene los detalles de la transacción
     *
     * @return json
     */
    public function GetTrxDetailsByCodExternal() {

        $o = new stdClass();
        $o->code = 0;
        $o->message = "";
        $o->result = NULL;

        try {

            $codExternal = trim($this->input->post("codExternal"));
            //$codExternal = "8fad053501484205e390958dc31a7c23";
            if(empty($codExternal)) throw new Exception("No se ha recibido ningún identificador", 1000);

            // Busca el detalle de la transacción
            $oTrx = $this->core_model->getTrxByCodExternal($codExternal);
            if(is_null($oTrx)) throw new Exception("No se ha podido identificar la transacción", 1001);

            // Obtiene el detalle dependiendo del método de pago
            $details = NULL;

            switch($oTrx->idPaymentType) {
                case self::WEBPAY_PLUS_ID:
                case self::REDCOMPRA_ID:


                    break;
                case self::ONECLICK_ID:

                    $details = $this->oneclick_model->getTrxDetailsByIdTrx($oTrx->idTrx);
                    if(is_null($details)) throw new Exception("No se ha podido identificar la transacción", 1003);

                    break;
                case self::TUSALDO_ID:

                    break;
                default:
                    throw new Exception("No se ha podido identificar el tipo de pago de la transacción", 1002);
                    break;
            }

            $o->result = $details;

        } catch(Exception $e) {
            $o->code = $e->getCode();
            $o->message = $e->getMessage();
            log_message("error", __METHOD__ . " (". $o->code .")-> " . $e->getMessage());
        }

        echo json_encode($o);

    }



    /**
     * Obtiene los detalles de la transacción por el buyOrder
     * Todos los métodos de Transbank están asociados a este
     *
     * @return json
     */
    public function GetTrxOneclickByBuyOrder() {

        $o = new stdClass();
        $o->code = -1;
        $o->message = "";
        $o->result = NULL;

        try {

            $buyOrder = trim($this->input->post("buyOrder"));
            if(empty($buyOrder)) throw new Exception("No se ha recibido ningún identificador", 1000);

            // Busca el detalle de la transacción
            $oTrx = $this->oneclick_model->getTrxDetailsByBuyOrder($buyOrder);
            if(is_null($oTrx)) throw new Exception("No se ha podido identificar la transacción", 1001);

            // OK
            $o->code = 0;
            $o->result = $oTrx;

        } catch(Exception $e) {
            $o->code = $e->getCode();
            $o->message = $e->getMessage();
            log_message("error", __METHOD__ . " (". $o->code .")-> " . $e->getMessage());
        }

        echo json_encode($o);

    }

}