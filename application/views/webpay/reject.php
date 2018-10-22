<?php $this->load->view("includes/top_view"); ?>

<div class="table">
	<div class="td">
		<div class="container">
		
			<h2>La transacci&oacute;n no ha podido ser validada</h2>
			
			<div class="div-info-voucher">
				<div>Transacci&oacuten Rechazada N&deg;:</div>
				<div><?= $buyOrder ?></div>
			</div>
			
			<div style="text-align:left;font-size:.8em;margin:40px auto">
				<div style="font-weight:bold">Las posibles causas de este rechazo son:</div>
				<ul>
					<li>Error en el ingreso de los datos de su tarjeta de Cr&eacute;dito (fecha y/o c&oacute;digo de seguridad).</li>
					<li>Su tarjeta de Cr&eacute;dito no cuenta con el cupo necesario para cancelar la compra.</li> 
					<li>Tarjeta a&uacute;n no habilitada en el sistema financiero.</li>
				</ul>
			</div>
				
			<button onClick="goBack('<?= $errorUrl ?>')" class="normal-btn">Retornar a Comercio</button>
			
		</div>
	</div>
</div>

<?php $this->load->view("includes/bottom_view"); ?>