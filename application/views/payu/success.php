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
                    <img src="<?= $logo; ?>" alt="" />
                </div>
            <?php endif; ?>

            <h2>Transacción realizada satisfactoriamente</h2>

            <div class="error-container no-print">
                <img src="<?= base_url('assets/img/success.png'); ?>" alt="" width="75" />
            </div>

            <div class="voucher-data">
                <div class="table-container">
                    <table class="table">
                        <tbody class="success-table">
                            <tr>
                                <td>Orden de compra</td>
                                <td><?= $buyOrder; ?></td>
                            </tr>
                            <tr>
                                <td>Nombre del comercio</td>
                                <td><?= $commerceName ?></td>
                            </tr>
                            <tr>
                                <td>M&eacute;todo de Pago</td>
                                <td><?= $paymentMethod ?> (<?= $fee ?> cuota(s))</td>
                            </tr>
                            <tr>
                                <td>Monto</td>
                                <td><?= $amount; ?> <?= $currency; ?></td>
                            </tr>
                            <tr>
                                <td>Estado</td>
                                <td><?= $status; ?></td>
                            </tr>
                            <tr>
                                <td>Fecha</td>
                                <td><?= $creationDate; ?></td>
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