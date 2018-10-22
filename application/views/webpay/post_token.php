<html>
    <head>
        <title>Redireccionando a Transbank...</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    </head>
    <body onload="document.forms['id-form'].submit();">
		<!-- <div style="font-weight:bold">Redireccionando a Transbank...</div> -->
	<!-- <body> -->
		<form id="id-form" action="<?= $url; ?>" method="POST">
			<input type="hidden" name="token_ws" value="<?= $token ?>" />
		</form>
    </body>
</html>