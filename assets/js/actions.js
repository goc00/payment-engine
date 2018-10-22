$(document).ready(function() {
	
	// Tipos de pago (canales)
	$(document).on("click", ".payment_type", function(e) {
		e.preventDefault();
		var ele = $(this);
		
		// Pasa valor de selección a campo
		$("#option").val(ele.data("id"));

		// Envío
		$("#frmPayment").submit();
	});
	
	
	$("#cancelBtn").click(function() {
		goBack($("#error").val());
	});
	
});
function goBack(url) {
	location.href = url;
}