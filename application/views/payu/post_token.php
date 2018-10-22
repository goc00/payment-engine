<html>
    <head>
        <title>Procesando pago con plataforma PayU...</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    </head>
    <!--  <body> -->
    <body onload="document.forms['id-form'].submit();"> 
        Procesando pago con plataforma PayU...
		<form id="id-form" action="<?= $parameters["url"]; ?>" method="POST">
            <?php foreach($parameters as $key => $value) { ?>
			    <input type="hidden" name="<?= $key ?>" value="<?= $value ?>" />
            <?php } ?>
		</form>
    </body>
</html>