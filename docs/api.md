# Referência da API

Esta página documenta todos os métodos públicos da classe **Vinti4NetLegacy**.

---

## 🔧 Construtor

```php
__construct(string $posID, string $posAuthCode)
```

| Parâmetro     | Tipo   | Descrição                                      |
| ------------- | ------ | ---------------------------------------------- |
| `posID`       | string | Identificador do POS fornecido pelo SISP       |
| `posAuthCode` | string | Código de autenticação POS fornecido pelo SISP |

---

## 🟦 Métodos Principais

---

### preparePurchasePayment

```php
preparePurchasePayment(int $amount, array $options = [])
```

Prepara um pagamento 3DS.

### Parâmetros:

* **amount** – valor em escudos (inteiro)
* **options.user** – dados do cliente para 3DS
* **options.orderId** – opcional
* **options.currency** – padrão CVE

---

### prepareServicePayment

```php
prepareServicePayment(int $amount, string $entity, string $reference)
```

Pagamentos de serviços.

---

### prepareRechargePayment

```php
prepareRechargePayment(int $amount, string $rechargeCode)
```

Fluxo de recargas.

---

### prepareRefundPayment

```php
prepareRefundPayment(string $transactionId, int $amount)
```

Fluxo de reembolso (refund).

---

## 🔄 Parâmetros opcionais

### setRequestParams

```php
setRequestParams(array $params)
```

Permite acrescentar parâmetros extras enviados ao SISP.

---

## 📝 Geração do Formulário

### createPaymentForm

```php
createPaymentForm(string $responseUrl)
```

Gera HTML com auto-submit para o endpoint do SISP.

---

## 📥 Processar Resposta

### processResponse

```php
processResponse(array $post)
```

Retorna um array normalizado contendo:

```php
[
    'success' => bool,
    'status'  => 'SUCCESS|ERROR|CANCELLED|INVALID_FINGERPRINT',
    'message' => string,
    'data'    => array,
    'dcc'     => array|null,
    'debug'   => array,
]
```

---

## 🧩 Códigos de status retornados

| Código              | Significado                    |
| ------------------- | ------------------------------ |
| SUCCESS             | Pagamento concluído            |
| ERROR               | Falha genérica                 |
| CANCELLED           | Utilizador cancelou            |
| INVALID_FINGERPRINT | Potencial fraude / manipulação |

---

## 📄 Notas Importantes

* amount **deve ser inteiro**
* timezone do servidor deve estar correta
* fingerprints são obrigatórios
* nunca reutilize a mesma instância para múltiplos pagamentos

---

## 📚 Continue lendo

* [Guia Rápido](quickstart.md)
* [Documentação Avançada](advanced.md)