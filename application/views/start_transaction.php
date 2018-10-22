<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href='http://fonts.googleapis.com/css?family=Nunito:400,300' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" type="text/css" href="<?= base_url() ?>assets/css/theme.css">
	<title>3GPaymentEngine</title>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
	<script src="<?= base_url() ?>assets/js/rut.js"></script>
</head>
<body>
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

  ga('create', '<?= $data->codAnalytics ?>', 'auto');
  ga('send', 'pageview');
</script>
<div>
	<div style="text-align:center;margin:auto"><img src="<?= $data->logo ?>" /></div>
	<div>
		<form id="frmPayment" action="<?= $action ?>" method="post">
		
			<h1>Formulario de Pago</h1>
		
			<?php
			if(is_null($paymentTypes)) {
			?>
			No hay ning&uacute;n m&eacute;todo de pago definido
			<?php
			} else {
			?>
				<fieldset>
					<legend><span class="number">1</span>Selecciona el m&eacute;todo de pago:</legend>
					<div style="margin-bottom:30px">
					<?php
						$first = TRUE;
						foreach($paymentTypes as $pt) {
							$selected = "";
							if($first) {
								$selected = " checked";
								$first = FALSE;
							}
					?>
						<input type="radio" id="radio_am_<?= $pt->idPaymentType ?>" value="<?= $pt->idPaymentType ?>"<?= $selected; ?> name="PT" />
						<label for="radio_am_<?= $pt->idPaymentType ?>" class="light"><?= $pt->description ?></label><br />
						<input type="hidden" id="am_<?= $pt->idPaymentType ?>" name="am_<?= $pt->idPaymentType ?>" value="<?= $pt->amount ?>" />
					<?php
						}
					?>
					</div>
					
					
					<legend><span class="number">2</span>Completa los datos:</legend>
					<div id="tblFieldsPayment">
						
			<?php
					if(count($initialFields) > 0) {
						foreach($initialFields as $iniF) {
			?>
						<?= $iniF->fieldHTML; ?>
			<?php
						}
					}
			?>	
					</div>
					<iframe frameborder="0" allowtransparency="true" scrolling="no" id="iOperator" src="<?= $actionOperator; ?>"></iframe>
				</fieldset>
			<?php
			}
			?>
			<input type="hidden" id="trx" name="trx" value="<?= $data->trx ?>" />
			<div style="margin:auto;width:24px;display:none" id="loading"><img src="<?= base_url() ?>assets/img/ajax-loader.gif" width="24" height="24" alt="Procesando..." title="Procesando..." /></div>
			<button type="submit">Procesar Pago</button>
			<button type="button" id="cancelBtn">Cancelar Pago</button>
		</form>
	</div>
</div>
<script>
	$(document).ready(function() {
		
		var webpayOpt = 1;
		var operadorOpt = 3;
		var paypalOpt = 4;
		var interv = null;
		
		// Para formatear el RUT
		$("body").on("blur", "#txtCardHolderId", function() {
			var elemento = $(this);
			if(valida_rut(elemento.val())) {
				formato_rut($(this)[0]);
			} else {
				alert("El formato del RUT no es válido");
				elemento.val("");
			}
		});
		
		$("body").on('keydown', '.cNumber', function(e){-1!==$.inArray(e.keyCode,[46,8,9,27,13,110,190])||/65|67|86|88/.test(e.keyCode)&&(!0===e.ctrlKey||!0===e.metaKey)||35<=e.keyCode&&40>=e.keyCode||(e.shiftKey||48>e.keyCode||57<e.keyCode)&&(96>e.keyCode||105<e.keyCode)&&e.preventDefault()});
		$("body").on('paste', '.cNumber', function(e){ e.preventDefault(); });
		
		// Botón cancelar
		$("#cancelBtn").click(function() {
			location.href = "<?= $cancelUrl ?>";
		});
		
		
		// cambio PaymentType
		$('input[type=radio][name=PT]').on("change", function() {
			// Condición para no entrar a flujo normal por operador
			var $id = $(this).val();
			if($id == operadorOpt) {
				// Operador
				loadOperator();
			} else {
				// Flujo normal de medios de pagos
				if(interv) { clearInterval(interv); }

				if($id == webpayOpt) ga('send', 'pageview', '/formulario-de-pago/webpay'); 
				else ga('send', 'pageview', '/formulario-de-pago/paypal');

				$.post(
					"<?= $actionPt ?>",
					{ idPaymentType: $id },
					function(response) {
						
						$("button[type='submit']").show();
						$("#iOperator").hide();
						
						if(response.err_number == 0) {
							$("#tblFieldsPayment").empty(); // limpia la tabla de campos
							// creo nuevos campos
							var fields = response.o;
							$.each(fields, function(i, f) {
								$('#tblFieldsPayment').append(f.fieldHTML);
							});
						} else {
							alert(response.message);
						}
					}, "json"
				);
				
			}
			
			
		});
		
		
		// Revisa si llega operador como primera opción, para levantar iframe o no
		var opt = $('input[type=radio][name=PT]');
		if(opt.val() == operadorOpt) { loadOperator(); }
		
		$("#frmPayment").submit(function() {
			$("button[type='submit']").hide();
			$("#loading").show();
		});
		
		
		function loadOperator() {
			ga('send', 'pageview', '/formulario-de-pago/sms');

			var seconds = 2;
			$("#tblFieldsPayment").empty(); // limpia la tabla de campos
			$("button[type='submit']").hide();
			$("#iOperator").css("display", "block");
			
			// Crea una llamada en ajax para verificar el cambio en el estado de la transacción
			interv = setInterval(function() {
				
				$.post(
					"<?= $actionOperatorCheck ?>",
					{ trx: "<?= $data->trx ?>" },
					function(response) {
						if(response.status == <?= $okStatusOpe ?> || response.status == <?= $errStatusOpe ?>) {
							clearInterval(interv);
							if(response.status == <?= $okStatusOpe ?>) {
								window.location.replace(response.okUrl);
							} else {
								window.location.replace(response.errUrl); 
							}
						}
						
					}, "json"
				);
				
			}, seconds * 1000);
		}
		
	});
</script>
</body>
</html>