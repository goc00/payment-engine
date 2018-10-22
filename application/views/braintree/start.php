<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>3GPaymentEngine</title>

	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
	
	<style>
	/* Uses Bootstrap stylesheets for styling, see linked CSS*/
	body {
	  background-color: #fff;
	}

	.panel {
	  width: 80%;
	  margin: 2em auto;
	}

	.bootstrap-basic {
	  background: white;
	}

	.panel-body {
	  width: 90%;
	  margin: 2em auto;
	}

	.helper-text {
	  color: #8A6D3B;
	  font-size: 12px;
	  margin-top: 5px;
	  height: 12px;
	  display: block;
	}

	/* Braintree Hosted Fields styling classes*/
	.braintree-hosted-fields-focused { 
	  border: 1px solid #0275d8;
	  box-shadow: inset 0 1px 1px rgba(0,0,0,.075),0 0 8px rgba(102,175,233,.6);
	}

	.braintree-hosted-fields-focused.focused-invalid {
	  border: 1px solid #ebcccc;
	  box-shadow: inset 0 1px 1px rgba(0,0,0,.075),0 0 8px rgba(100,100,0,.6);
	}

	@media (max-width: 670px) {
	  .form-group {
		width: 100%;
	  }
	  
	  .btn {
		white-space: normal;
	  }
	}
	</style>
	
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.2/jquery.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
	
</head>
<body>
<!-- Bootstrap inspired Braintree Hosted Fields example -->
<div class="panel panel-default bootstrap-basic">
  <div class="panel-heading">
    <h3 class="panel-title">Detalles Tarjeta</h3>
  </div>
  <form class="panel-body">
    <div class="row">
      <div class="form-group col-xs-8">
        <label class="control-label">N&uacute;mero Tarjeta</label>
        <!--  Hosted Fields div container -->
        <div class="form-control" id="card-number"></div>
        <span class="helper-text"></span>
      </div>
      <div class="form-group col-xs-4">
        <div class="row">
          <label class="control-label col-xs-12">Fecha Expiraci&oacute;n</label>
          <div class="col-xs-6">
            <!--  Hosted Fields div container -->
            <div class="form-control" id="expiration-month"></div>
          </div>
          <div class="col-xs-6">
            <!--  Hosted Fields div container -->
            <div class="form-control" id="expiration-year"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="form-group col-xs-6">
        <label class="control-label">C&oacute;digo Seguridad</label>
        <!--  Hosted Fields div container -->
        <div class="form-control" id="cvv"></div>
      </div>
      <div class="form-group col-xs-6">
        <label class="control-label">C&oacute;digo Postal</label>
        <!--  Hosted Fields div container -->
        <div class="form-control" id="postal-code"></div>
      </div>
    </div>

    <button value="submit" id="submit" class="btn btn-success btn-lg center-block">Pagar con <span id="card-type">Tarjeta</span></button>
  </form>
  <form id="frmPayLoad" method="post" action="<?= $payload ?>">
	<input type="hidden" id="trx_txt" name="trx_txt" value="<?= $trx ?>" />
	<input type="hidden" id="description_txt" name="description_txt" value="" />
	<input type="hidden" id="cardType_txt" name="cardType_txt" value="" />
	<input type="hidden" id="lastTwo_txt" name="lastTwo_txt" value="" />
	<input type="hidden" id="nonce_txt" name="nonce_txt" value="" />
	<input type="hidden" id="type_txt" name="type_txt" value="" />
  </form>

<!-- Load the required client component. -->
<script src="https://js.braintreegateway.com/web/3.6.0/js/client.min.js"></script>

<!-- Load Hosted Fields component. -->
<script src="https://js.braintreegateway.com/web/3.6.0/js/hosted-fields.min.js"></script>

<!-- JS SDK BrainTree -->
<script>


	braintree.client.create({
	  authorization: '<?= $token ?>'
	}, function (err, clientInstance) {
	  if (err) {
		console.error(err);
		return;
	  }

	  braintree.hostedFields.create({
		client: clientInstance,
		styles: {
		  'input': {
			'font-size': '14px',
			'font-family': 'helvetica, tahoma, calibri, sans-serif',
			'color': '#3a3a3a'
		  },
		  ':focus': {
			'color': 'black'
		  }
		},
		fields: {
		  number: {
			selector: '#card-number',
			placeholder: '4111 1111 1111 1111'
		  },
		  cvv: {
			selector: '#cvv',
			placeholder: '123'
		  },
		  expirationMonth: {
			selector: '#expiration-month',
			placeholder: 'MM'
		  },
		  expirationYear: {
			selector: '#expiration-year',
			placeholder: 'AA'
		  },
		  postalCode: {
			selector: '#postal-code',
			placeholder: '99999'
		  }
		}
	  }, function (err, hostedFieldsInstance) {
		if (err) {
		  console.error(err);
		  return;
		}

		hostedFieldsInstance.on('validityChange', function (event) {
		  var field = event.fields[event.emittedBy];

		  if (field.isValid) {
			if (event.emittedBy === 'expirationMonth' || event.emittedBy === 'expirationYear') {
			  if (!event.fields.expirationMonth.isValid || !event.fields.expirationYear.isValid) {
				return;
			  }
			} else if (event.emittedBy === 'number') {
			  $('#card-number').next('span').text('');
			}
					
			// Apply styling for a valid field
			$(field.container).parents('.form-group').addClass('has-success');
		  } else if (field.isPotentiallyValid) {
			// Remove styling  from potentially valid fields
			$(field.container).parents('.form-group').removeClass('has-warning');
			$(field.container).parents('.form-group').removeClass('has-success');
			if (event.emittedBy === 'number') {
			  $('#card-number').next('span').text('');
			}
		  } else {
			// Add styling to invalid fields
			$(field.container).parents('.form-group').addClass('has-warning');
			// Add helper text for an invalid card number
			if (event.emittedBy === 'number') {
			  $('#card-number').next('span').text('Looks like this card number has an error.');
			}
		  }
		});

		hostedFieldsInstance.on('cardTypeChange', function (event) {
		  // Handle a field's change, such as a change in validity or credit card type
		  if (event.cards.length === 1) {
			$('#card-type').text(event.cards[0].niceType);
		  } else {
			$('#card-type').text('Card');
		  }
		});

		$('.panel-body').submit(function (event) {
		  event.preventDefault();
		  hostedFieldsInstance.tokenize(function (err, payload) {
			if (err) {
			  console.error(err);
			  return;
			}

			// Proceso el payload (resultado)
			$("#description_txt").val(payload.description);
			$("#cardType_txt").val(payload.details.cardType);
			$("#lastTwo_txt").val(payload.details.lastTwo);
			$("#nonce_txt").val(payload.nonce);
			$("#type_txt").val(payload.type);
			$("#frmPayLoad").submit();

			// This is where you would submit payload.nonce to your server
			//alert('Submit your nonce to your server here!');
		  });
		});
	  });
	});


</script>

</body>
</html>