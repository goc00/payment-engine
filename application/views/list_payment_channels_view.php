<?php $this->load->view("includes/top_view"); ?>
<script>
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

	ga('create', '<?= $googleAnalytics ?>', 'auto');
	ga('send', 'pageview', '/payment-form');

</script>
<div class="table">

	<div class="td" style="background-color:<?= $bgColor ?>; color:<?= $fontColor ?>">

		<div class="container">

			<div class="details-frm">
				<?php if(!is_null($logo)) { ?>
				<div><img src="<?= $logo ?>" alt="" /></div>
				<?php } ?>
				<div class="monto">Monto a pagar: <b><?= $amount ?></b></div>
				<div style="font-size:.7em">(Tu pago se realizar&aacute; a nombre de <b>3GMotion S.A</b>)</div>
			</div>
			<div>
				<form id="frmPayment" action="<?= $action ?>" method="post">

					<div class="white"><?= $paymentChannels ?></div>

					<input type="hidden" id="ok" name="ok" value="<?= $urlOk ?>" />
					<input type="hidden" id="error" name="error" value="<?= $urlError ?>" />
					<?php if($showChannels) { ?>

						<input type="hidden" id="token" name="token" value="<?= $idTrxEncrypted ?>" />
						<input id="option" name="option" type="hidden" value="" />
						<div style="margin:auto;width:24px;display:none" id="loading"><img src="<?= base_url() ?>assets/img/ajax-loader.gif" width="24" height="24" alt="Procesando..." title="Procesando..." /></div>

					<?php } ?>

				</form>
				<input id="cancelBtn" type="button" value="Anular Pago" />
			</div>

		</div>
	</div>
</div>
<!-- Hotjar Tracking Code for https://digevopayments.com/ -->
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
<?php $this->load->view("includes/bottom_view"); ?>