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

            <div class="error-container">
                <img src="<?php echo base_url('assets/img/error.png'); ?>" alt="" width="75" />
            </div>

            <h2>Ha sucedido un error en la transacción</h2>

            <h3><?php echo $message; ?></h3>

            <?php if (!empty($url)) : ?>
                <button type=button onClick="goBack('<?= $url ?>')"  class="btn btn-danger">Volver</button>
            <?php endif; ?>
        </div>

        <div class="col-md-3 col-lg-2"></div> <!-- only for center -->

    </div>

</div>

<?php $this->load->view('channels/_scripts'); ?>
</body>
</html>