[![Digevo Logo](http://digevogroup.digevoventures.com/wp-content/uploads/sites/9/2017/07/logodigevo.png)](http://digevo.com/)

# Digevo Core - Motor de Pagos

## Obtener paises

### Descripción General

Obtener listado de países

### Descripción técnica

| Atributo               | Valor                                              |
|------------------------|----------------------------------------------------|
| Endpoint Desarrollo    | dev-api.digevo.com/payment/v2/country/{commerceid} |
| Endpoint Producción    | api.digevo.com/payment/v2/country/{commerceid}     |
| Protocolo              | GET                                                |


### Parámetros de envío

| Atributo     | Requerido | Descripción                                                                    |
|--------------|:---------:|--------------------------------------------------------------------------------|
| commerceId   | Si        | Código de comercio (no ID), para fines de prueba, se puede usar el código 1234 |

### Ejemplo respuesta de éxito

```json
{
    "apiVersion": "2.1",
    "context": "payments",
    "data": {
        "totalItems": 9,
        "items": [
            {
                "idCountry": "1",
                "name": "Chile",
                "iso31662": "CL",
                "iso31663": "CHL"
            },
            {
                "idCountry": "2",
                "name": "Perú",
                "iso31662": "PE",
                "iso31663": "PER"
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
        "message": "Missing Commerce"
    }
}
```

```json
{
    "error": {
        "code": 400,
        "message": "Invalid Commerce"
    }
}
```

```json
{
    "error": {
        "code": 204,
        "message": "No Records Found"
    }
}
```

