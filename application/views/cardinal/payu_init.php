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
        <form method="get" action="PayuProvider/process">
          <div class="row">
            <div class="col-xs-6">
              <label for="value">Identificador de la cuenta. Aparece en el panel de PayU (678300 o 680645), https://secure.payulatam.com/accounts/</label>
              <input type="text" class="form-control" name="account_id" value="678300">
            </div>
            <div class="col-xs-6">
              <label for="value">Valor de la transacción</label>
              <input type="text" class="form-control" name="value" value="20000">
            </div>
            <div class="col-xs-6">
              <label for="value">Valor del IVA (si es nulo, se aplicará 19% automáticamente)</label>
              <input type="text" class="form-control" name="tax_value" value="3193">
            </div>
            <div class="col-xs-6">
              <label for="value">Valor sujeto a IVA (si producto no tiene IVA, se envía 0</label>
              <input type="text" class="form-control" name="tax_return_base" value="16806">
            </div>
            <div class="col-xs-6">
              <label for="value">E-mail del comprador</label>
              <input type="text" class="form-control" name="buyer_email" value="buyer_test@test.com">
            </div>
            <div class="col-xs-6">
              <label for="value">Nombre del comprador</label>
              <input type="text" class="form-control" name="payer_name" value="Takeshi">
            </div>
            <div class="col-xs-6">
              <label for="value">DNI</label>
              <input type="text" class="form-control" name="payer_dni" value="5415668464654">
            </div>
            <div class="col-xs-6">
              <label for="value">Medio de pago (BALOTO o EFECTY)</label>
              <input type="text" class="form-control" name="payment_method" value="BALOTO">
            </div>
            <div class="col-xs-6">
              <label for="value">Fecha de expiración</label>
              <input type="text" class="form-control" name="expiry_date" value="2014-09-26T00:00:00">
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


