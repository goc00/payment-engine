<!doctype html>
<html lang="es">
<head>
    <?php $this->load->view('channels/_head'); ?>
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

            <?php if($error == 0) : ?>
                <h2>Transacci&oacute;n realizada satisfactoriamente</h2>

                <div class="error-container no-print">
                    <img src="<?php echo base_url('assets/img/success.png'); ?>" alt="" width="75" />
                </div>

                <div class="voucher-data">
                    <div class="table-container">
                        <table class="table">
                            <tbody class="success-table">
                            <tr>
                                <td>Order de compra</td>
                                <td><?php echo $buyOrder; ?></td>
                            </tr>
                            <tr>
                                <td>Nombre del comercio</td>
                                <td>3GMotion S.A.</td>
                            </tr>
                            <tr>
                                <td>Monto</td>
                                <td><?php echo $amount.' '.$currency; ?></td>
                            </tr>
                            <tr>
                                <td>C&oacute;digo de autorizaci&oacute;n</td>
                                <td><?php echo $authorizationCode; ?></td>
                            </tr>
                            <tr>
                                <td>Fecha</td>
                                <td><?php echo $transactionDate; ?></td>
                            </tr>
                            <tr>
                                <td>Tipo de Pago</td>
                                <td><?php echo $paymentType; ?></td>
                            </tr>
                            <tr>
                                <td>Tipo de cuotas</td>
                                <td><?php echo (isset($sharesType)) ? $sharesType : 'Cr&eacute;dito sin cuotas' ?></td>
                            </tr>
                            <tr>
                                <td>Número de cuotas</td>
                                <td><?php echo (isset($sharesNumber)) ? $sharesNumber : 'Cr&eacute;dito sin cuotas' ?></td>
                            </tr>
                            <tr>
                                <td>Tarjeta de cr&eacute;dito</td>
                                <td><?php echo $cardNumber; ?></td>
                            </tr>
                            <tr>
                                <td>Descripci&oacute;n</td>
                                <td><?php echo $description; ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="back-container no-print">
                        <button type="button" onClick="goBack('<?php echo $returnUrl; ?>')" class="btn btn-success">
                            VOLVER
                        </button>
                    </div>
                </div>

                <div class="row no-print">
                    <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
                        <button id="pdf" type="button" class="btn btn-warning">GUARDAR</button>
                    </div>
                    <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
                        <button id="print" type="button" class="btn btn-warning">IMPRIMIR</button>
                    </div>

                    <a href="#" download id="download" hidden></a>
                </div>
            <?php else : ?>

                <div class="error-container">
                    <img src="<?php echo base_url('assets/img/error.png'); ?>" alt="" width="75" />
                </div>

                <div class="row">
                    <div class="col-md-4 col-lg-3"></div> <!-- only for center -->

                    <div id="error-decription" class="col-md-4 col-lg-6 text-center">
                        <div class="div-details">
                            <div>Transacci&oacuten Rechazada N&deg;:</div>
                            <div><b><?= $buyOrder ?></b></div>
                        </div>

                        <div style="text-align:left; font-size:.8em; margin:40px auto">
                            <div style="font-weight:bold; margin-bottom: 1em;">Las posibles causas de este rechazo son:</div>
                            <ul>
                                <li>Error en el ingreso de los datos de su tarjeta de Cr&eacute;dito (fecha y/o c&oacute;digo de seguridad).</li>
                                <li>Su tarjeta de Cr&eacute;dito no cuenta con el cupo necesario para cancelar la compra.</li>
                                <li>Tarjeta a&uacute;n no habilitada en el sistema financiero.</li>
                            </ul>
                        </div>

                        <button type="button" class="btn btn-danger" onClick="goBack('<?= $errorUrl ?>')">VOLVER</button>
                    </div>

                    <div class="col-md-4 col-lg-3"></div> <!-- only for center -->
                </div>

            <?php endif; ?>
        </div>

        <div class="col-md-3 col-lg-2"></div> <!-- only for center -->

    </div>
</div>

<script>
    const send = {
        logo:               "<?php echo !isset($logo) ? null : $logo; ?>",
        buyOrder:           "<?php echo (!isset($buyOrder)) ? '' : $buyOrder; ?>",
        amount:             "<?php echo (!isset($amount)) ? '' : $amount.' '.$currency; ?>",
        authorizationCode:  "<?php echo (!isset($authorizationCode)) ? '' : $authorizationCode; ?>",
        creationDate:       "<?php echo !isset($transactionDate) ? '' : $transactionDate; ?>",
        last4CardDigits:    "<?php echo !isset($cardNumber) ? '' : $cardNumber; ?>",
        sharesType:         "<?php echo !isset($sharesType) ? '' : $sharesType; ?>",
        description:        "<?php echo !isset($description) ? '' : $description; ?>",
        paymentType:        "<?php echo !isset($paymentType) ? '' : $paymentType; ?>",
        sharesType:         "<?php echo !isset($sharesType) ? '' : $sharesType; ?>",
        sharesNumber:       "<?php echo !isset($sharesNumber) ? '' : $sharesNumber; ?>"
    };
</script>

<?php $this->load->view('channels/_scripts'); ?>
</body>
</html>