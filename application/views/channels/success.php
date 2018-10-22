<!doctype html>
<html lang="es">
<head>
    <?php $this->load->view('channels/_head'); ?>
</head>
<body style="background-color:<?php echo $bgColor; ?>; color:<?php echo $fontColor; ?>">
<div class="container">

    <div class="row">

        <div class="col-md-3 col-lg-2"></div> <!-- only for center -->

        <div class="col-md-6 col-lg-8 text-center">
            <?php if (!is_null($logo)) : ?>
                <div class="logo">
                    <img src="<?php echo $logo; ?>" alt="" />
                </div>
            <?php endif; ?>

            <div class="error-container">
                <img src="<?php echo base_url('assets/img/success.png'); ?>" alt="" width="75" />
            </div>

            <h2>Transacción realizada satisfactoriamente</h2>

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

            <div class="back-container">
                <button type="button" onClick="goBack('<?php echo $urlOk; ?>')" class="btn btn-success">VOLVER</button>
            </div>

            <div class="row">
                <div class="col-sm-6 col-md-6 col-lg-6">
                    <button id="pdf" type="button" class="btn btn-warning">GUARDAR</button>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-6">
                    <button id="print" type="button" class="btn btn-warning">IMPRIMIR</button>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-lg-2"></div> <!-- only for center -->

    </div>
</div>

<?php $this->load->view('channels/_scripts'); ?>

<script>
    $('#pdf').click(function (event) {
        event.preventDefault();

        $.ajax({
            method: "POST",
            url: "<?php echo base_url('v2/transaction/generatePdf'); ?>"
        }).done(function( msg ) {
            console.log(msg);
        });
    });

    $('#print').click(function (event) {
        event.preventDefault();

        window.print();
    });
</script>
</body>
</html>