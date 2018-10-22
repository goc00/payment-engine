<?php $this->load->view("includes/top_view"); ?>

<div class="table">
	<div class="td">
		<div class="container">
		
			
			<?php if($error == 0) { ?>

				<h2>La Inscripci&oacute;n ha finalizado satisfactoriamente</h2>
				
				<button onClick="goBack('<?= $returnUrl ?>')" class="normal-btn">Retornar a Comercio</button>
				
			<?php } else { ?>
				
				<div class="div-info-voucher">
					<div>Transacci&oacute;n Rechazada N&deg;:</div>
					<div><?= $buyOrder ?></div>
				</div>

				<button onClick="goBack('<?= $errorUrl ?>')" class="normal-btn">Retornar a Comercio</button>
			
			<?php } ?>
		</div>
	</div>
</div>

<?php $this->load->view("includes/bottom_view"); ?>