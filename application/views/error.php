<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>3GPaymentEngine</title>
</head>
<body>

<div style="margin:auto;text-align:center">
<h2><?= $message ?></h2>
<button onClick="goBack()">Volver</button>
</div>

<script>
function goBack() {
    window.history.back();
}
</script>
</body>
</html>