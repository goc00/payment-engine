<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
	<title>Ingreso Sistema</title>
	<style>
		body { font-family:Arial,Verdana }
		.titulo { font-size: 1.4em; font-weight:bold; margin:20px 0;  }
		.destacar { background:red;font-size:1.12em;color:white;text-align:center }
		.tablee { font-size:.8em }
		.ready { background:#81F781 }
	</style>
</head>

<body>
	<div class="titulo">Ingreso Sistema</div>
	<div>
		<form action="<?= base_url() ?>admin/loginAction" method="POST">
			<table>
				<tr>
					<td>Nombre Usuario:</td>
					<td><input type="text" name="txtUsername" id="txtUsername" /></td>
				</tr>
				<tr>
					<td>Contrase&ntilde;a:</td>
					<td><input type="password" name="txtPass" id="txtPass" /></td>
				</tr>
				<tr>
					<td colspan="2" align="right"><input type="submit" value="Ingresar" /></td>
				</tr>
			</table>
		</form>
	</div>
</body>
</html>