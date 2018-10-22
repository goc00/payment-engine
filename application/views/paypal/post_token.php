<html>
    <head>
        <title>Redireccionando a PayPal...</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    </head>
    <body onload="document.forms['id-form'].submit();">
	<!-- <body> -->
		<form id="id-form" action="<?= $url; ?>" method="POST" />
    </body>
</html>