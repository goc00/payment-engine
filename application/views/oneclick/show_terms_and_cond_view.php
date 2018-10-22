<?php $this->load->view("includes/top_view"); ?>
<div class="table">
	<div class="td" style="background-color:<?= $bgColor ?>; color:<?= $fontColor ?>">
		<div class="container">
			<?php if(!is_null($logo)) { ?>
				<div><img src="<?= $logo ?>" alt="" /></div>
			<?php } ?>
			
			<div  style="margin: 10px auto">
				<form class="form" action="<?= $action ?>" method="post">
					<input type="hidden" name="token" value="<?= $token ?>" />
					<button name="selection" type="submit" value="nok" class="normal-btn">No Acepto</button>
					<button name="selection" type="submit" value="ok" class="normal-btn action-btn">Acepto</button>
				</form>
			</div>
			
			<div style="text-align:center;margin:auto"><h3>T&eacute;rminos y Condiciones Webpay OneClick en <b><?= $commName ?></b></h3></div>
			<div style="font-size:.7em; text-align:justify; margin:auto">
			
				<ol>
			
					<li>Los usuarios de <b><?= $commName ?></b> podrán acceder a esta modalidad de pago llamada Webpay Oneclick, la cual consiste en realizar tus compras en <b><?= $host ?></b> solo con 1(un) click.</li>
					<li>Para acceder esta forma de pago, debes empezar por elegir la opci&oacute;n Webpay Oneclick y completar el formulario con los datos de tu tarjeta de cr&eacute;dito por &uacute;nica vez. Los datos son ingresados sobre Webpay Plus(*) y se redireccionar&aacute; a tu banco para ingresar las claves de seguridad correspondientes, por el monto de $1, el cual es solo referencial ya que no se ver&aacute; reflejado en tu estado de cuenta(**). Desde ese momento, al elegir la opci&oacute;n Webpay Oneclick podr&aacute;s realizar tus compras solo con presionar “Comprar”, solo con 1(un) click(***).</li>
					<li>Solo podr&aacute;s tener activa 1(una) tarjeta por cada cuenta de <b><?= $commName ?></b> que tengas. Si deseas terminar con este servicio solo debes desactivar tu tarjeta, en los datos de tu perfil o al realizar la pr&oacute;xima compra(****)</li>
				
				</ol>
				
				<ul style="font-size:.7em; margin-top: 10px; list-style: none">
					<li>(*) Webpay Plus, plataforma segura que cuenta con el respaldo de Transbank S.A.</li>
					<li>(**) Solo la primera vez "te inscribes y compras" en la misma transacci&oacute;n.</li>
					<li>(***) Aplica solo para ventas con tarjetas de cr&eacute;dito de emisores nacionales. No admite cuotas.</li>
					<li>(****) Cada usuario es responsable de la seguridad de su contrase&ntilde;a.</li>
				</ul>

			</div>
		</div>
	</div>
</div>
<?php $this->load->view("includes/bottom_view"); ?>