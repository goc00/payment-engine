<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href='http://fonts.googleapis.com/css?family=Nunito:400,300' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" type="text/css" href="<?= base_url() ?>assets/css/theme.css">
	<title>3GPaymentEngine</title>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
</head>
<body>

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
				if(count($initialFields) <= 0) {
			?>
				No se han definido los campos para este m&eacute;todo de pago 
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
							<label for="radio_am_<?= $pt->idPaymentType ?>" class="light"><?= $pt->name ?> (<?= $pt->amountFormatted ?>)</label><br />
						<input type="hidden" id="am_<?= $pt->idPaymentType ?>" name="am_<?= $pt->idPaymentType ?>" value="<?= $pt->amount ?>" />
					<?php
						}
					?>
					</div>
					
					
					<legend><span class="number">2</span>Completa los datos:</legend>
					<div id="tblFieldsPayment">
						
			<?php
					foreach($initialFields as $iniF) {
			?>
					<?= $iniF->fieldHTML; ?>
					
			<?php
					}
			?>
						
					</div>
					
					<iframe frameborder="0" allowtransparency="true" scrolling="no" id="iOperator" src="<?= $actionOperator; ?>"></iframe>
			<?php
				}
				// Muestra métodos de pago
			?>
				</fieldset>
			<?php
			}
			?>
			<input type="hidden" id="trx" name="trx" value="<?= $data->trx ?>" />
			<div style="margin:auto;width:24px;display:none" id="loading"><img src="<?= base_url() ?>assets/img/ajax-loader.gif" width="24" height="24" alt="Procesando..." title="Procesando..." /></div>
			<button type="submit">Procesar Pago</button>
		</form>
	</div>
</div>
<script>
	$(document).ready(function() {
		
		// cambio PaymentType
		$('input[type=radio][name=PT]').change(function() {
			// Condición para no entrar a flujo normal por operador
			var $id = $(this).val();
			if($id == 3) {
				// Operador
				var seconds = 2;
				$("#tblFieldsPayment").empty(); // limpia la tabla de campos
				$("button[type='submit']").hide();
				$("#iOperator").css("display", "block");
				
				// Crea una llamada en ajax para verificar el cambio en el estado de la transacción
				var interv = setInterval(function() {
					
					$.post(
						"<?= $actionOperatorCheck ?>",
						{ trx: "<?= $data->trx ?>" },
						function(response) {
							
							//console.log(response.status);
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
			} else {
				// Flujo normal de medios de pagos
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
								$('#tblFieldsPayment').append('<label for="'+f.htmlId+'">'+f.htmlLabel+'</label>'+f.fieldHTML);
							});
						} else {
							alert(response.message);
						}
					}, "json"
				);
				
			}
			
			
		});
		$("#frmPayment").submit(function() {
			$("button[type='submit']").hide();
			$("#loading").show();
		});
		
	});
</script>
</body>
</html>