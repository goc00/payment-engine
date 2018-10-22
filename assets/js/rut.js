function formato_rut(rut) {
    var sRut1 = rut.value;
    //contador de para saber cuando insertar el . o la -
    var nPos = 0;
    //Guarda el rut invertido con los puntos y el guión agregado
    var sInvertido = "";
    //Guarda el resultado final del rut como debe ser
    var sRut = "";
    for (var i = sRut1.length - 1; i >= 0; i--) {
        sInvertido += sRut1.charAt(i);
        if (i == sRut1.length - 1)sInvertido += "-";
        else if (nPos == 3) {
            sInvertido += ".";
            nPos = 0;
        }
        nPos++;
    }
    for (var j = sInvertido.length - 1; j >= 0; j--) {
        if (sInvertido.charAt(sInvertido.length - 1) != ".")sRut += sInvertido.charAt(j);
        else if (j != sInvertido.length - 1)sRut += sInvertido.charAt(j);
    }
    //Pasamos al campo el valor formateado
    rut.value = sRut.toUpperCase();
}

function valida_rut(rut) {
    //Valor acumulado para el calculo de la formula
    var nAcumula = 0;
    //Factor por el cual se debe multiplicar el valor de la posicion
    var nFactor = 2;
    //Dígito verificador
    var nDv = 0;
    //extraemos el ultimo numero o letra que corresponde al verificador
    //La K corresponde a 10
    if (rut.charAt(rut.length - 1).toUpperCase() == 'K')nDvReal = 10;
    //el 0 corresponde a 11
    else if (rut.charAt(rut.length - 1) == 0)nDvReal = 11;
    else nDvReal = rut.charAt(rut.length - 1);
    for (nPos = rut.length - 2; nPos >= 0; nPos--) {
        nAcumula += rut.charAt(nPos).valueOf() * nFactor;
        nFactor++;
        if (nFactor > 7)nFactor = 2;
    }
    nDv = 11 - (nAcumula % 11);
	if (nDv == nDvReal) return true;
    return false;
}
