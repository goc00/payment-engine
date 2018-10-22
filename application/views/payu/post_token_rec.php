<html>
    <head>
        <title>Procesando respuesta con plataforma PayU...</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    </head>
    <!--  <body> -->
    <body onload="document.forms['id-form'].submit();"> 
        Procesando respuesta con plataforma PayU...
		<form id="id-form" action="<?= $go; ?>" method="POST">
		    <input type="hidden" name="trx" value="<?= $trx ?>" />
		</form>
    </body>
</html>