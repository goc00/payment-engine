<!doctype html>
<html lang="es">
<head>
    <?php $this->load->view('channels/_head'); ?>
    <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

        ga('create', '<?= $googleAnalytics; ?>', 'auto');
        ga('send', 'pageview', '/payment-form');
    </script>
	<script>
	   (function(h,o,t,j,a,r){
		   h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
		   h._hjSettings={hjid:757649,hjsv:6};
		   a=o.getElementsByTagName('head')[0];
		   r=o.createElement('script');r.async=1;
		   r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
		   a.appendChild(r);
	   })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
	</script>
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

            <h1>Realizar transacci&oacute;n</h1>

            <span>(Tu pago se realizar&aacute; a nombre de <b>3GMotion S.A</b>)</span>

            <div class="row">
                <div class="col-md-2 col-lg-3"></div> <!-- only for center -->

                <div id="payment-decription" class="col-md-8 col-lg-6 text-center">
                    <div class="row total">
                        <div class="col-md-6 total-text-container">
                            <span class="total-text">Monto a pagar:</span>
                        </div>
                        <div class="col-md-6">
                            <span class="total-number"><?php echo $amount; ?></span>
                        </div>
                    </div>

                    <div class="row">
                        <form id="frmPayment" action="<?= $action ?>" method="post">

                            <div class="terms">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input chk-terms">
                                    Acepto los t&eacute;rminos y condiciones
                                </label>
                            </div>

                            <div class="channels-list">
                                <?= $paymentChannels; ?>
                            </div>

                            <input type="hidden" id="ok" name="ok" value="<?php echo $urlOk; ?>" />
                            <input type="hidden" id="error" name="error" value="<?php echo $urlError; ?>" />

                            <?php if ($showChannels) { ?>
                                <input type="hidden" id="token" name="token" value="<?php echo $idTrxEncrypted; ?>" />
                                <input id="option" name="option" type="hidden" value="" />
                                <div style="margin:auto;width:24px;display:none" id="loading">
                                    <img src="<?= base_url() ?>assets/img/ajax-loader.gif" width="24" height="24" alt="Procesando..." title="Procesando..." />
                                </div>
                            <?php } ?>

                        </form>
                    </div>

                </div>

                <div class="col-md-2 col-lg-3"></div> <!-- only for center -->
            </div>

            <?php $this->load->view('channels/terms'); ?>


            <!-- START Modal for extra inputs -->
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#extraInputs" id="btnExtraInputs" style="display:none">
                Launch demo modal
            </button>
            <!-- END -->

            <div class="cancel-container">
                <?php if (strpos(base_url(), 'localhost') !== false
                    || strpos(base_url(), 'dev') !== false
                    || strpos(base_url(), 'int') !== false) : ?>
                    <a id="cancelBtn" href="#">Anular Pago</a>
                <?php else : ?>
                    <a id="cancelBtn" href="#" onclick="ga('send', 'event', 'Btn_Anular_pago', 'click', 'Btn_Anular_pago');">
                        Anular Pago
                    </a>
                <?php endif; ?>
            </div>

        </div>

        <div class="col-md-3 col-lg-2"></div> <!-- only for center -->

    </div> <!-- class="row" -->

</div> <!-- class="container" -->

    <?php $this->load->view('channels/_scripts'); ?>
    <script>
        //$("#btnExtraInputs").trigger("click");
    </script>    
</body>
</html>