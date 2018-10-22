<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
	<title>3GPaymentEngine</title>
</head>
<body bgcolor="#ccc">

<div>
	<a href="<?= $link ?>">Prueba inicio transacci&oacute;n</a>
</div>
<div id="target" style="width:500px;margin-left:auto;margin-right:auto;height:400px;top:200px;background:#fff">
</div>

<script>
$(document).ready(function() {
	$("#target").load("<?= $link ?>");
});
</script>
</body>
</html>