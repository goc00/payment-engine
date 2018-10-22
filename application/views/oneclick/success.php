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

            <h2>Transacción realizada satisfactoriamente</h2>

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
                                <td><?php echo '$'.number_format($amount, 0, ',', '.'); ?> CLP</td>
                            </tr>
                            <tr>
                                <td>C&oacute;digo de autorizaci&oacute;n</td>
                                <td><?php echo $authorizationCode; ?></td>
                            </tr>
                            <tr>
                                <td>Fecha</td>
                                <td><?php echo date('d/m/Y', strtotime($creationDate)); ?></td>
                            </tr>
                            <tr>
                                <td>Tarjeta asociada</td>
                                <td><?php echo 'XXXX XXXX XXXX '.$last4CardDigits; ?></td>
                            </tr>
                            <tr>
                                <td>Tipo Pago</td>
                                <td>Cr&eacute;dito sin cuotas</td>
                            </tr>
                            <tr>
                                <td>Descripci&oacute;n</td>
                                <td><?php echo $description; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="back-container no-print">
                <button type="button" onClick="goBack('<?php echo $urlOk; ?>')" class="btn btn-success">VOLVER</button>
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
        </div>

        <div class="col-md-3 col-lg-2"></div> <!-- only for center -->

    </div>
</div>

<script>
    const send = {
        logo:               "<?php echo !isset($logo) ? null : $logo; ?>",
        buyOrder:           "<?php echo (!isset($buyOrder)) ? '' : $buyOrder; ?>",
        amount:             "<?php echo (!isset($amount)) ? '' : $amount; ?>",
        authorizationCode:  "<?php echo (!isset($authorizationCode)) ? '' : $authorizationCode; ?>",
        creationDate:       "<?php echo (!isset($creationDate)) ? '' : date('d/m/Y', strtotime($creationDate)); ?>",
        last4CardDigits:    "<?php echo (!isset($last4CardDigits)) ? '' : 'XXXX XXXX XXXX '.$last4CardDigits; ?>",
        sharesType:         "Cr&eacute;dito sin cuotas",
        description:        "<?php echo !isset($description) ? '' : $description; ?>"
    }
</script>

<?php $this->load->view('channels/_scripts'); ?>
</body>
</html>