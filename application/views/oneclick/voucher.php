<?php $this->load->view("includes/top_view"); ?>
	
<div class="table">
	<div class="td" style="background-color:<?= $bgColor ?>; color:<?= $fontColor ?>">
		<div class="container">
			<?php if(!is_null($logo)) { ?>
				<div><img src="<?= $logo ?>" alt="" /></div>
			<?php } ?>
			<h2>Transacci&oacute;n realizada satisfactoriamente</h2>
			
			<div style="font-size:.7em">
			
				<div class="div-details">
					<div>N&uacute;mero de Orden de Compra</div>
					<div><b><?= $buyOrder ?></b></div>
				</div>
				<div class="div-details">
					<div>Nombre del comercio</div>
					<div><b>3GMotion S.A.</b></div>
				</div>
				<div class="div-details">
					<div>Monto</div>
					<div><b><?= "$".number_format($amount, 0,",",".") ?> CLP</b></div>
				</div>
				<div class="div-details">
					<div>C&oacute;digo de autorizaci&oacute;n</div>
					<div><b><?= $authorizationCode ?></b></div>
				</div>
				<div class="div-details">
					<div>Fecha</div>
					<div><b><?= date("d/m/Y", strtotime($creationDate)) ?></b></div>
				</div>
				<div class="div-details">
					<div>Tarjeta asociada</div>
					<div><b><?= "XXXX XXXX XXXX ".$last4CardDigits ?></b></div>
				</div>
				<div class="div-details">
					<div>Tipo Pago</div>
					<div><b>Cr&eacute;dito sin cuotas</b></div>
				</div>
				<div class="div-details">
					<div>Descripci&oacute;n</div>
					<div><b><?= $description ?></b></div>
				</div>
			
			</div>
			
			<button onClick="goBack('<?= $urlOk ?>')" class="normal-btn">Retornar a Comercio</button>

		</div>
	</div>
</div>
<?php $this->load->view("includes/bottom_view"); ?>