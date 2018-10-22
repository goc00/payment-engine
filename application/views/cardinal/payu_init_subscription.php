<!DOCTYPE html>
<html>
<head>
  <title>Payu Provider</title>
  <script src="https://code.jquery.com/jquery-3.2.1.min.js" crossorigin="anonymous"></script>
  
  <!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

  <!-- Optional theme -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

  <!-- Latest compiled and minified JavaScript -->
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</head>

<body>
  <div class="content">
    <div class="row">
      <div class="col-xs-6 col-xs-offset-3">
        <form method="get" action="http://localhost:3000/PayuProvider/subscribe">
          <h2>Datos del cliente y TC</h2>
<!-- // 
      // 
      // (OPCIONAL) Ingresa aquí el documento de identificación del pagador
      PayUParameters::PAYER_DNI => "1020304050",
      // (OPCIONAL) Ingresa aquí la primera línea de la dirección del pagador
      PayUParameters::PAYER_STREET => "Address Name",
      // (OPCIONAL) Ingresa aquí la segunda línea de la dirección del pagador
      PayUParameters::PAYER_STREET_2 => "17 25",
      // (OPCIONAL) Ingresa aquí la tercera línea de la dirección del pagador
      PayUParameters::PAYER_STREET_3 => "Of 301",
      // (OPCIONAL) Ingresa aquí la ciudad de la dirección del pagador
      PayUParameters::PAYER_CITY => "City Name",
      // (OPCIONAL) Ingresa aquí el estado o departamento de la dirección del pagador
      PayUParameters::PAYER_STATE => "State Name",
      // (OPCIONAL) Ingresa aquí el código del país de la dirección del pagador
      PayUParameters::PAYER_COUNTRY => "CO",
      // (OPCIONAL) Ingresa aquí el código postal de la dirección del pagador
      PayUParameters::PAYER_POSTAL_CODE => "00000",
      // (OPCIONAL) Ingresa aquí el número telefónico del pagador
      PayUParameters::PAYER_PHONE => "300300300", -->
        <div class="row">
          <div class="col-xs-6">
            <label for="value">Número de cuotas a pagar.</label>
            <input type="text" class="form-control" name="INSTALLMENTS_NUMBER" value="1">
          </div>
          <div class="col-xs-6">
            <label for="value">Cantidad de días de prueba</label>
            <input type="text" class="form-control" name="TRIAL_DAYS" value="10">
          </div>
          <div class="col-xs-6">
            <label for="value">Nombre del cliente</label>
            <input type="text" class="form-control" name="CUSTOMER_NAME" value="Pedro Perez">
          </div>
          <div class="col-xs-6">
            <label for="value">Email del cliente</label>
            <input type="text" class="form-control" name="CUSTOMER_EMAIL" value="pperezz@payulatam.com">
          </div>
          <div class="col-xs-6">
            <label for="value">Nombre del pagador</label>
            <input type="text" class="form-control" name="PAYER_NAME" value="Sample User Name">
          </div>
          <div class="col-xs-6">
            <label for="value">Número de la tarjeta de crédito</label>
            <input type="text" class="form-control" name="CREDIT_CARD_NUMBER" value="4242424242424242">
          </div>
          <div class="col-xs-6">
            <label for="value">Fecha de expiración de la tarjeta de crédito en formato AAAA/MM</label>
            <input type="text" class="form-control" name="CREDIT_CARD_EXPIRATION_DATE" value="2014/12">
          </div>
          <div class="col-xs-6">
            <label for="value">Nombre de la franquicia de la tarjeta de crédito</label>
            <input type="text" class="form-control" name="PAYMENT_METHOD" value="VISA">
          </div>
          <div class="col-xs-6">
            <label for="value">Documento de identificación asociado a la tarjeta</label>
            <input type="text" class="form-control" name="CREDIT_CARD_DOCUMENT" value="1020304050">
          </div>
        </div>

        <h2>Parámetros del plan</h2>

        <div class="row">
          <div class="col-xs-6">
            <label for="value">Descripción del plan</label>
            <input type="text" class="form-control" name="PLAN_DESCRIPTION" value="Sample Plan 001">
          </div>
          <div class="col-xs-6">
            <label for="value">Código de identificación para el plan</label>
            <input type="text" class="form-control" name="PLAN_CODE" value="sample-plan-code-001">
          </div>
          <div class="col-xs-6">
            <label for="value">Intervalo del plan</label>
            <input type="text" class="form-control" name="PLAN_INTERVAL" value="MONTH">
          </div>
          <div class="col-xs-6">
            <label for="value">Cantidad de intervalos</label>
            <input type="text" class="form-control" name="PLAN_INTERVAL_COUNT" value="1">
          </div>
          <div class="col-xs-6">
            <label for="value">Moneda para el plan</label>
            <input type="text" class="form-control" name="PLAN_CURRENCY" value="COP">
          </div>
          <div class="col-xs-6">
            <label for="value">Valor del plan</label>
            <input type="text" class="form-control" name="PLAN_VALUE" value="10000">
          </div>
          <div class="col-xs-6">
            <label for="value">Valor del impuesto</label>
            <input type="text" class="form-control" name="PLAN_TAX" value="1600">
          </div>
          <div class="col-xs-6">
            <label for="value">Base de devolución sobre el impuesto</label>
            <input type="text" class="form-control" name="PLAN_TAX_RETURN_BASE" value="8400">
          </div>
          <div class="col-xs-6">
            <label for="value">Cuenta Id del plan</label>
            <input type="text" class="form-control" name="ACCOUNT_ID" value="512321">
          </div>
          <div class="col-xs-6">
            <label for="value">Intervalo de reintentos</label>
            <input type="text" class="form-control" name="PLAN_ATTEMPTS_DELAY" value="1">
          </div>
          <div class="col-xs-6">
            <label for="value">Cantidad de cobros que componen el plan</label>
            <input type="text" class="form-control" name="PLAN_MAX_PAYMENTS" value="12">
          </div>
          <div class="col-xs-6">
            <label for="value">Cantidad total de reintentos para cada pago rechazado de la suscripción</label>
            <input type="text" class="form-control" name="PLAN_MAX_PAYMENT_ATTEMPTS" value="3">
          </div>
          <div class="col-xs-6">
            <label for="value">Cantidad máxima de pagos pendientes que puede tener una suscripción antes de ser cancelada.</label>
            <input type="text" class="form-control" name="PLAN_MAX_PENDING_PAYMENTS" value="1">
          </div>
          <div class="col-xs-6">
            <label for="value">Cantidad de días de prueba de la suscripción</label>
            <input type="text" class="form-control" name="TRIAL_DAYS" value="30">
          </div>
        </div>
        
        <div class="row">
          <div class="col-xs-12">
            <button type="submit" class="pull-right pull-left-xs btn btn-primary">Pagar</button>
          </div>
        </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>


