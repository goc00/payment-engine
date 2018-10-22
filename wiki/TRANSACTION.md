[![Digevo Logo](http://digevogroup.digevoventures.com/wp-content/uploads/sites/9/2017/07/logodigevo.png)](http://digevo.com/)

# Digevo Core - Motor de Pagos

## Conexión WebPay ambiente de integración

**Visa**

- NUM: 4051885600446623
- CCV: 123

**Mastercard**

- NUM: 5186059559590568
- CCV: 123

**Débito**

- NUM: 12345678

**Tarjetahabitante**

- Rut: 11.111.111-1
- Clave 123

## Iniciar Transacción

### Descripción General

Inicia el proceso de una nueva transacción en el motor de pagos.

### Descripción técnica

| Atributo                | Valor                                      |
|-------------------------|--------------------------------------------|
| Endpoint Desarrollo     | api.digevo.com/payment/v2/transactions     |
| Endpoint Producción     | dev-api.digevo.com/payment/v2/transactions |
| Protocolo               | POST                                       |

### Parámetros de envío

| Atributo       | Requerido | Descripción                                      |
|----------------|:---------:|--------------------------------------------------|
| commerceID     | Si        | ID del producto (entiéndase código de comercio)  |
| idUserExternal | Si        | ID Ususario plataforma externa                   |
| codExternal    | Si        | ID Trx plataforma externa                        |
| urlOk          | Si        | Url si el proceso es exitoso                     |
| urlError       | Si        | Url si el proceso es erróneo                     |
| urlNotify      | Si        | Url de notificación, a esta url se envía a través de POST lo siguiente: result, codExternal y message, con el fin que el comercio asociado se entere de la transacción                              |
| amount         | Si        | Precio a pagar                                   |

Los siguientes parámetros no son obligatorios, pero son necesarios para realizar una trazabilidad correcta.

| Atributo    | Requerido | Descripción                   |
|-------------|:---------:|-------------------------------|
| patternId   | No        | ID de pauta                   |
| pixelId     | No        | ID del pixel                  |
| utmSource   | No        | Parámetro de Google Analytics |
| utmMedium   | No        | Parámetro de Google Analytics |
| utmCampaign | No        | Parámetro de Google Analytics |
| utmContent  | No        | Parámetro de Google Analytics |
| utmTerm     | No        | Parámetro de Google Analytics |
| springSale  | No        | Parámetro de Google Analytics |

## Request

### JSON de envío sin trazabilidad

```json
{
	"commerceID": "Código de commercio",
	"idUserExternal": "miusuario",
	"codExternal": "micodigo",
	"urlOk": "https://payments-ok.com",
	"urlError": "https://payments-error.com",
	"urlNotify": "https://payments-notify.com",
	"amount": "500"
}
```

### JSON de envío con trazabilidad

```json
{
    "commerceID": "100003",
    "idUserExternal": "20180319-002",
    "codExternal": "20180319-002",
    "urlOk": "https://payments-ok.com",
    "urlError": "https://payments-error.com",
    "urlNotify": "https://payments-notify.com",
    "amount": "50",
    "patternId": 1234,
    "pixelId": 4321,
    "utmSource": "source",
    "utmMedium": "medium",
    "utmCampaign": "campaign",
    "utmContent": "content",
    "utmTerm": "term",
    "springSale": "spring"
}
```

## Response

### Ejemplo JSON de envío sin trazabilidad

```json
{
    "apiVersion": "2.1",
    "context": "payments",
    "data": {
        "encoded": "6d8cfdf0219f092627f9ff164dfaf1755120035eeebef414d606df3386056c502ec2102c5767a78cc977431aec0e5ee7d7804aa77587335b52e1a281f204f94dAu50tNos5ZjbYtyX9twNwd4pUADLqt5CP26gMnEuhnc-",
        "campaign": null,
        "url": "http://localhost/pe3g/apiv2/ShowPaymentFormGet/6d8cfdf0219f092627f9ff164dfaf1755120035eeebef414d606df3386056c502ec2102c5767a78cc977431aec0e5ee7d7804aa77587335b52e1a281f204f94dAu50tNos5ZjbYtyX9twNwd4pUADLqt5CP26gMnEuhnc-/"
    }
}
```

### Ejemplo JSON de envío con trazabilidad

```json
{
    "apiVersion": "2.1",
    "context": "payments",
    "data": {
        "encoded": "f1644a59732597dab3f86d6c354e144b3e683add5f978b7a1f0fcb66f32b72e7962ca27ab824915a54cbbc6c76b881b678ffe8b7191a36a9c4faeadef2064e4detBejHV.D.rDtcdtLwmc3YhqhWOLaME7UH09yQNYYG0-",
        "campaign": "?pattern_id=1234&pixel_id=4321&utm_source=source&utm_medium=medium&utm_campaign=campaign&utm_content=content&utm_term=term&spring_sale=spring",
        "url": "http://localhost/pe3g/apiv2/ShowPaymentFormGet/f1644a59732597dab3f86d6c354e144b3e683add5f978b7a1f0fcb66f32b72e7962ca27ab824915a54cbbc6c76b881b678ffe8b7191a36a9c4faeadef2064e4detBejHV.D.rDtcdtLwmc3YhqhWOLaME7UH09yQNYYG0-/"
    }
}
```

El campo URL, identifica el formulario de pago, la cual se compone de {ENDPOINT}/{trx}/{opts}. Básicamente es el formulario HTML en donde la persona visualiza todos los canales de pagos asociados al comercio.

La respuesta se estructura de la siguiguiente forma:

- Endpoint: Es la URL
- trx: Es la transacción encriptada

No obstante, también permite: 

- opts: Son opcionales, actualmente permite filtrar por canales de pago, por ejemplo, permite lo siguiente {ENDPOINT}/{trx}/1,2 lo que significa que el formulario de pago debe mostrar sólo los canales de pago con id 1 y 2. En caso de ingresar un ID que no esté asociado al comercio, sólo se permitirá cancelar el pago y no permite seguir adelante.

### Ejemplo respuesta de error

```json
{
    "apiVersion": "2.1",
    "context": "payments",
    "error": {
        "code": 400,
        "message": "Missing Amount"
    }
}
```

<br><br><br><br><br>

# Obtener transacciones de usuario

## Descripción General

Obtener transacciones de un comercio o de la unión de un comercio + usuario

## Descripción técnica

| **Atributo**            | **Valor**                                                                                   |
|-------------------------|---------------------------------------------------------------------------------------------|
| Endpoint Desarrollo     | dev-api.digevo.com/payment/v2/transactions/{commercecode}/{iduserexternal}/{offset}/{total} |
| Endpoint Producción     | api.digevo.com/payment/v2/transactions/{commercecode}/{iduserexternal}/{offset}/{total}     |
| Protocolo               | GET                                                                                         |

## Parámetros de envío

| Atributo       | Requerido | Descripción                                                                               |
|----------------|:---------:|-------------------------------------------------------------------------------------------|
| idProduct      | Si        | ID del producto (entiéndase código de comercio)                                           |
| idUserExternal | No        | ID Ususario plataforma externa, si no se desea buscar el usuario, se debe enviar un guión |
| offset         | No        | Cuantos registros NO se consideran                                                        |
| total          | No        | Total registros a mostrar                                                                 |

Ejemplos de envío

- Con usuario: /transactions/1234/1/0/10
- Sin usuario: /transactions/-/1/0/10

## Response

```json
{
    "apiVersion": "1.0",
    "context": "payments",
    "data": {
        "totalItems": 10,
        "itemsPerPage": 1,
        "items": [
            {
                "idTrx": "28168",
                "amount": "500",
                "idUserExternal": "idUserExternalNormeno",
                "codExternal": "codExternalNormeno",
                "creationDate": "2017-10-20T14:56:59.000000+02:00",
                "modificationDate": null,
                "idCommerce": "1",
                "nameCommerce": "Comercio Prueba",
                "codeCommerce": "1234",
                "activeCommerce": "1",
                "idStage": "1",
                "nameStage": "INICIO TRANSACCIÓN",
                "descStage": "Inicio Trx para PatPass",
                "idPaymentType": null,
                "namePaymentType": null,
                "descPaymentType": null,
                "activePaymentType": null,
                "codePaymentType": null
            },
            {
                "idTrx": "28167",
                "amount": "500",
                "idUserExternal": "idUserExternalNormeno",
                "codExternal": "codExternalNormeno",
                "creationDate": "2017-10-20T14:52:28.000000+02:00",
                "modificationDate": null,
                "idCommerce": "1",
                "nameCommerce": "Comercio Prueba",
                "codeCommerce": "1234",
                "activeCommerce": "1",
                "idStage": "1",
                "nameStage": "INICIO TRANSACCIÓN",
                "descStage": "Inicio Trx para PatPass",
                "idPaymentType": null,
                "namePaymentType": null,
                "descPaymentType": null,
                "activePaymentType": null,
                "codePaymentType": null
            }
        ]
    }
}
```

