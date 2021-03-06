<!DOCTYPE html>
<html lang="es">
<html>
    <head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Transacci&oacute;n Finalizada</title>
		<link href='http://fonts.googleapis.com/css?family=Nunito:400,300' rel='stylesheet' type='text/css'>
		<link rel="stylesheet" type="text/css" href="<?= base_url() ?>assets/css/theme.css">
    </head>
    <body>
		
		<div style="text-align:center;margin:auto">
		
			<div id="legend">La transacci&oacute;n no ha podido ser validada</div>
			
			<div class="div-info-voucher">
				<div>Transacci&oacuten Rechazada N&deg;:</div>
				<div><?= $buyOrder ?></div>
			</div>
			
			<div style="width:30%;text-align:left;font-size:.8em;margin:40px auto">
				<div style="font-weight:bold">Las posibles causas de este rechazo son:</div>
				<ul>
					<li>Error en el ingreso de los datos de su tarjeta de Cr&eacute;dito (fecha y/o c&oacute;digo de seguridad).</li>
					<li>Su tarjeta de Cr&eacute;dito no cuenta con el cupo necesario para cancelar la compra.</li> 
					<li>Tarjeta a&uacute;n no habilitada en el sistema financiero.</li>
				</ul>
			</div>
				
			<button style="width:250px" onClick="error();">Volver al Comercio</button>
			
		</div>
		<script>
			function error(){
				window.location='<?= $errorUrl ?>';
			}
		</script>
		
    </body>
</html>