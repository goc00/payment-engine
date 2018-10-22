<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">

<title>Motor de Pagos - 3GMotion S.A.</title>

<!-- Bootstrap -->
<link href="<?php echo base_url('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>" rel="stylesheet">

<!-- SweetAlert -->
<script src="<?php echo base_url('assets/vendor/sweetalert/sweetalert2.min.js'); ?>"></script>
<link rel="stylesheet" href="<?php echo base_url('assets/vendor/sweetalert/sweetalert2.min.css'); ?>">

<style>
    @import url('https://fonts.googleapis.com/css?family=Lato:300,400,700,900');

    html, body {
        font-family: 'Lato', sans-serif;
        background-color: #f9f9f9;
    }

    .hide {
        display: none;
    }

    .container {
        padding-top: 3em;
    }

    .logo {
        margin-bottom: 2em;
    }

    .logo img {
        height: 100px;
    }

    .total {
        margin-top: 15px;
    }

    .total-text {
        font-size: 15pt;
        font-weight: 400;
    }

    .total-number {
        font-size: 22pt;
        font-weight: 700;
    }

    .item {
        margin-top: 2.5em;
        margin-bottom: 2.5em
    }

    .channels-list .item:not(:last-of-type) {
        border-bottom: 2px solid #efefef;
        padding-bottom: 2em;
    }

    .oneclick-title-terms {
        background-color: #26abe2!important;
        color: #FFFFFF!important;
    }

    .webpay-title-terms {
        background-color: #ff9122!important;
        color: #FFFFFF!important;
    }

    .cuentarut-title-terms {
        background-color: #002f6e!important;
        color: #FFFFFF!important;
    }

    .panel-body {
        text-align: justify;
        background-color: #ffffff!important;
    }

    ol {
        padding-right: 2em;
    }

    .panel-group {
        margin-top: 5em;
    }

    .cancel-container {
        margin-bottom: 2.5em;
    }

    .error-container {
        margin: 2.5em 0 2.5em 0;
    }

    .btn-danger {
        width: 15em;
        margin-top: 2em;
    }

    .table-container {
        padding: 0 15% 0 15%;
    }
    .success-table {
        text-align: left;
    }

    .back-container {
        text-align: center;
        margin: 2.5em 0 2.5em 0;
    }

    .btn-success {
        width: 15em;
    }

    .terms {
        margin-top: 1em;
        border-bottom: 2px solid #efefef;
        height: 35px;
    }

    #payment-decription {
        background-color: #FFFFFF;
        border: 2px solid #efefef;
        margin-top: 1em;
    }

    #error-decription {
        background-color: #FFFFFF;
        border: 2px solid #efefef;
        padding: 1em 1em 1em 1em;
    }

    .row .total {
        border-bottom: 2px solid #efefef;
        height: 60px;
    }

    .total-text-container {
        padding-top: 0.5em;
    }

    .voucher-data {
        background: #fff;
        padding: 2em 0em 1em 0em;
        margin: 0 0 1em 0;
    }

    .section-panel-body {
        margin-bottom: 1em;
    }

    .section-panel-body strong {
        display: block;
    }

    .no-print {
        margin-bottom: 1em;
    }

    @page {
        size: auto;  margin: 0mm;
    }

    @media print
    {
        .no-print, .no-print *
        {
            display: none !important;
        }
    }

    @media only screen and (max-width: 993px)  {
        .row .total {
            border-bottom: 2px solid #efefef;
            height: 95px;
        }
    }

    @media only screen and (min-width: 993px) and (max-width: 1200px)  {
        .row .total {
            border-bottom: 2px solid #efefef;
            height: 90px;
        }
    }
</style>

<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->