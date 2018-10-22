<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
	<title>Listado de Transacciones</title>
	<style>
		body { font-family:Arial,Verdana }
		.titulo { font-size: 1.4em; font-weight:bold; margin:20px 0;  }
		.destacar { background:red;font-size:1.12em;color:white;text-align:center }
		.tablee { font-size:.8em }
		.ready { background:#81F781 }
	</style>
</head>

<body>
	<div class="titulo">Listado de Transacciones</div>
	<div><?= $trxs ?></div>

	<script>
	$(document).ready(function() {
		
	});
	</script>
</body>
</html>