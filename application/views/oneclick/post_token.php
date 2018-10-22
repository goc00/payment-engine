<html>
    <head>
        <title>Redireccionando a TransBank...</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    </head>
    <body onload="document.forms['id-form'].submit();">
	<!-- <body> -->
		<form id="id-form" action="<?= $url; ?>" method="POST">
			<input type="hidden" name="TBK_TOKEN" value="<?= $token ?>" />
		</form>
    </body>
</html>