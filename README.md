#  Motor de Pagos

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

| **Atributo** | **Valor**           |
|--------------|---------------------|
| Endpoint     | api/InitTransaction |
| Protocolo    | POST                |

### Parámetros de envío

| Atributo       | Requerido | Descripción                               |
|----------------|:---------:|-------------------------------------------|
| idUserExternal | Si        | ID Ususario plataforma externa            |
| codExternal    | Si        | ID Trx plataforma externa                 |
| urlOk          | Si        | Url si el proceso es exitoso              |
| urlError       | Si        | Url si el proceso es erróneo              |
| urlNotify      | Si        | Url de notificación                       |
| commerceID     | Si        | Código de comercio (no ID)                |
| amount         | Si        | Precio a pagar                            |

### Ejemplo respuesta de éxito

```json
{
    "code": 0,
    "message": "Transacción OK 2",
    "result": "cbc0618daf792fdbac0faea1efe9ec0c0efdf4d2e0c07f4057113699c06b16569f847199761588916d49bda3987ed40f994adcf88fdca0c5c05e44f985b716a8ymWyKwbW8HN3nr4~IERzmNoolhjBXO~JDQiRP77m6Yw-",
    "paymentForm": "http://localhost/pe3g/api/ShowPaymentFormGet/cbc0618daf792fdbac0faea1efe9ec0c0efdf4d2e0c07f4057113699c06b16569f847199761588916d49bda3987ed40f994adcf88fdca0c5c05e44f985b716a8ymWyKwbW8HN3nr4~IERzmNoolhjBXO~JDQiRP77m6Yw-"
}
```

| Atributo    | Descripción                        |
|-------------|------------------------------------|
| code        | 0 = éxito; <> 0 error              |
| message     | Mensaje identificador              |
| result      | Hash de trx, se usa en paymentForm |
| paymentForm | Url formulario de pago             |



## Iniciar Transacción Recurrente

### Descripción General

Inicia el proceso de una nueva transacción recurrente en el motor de pagos. Actualmente SÓLO se está marcando la transacción con un flag que la identifica como recurrente, no realiza ninguna otra acción.

### Descripción técnica

| **Atributo** | **Valor**                     |
|--------------|-------------------------------|
| Endpoint     | api/InitTransactionRecurrence |
| Protocolo    | POST                          |

### Parámetros de envío

| Atributo       | Requerido | Descripción                               |
|----------------|:---------:|-------------------------------------------|
| idUserExternal | Si        | ID Ususario plataforma externa            |
| codExternal    | Si        | ID Trx plataforma externa                 |
| urlOk          | Si        | Url si el proceso es exitoso              |
| urlError       | Si        | Url si el proceso es erróneo              |
| urlNotify      | Si        | Url de notificación                       |
| commerceID     | Si        | Código de comercio (no ID)                |
| amount         | Si        | Precio a pagar                            |
| recurrence     | Si        | valor 1 para indicar recurrencia          |
| periodicityTag | Si        | Tag de la periodicidad                    |

### Ejemplo respuesta de éxito

```json
{
    "code": 0,
    "message": "Transacción OK 2",
    "result": "cbc0618daf792fdbac0faea1efe9ec0c0efdf4d2e0c07f4057113699c06b16569f847199761588916d49bda3987ed40f994adcf88fdca0c5c05e44f985b716a8ymWyKwbW8HN3nr4~IERzmNoolhjBXO~JDQiRP77m6Yw-",
    "paymentForm": "http://localhost/pe3g/api/ShowPaymentFormGet/cbc0618daf792fdbac0faea1efe9ec0c0efdf4d2e0c07f4057113699c06b16569f847199761588916d49bda3987ed40f994adcf88fdca0c5c05e44f985b716a8ymWyKwbW8HN3nr4~IERzmNoolhjBXO~JDQiRP77m6Yw-"
}
```

| Atributo    | Descripción                        |
|-------------|------------------------------------|
| code        | 0 = éxito; <> 0 error              |
| message     | Mensaje identificador              |
| result      | Hash de trx, se usa en paymentForm |
| paymentForm | Url formulario de pago             |

