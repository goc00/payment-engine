<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>

<!-- Include all compiled plugins (below), or include individual files as needed -->
<script src="<?php echo base_url('assets/vendor/bootstrap/js/bootstrap.min.js'); ?>"></script>

<script>
    const baseUrl = "<?php echo base_url(); ?>";
    const siteUrl = "<?php echo site_url(); ?>";

    if (navigator.userAgent.match(/(iPod|iPhone|iPad)/) && $( "#download" ).length) {
        $("#pdf").hide();
    }

    $("body").on('click', '.payment_type', function (event) {
        event.preventDefault();

        if (!$('input.chk-terms').is(':checked')) {
            swal({
                type: 'info',
                'title': 'Oops...',
                text: 'Para continuar, se deben aceptar los términos y condiciones',
                confirmButtonColor: '#ccc7c7'
            });
            $('.chk-terms').prop('checked', true);
            return false;
        } else {
            // Save Analytics
            ga('send', 'event', 'Motor de Pagos', $(this).attr('data-name'), $(this).attr('data-commerce'));

            // Go To Next View
            var ele = $(this);
            var id = ele.data("id");
            $("#option").val(id);

            // Before send form, we need to check if extra fields were completed
            var div = $("body").find("div[id='frmExtraFields_" + id + "']");
            if(div.length > 0) {
                // Get fields from form found
                var fields = div.find("input");
                var ok = true;
                fields.each(function(index) {
                    var ele = $(this);
                    var value = ele.val();
                    var id = ele.attr("id");
                    if(!value.trim()) {
                        ok = false;
                        return false;
                    }
                });
                if(!ok) {
                    // Show modal (hiding any other form)
                    $("body").find("div[id*='frmExtraFields_']").not("div[id='frmExtraFields_" + id + "']").hide();
                    div.show();
                    $("#btnExtraInputs").trigger("click");
                } else {
                    // If it's ok, will append inputs to frmPayment to proccess them with main form
                    var error = false;
                    fields.each(function(index) {
                        var ele = $(this);
                        var value = ele.val();
                        if(ele.attr('type') == "email") {
                            if(!validateEmail(value)) {
                                alert("El E-mail ingresado no es válido");
                                error = true;
                                ele.val("");
                                return false; // break each
                            }  
                        }
                        if(!value.trim()) {
                            alert("Todos los campos son requeridos");
                            error = true;
                            return false;
                        }
                    });

                    if(!error) {
                        fields.each(function(index) {
                            var ele = $(this);
                            /*if(ele.attr('type') == "email") {
                                alert("El E-mail ingresado no es válido");
                            }*/
                            $('<input />').attr({
                                'type': 'hidden',
                                'id': ele.attr("id"),
                                'name': ele.attr("id"),
                                'value': ele.val()
                            }).appendTo($("#frmPayment"));

                        });


                        $("#frmPayment").submit();
                    }
                    
                }
            } else {
                $("#frmPayment").submit();
            }

        }
    }).each(function() { // Show Terms
        if ($(this).attr('data-id') == 10) {                // RedCompra
            $('#cuentarut-panel').removeClass('hide');
        }
        if ($(this).attr('data-id') == 8) {                 // Oneclick
            $('#oneclick-panel').removeClass('hide');
        }
        if ($(this).attr('data-id') == 5) {                 // Webpay Plus
            $('#webpay-panel').removeClass('hide');
        }
    });

    function validateEmail($email) {
        var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
        return emailReg.test( $email );
    }

    function goBack(url) {
        if(window.location.href.indexOf("ShowPaymentFormGet") > -1) {
            swal({
                type: 'info',
                title: 'Oops...',
                text: '¿Realmente quieres anular la transacción?',
                type: 'warning',
                showCancelButton: true,
                focusConfirm: false,
                focusCancel: true,
                confirmButtonColor: '#ccc7c7',
                confirmButtonText: 'ACEPTAR',
                cancelButtonColor: '#FF9101',
                cancelButtonText: 'CANCELAR'
            }).then(function () {
                location.href = url;
            })
        } else {
            location.href = url;
        }
    }

    $("#cancelBtn").click(function() {
        goBack($("#error").val());
    });

    $('#pdf').click(function (event) {
        event.preventDefault();

        $('#pdf').prop('disabled', true);   // Disable for multiple clicks
        $('#pdf').html('GENERANDO...');     // Change text

        $.ajax({
            method: "POST",
            url: "<?php echo base_url('v2/transaction/generatePdf'); ?>",
            data: send
        }).done(function( msg ) {
            if (msg.status == 1) {
                $('#download').attr('href', baseUrl+msg.path);
                $('#download')[0].click();
            }

            $('#pdf').prop('disabled', false); // Enable again
            $('#pdf').html('GUARDAR');
        });
    });

    $('#print').click(function (event) {
        event.preventDefault();

        window.print();
    });
</script>