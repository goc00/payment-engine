<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Motor de Pagos - 3GMotion S.A.</title>

    <link href="<?php echo base_url('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>" rel="stylesheet" type="text/css">

    <style rel="stylesheet" type="text/css" media="all">
        html, body {
            font-family: 'Roboto', sans-serif;
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
            font-size: 17pt;
            padding-top: 0.2em;
        }

        .total-number {
            font-size: 22pt;
        }

        .item {
            margin-top: 2.5em;
            margin-bottom: 2.5em;
        }

        .oneclick-title-terms {
            background-color: #00AFE8!important;
            color: #FFFFFF!important;
        }

        .panel-body {
            text-align: justify;
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
            margin-top: 2em;
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
    </style>
</head>
<body>
<div class="container">

    <div class="row">

        <div class="col-md-3 col-lg-2"></div> <!-- only for center -->

        <div class="col-md-6 col-lg-8 text-center">
            <?php if (!is_null($logo)) : ?>
                <div class="logo">
                    <img src="<?php echo $logo; ?>" alt="" />
                </div>
            <?php endif; ?>

            <h2>Transacción realizada satisfactoriamente</h2>

            <div class="table-container">
                <table class="table">
                    <tbody class="success-table">

                    <?php if ($buyOrder) : ?>
                    <tr>
                        <td>Order de compra</td>
                        <td><?php echo $buyOrder; ?></td>
                    </tr>
                    <?php endif; ?>

                    <tr>
                        <td>Nombre del comercio</td>
                        <td>3GMotion S.A.</td>
                    </tr>

                    <?php if (isset($amount) && !empty($amount)) : ?>
                    <tr>
                        <td>Monto</td>
                        <td><?php echo $amount; ?></td>
                    </tr>
                    <?php endif; ?>

                    <?php if ($authorizationCode) : ?>
                    <tr>
                        <td>C&oacute;digo de autorizaci&oacute;n</td>
                        <td><?php echo $authorizationCode; ?></td>
                    </tr>
                    <?php endif; ?>

                    <?php if ($creationDate) : ?>
                    <tr>
                        <td>Fecha</td>
                        <td><?php echo $creationDate; ?></td>
                    </tr>
                    <?php endif; ?>

                    <?php if ($paymentType) : ?>
                        <tr>
                            <td>Tipo de Pago</td>
                            <td><?php echo $paymentType; ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($sharesType) : ?>
                        <tr>
                            <td>Tipo de Pago</td>
                            <td><?php echo $sharesType; ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($sharesNumber) : ?>
                        <tr>
                            <td>Número de cuotas</td>
                            <td><?php echo $sharesNumber; ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($last4CardDigits) : ?>
                    <tr>
                        <td>Tarjeta asociada</td>
                        <td><?php echo $last4CardDigits; ?></td>
                    </tr>
                    <?php endif; ?>

                    <?php if ($description) : ?>
                    <tr>
                        <td>Descripci&oacute;n</td>
                        <td><?php echo $description; ?></td>
                    </tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-3 col-lg-2"></div> <!-- only for center -->

    </div>
</div>
</body>
</html>