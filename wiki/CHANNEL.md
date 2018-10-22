[![Digevo Logo](http://digevogroup.digevoventures.com/wp-content/uploads/sites/9/2017/07/logodigevo.png)](http://digevo.com/)

# Digevo Core - Motor de Pagos

## Obtener canales

### Descripción General

Obtener canales (pasarelas de pago) dado un código de comercio y un país

### Descripción técnica

| Atributo               | Valor                                                             |
|------------------------|-------------------------------------------------------------------|
| Endpoint Desarrollo    | dev-api.digevo.com/payment/v2/channels/{commerceCode}/{countryId} |
| Endpoint Producción    | api.digevo.com/payment/v2/channels/{commerceCode}/{countryId}     |
| Protocolo              | GET                                                               |

### Parámetros de envío

| Atributo       | Requerido | Descripción                                                                 |
|----------------|:---------:|-----------------------------------------------------------------------------|
| commerceCode   | Si        | Código de comercio (no ID). Para fines de prueba, se permite el uso de 1234 |
| countryId      | No        | ID del país                                                                 |

### Ejemplo respuesta de éxito

```json
{
    "apiVersion": "2.1",
    "context": "coupons",
    "data": {
        "totalItems": 2,
        "items": [
            {
                "idPaymentType": "5",
                "name": "Webpay Plus",
                "description": "Webpay Plus (compra normal). {VALOR} mensual.",
                "active": "1",
                "countryId": "1",
                "countryName": "Chile"
            },
            {
                "idPaymentType": "10",
                "name": "RedCompra",
                "description": "Redcompra, que es lo mismo que Webpay Plus",
                "active": "1",
                "countryId": "1",
                "countryName": "Chile"
            }
        ]
    }
}
```

### Ejemplos respuesta de error

```json
{
    "error": {
        "code": 400,
        "message": "Missing commerce"
    }
}
```

```json
{
    "error": {
        "code": 400,
        "message": "Commerce does not exist in the system"
    }
}
```

```json
{
    "error": {
        "code": 400,
        "message": "The commmerce is not active"
    }
}
```

```json
{
    "error": {
        "code": 400,
        "message": "The commerce is not enabled on the current date"
    }
}
```