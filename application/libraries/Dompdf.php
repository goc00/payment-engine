<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH.'third_party/dompdf/autoload.inc.php');

class Dompdf extends Dompdf\Dompdf
{
    public function __construct()
    {
        parent::__construct();
    }
}