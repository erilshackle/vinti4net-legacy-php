# 📘 Documentação Avançada

Esta secção cobre:

1. 🔐 Arquitetura interna
2. 🔄 Ciclo completo de pagamento (Purchase / Service / Recharge / Refund)
3. 🛡️ Fingerprints (Request & Response)
4. 🧭 Flow de 3D Secure + Campos 3DS
5. 🌍 DCC — Dynamic Currency Conversion
6. 🧩 Normalização avançada de Billing
7. ⚠️ Validações do SISP
8. 🧪 Boas práticas em produção
9. 🏗️ Modelos de resposta
10. 🛠️ Troubleshooting

---

## 1. 🔐 Arquitetura Interna

A classe `Vinti4NetLegacy` foi construída com três pilares:

### **1) Pré-processamento do pedido**

* Conversão de moeda
* Geração de merchantRef & merchantSession
* Normalização de billing
* Validação de campos obrigatórios

### **2) Assinatura de segurança**

* Fingerprint SHA512 obrigatório pelo SISP
* Codificação Base64
* Fluxo diferente para *payment* e *refund*

### **3) Pós-processamento da resposta**

* Validação de fingerprint
* Interpretação do messageType
* Extração opcional de dados DCC
* Normalização da resposta para o cliente

---

## 2. 🔄 Ciclo Completo de Pagamento

## Fluxo típico (Purchase 3DS)

```
Comerciante → SDK → SISP  → Middleware → SDK → Comerciante
```

1. Cliente inicia o pagamento
2. SDK gera auto-submit
3. Cliente autentica no Middleware (3DS)
4. SISP notifica seu endpoint com POST
5. SDK valida a resposta
6. Resultado final retorna ao sistema

---

## 3. 🛡️ Fingerprints em Detalhe

O SISP usa fingerprints SHA512 combinando:

* POSAuthCode (hash interno)
* Campos críticos da requisição/retorno
* Conversão especial do valor *(amount × 1000)*
* Codificação final Base64

### 3.1 Fingerprint do Request

O método interno:

```php
fingerprintRequest(array $data, $type = 'payment')
```

### Para pagamentos:

```
FP = BASE64(
    SHA512(
        base64(SHA512(POS_AUTH))
        + timeStamp
        + amountLong
        + merchantRef
        + merchantSession
        + posID
        + currency
        + transactionCode
        + entityCode
        + referenceNumber
    )
)
```

### Observações:

* **amountLong** = `amount * 1000` (regra oficial do SISP)
* Campos de entidade só são incluídos se não forem vazios
* Ordem dos campos é fixa e obrigatória

---

### 3.2 Fingerprint da Resposta

O método:

```php
fingerprintResponse(array $post)
```

Para PURCHASE:

```
FP = BASE64(
    SHA512(
        base64(SHA512(POS_AUTH))
        + messageType
        + merchantRespCP
        + merchantRespTid
        + merchantRespMerchantRef
        + merchantRespMerchantSession
        + merchantRespPurchaseAmountLong
        + merchantRespMessageID
        + merchantRespPan
        + merchantResp
        + merchantRespTimeStamp
        + merchantRespReferenceNumber
        + merchantRespEntityCode
        + merchantRespClientReceipt
        + merchantRespAdditionalErrorMessage
        + merchantRespReloadCode
    )
)
```

---

## 4. 🧭 3D Secure – Campos Suportados

O SDK tem integração automática com parâmetros típicos de 3DS:

### Campos derivados:

| Campo                 | Origem                      | Significado                    |
| --------------------- | --------------------------- | ------------------------------ |
| chAccAgeInd           | created_at                  | Idade da conta                 |
| chAccPwChangeInd      | updated_at                  | Mudança de password            |
| suspiciousAccActivity | user.suspicious             | Indica comportamento suspeito  |
| mobilePhone           | user.phone/user.mobilePhone | Normalizado em CC + subscriber |
| workPhone             | idem                        |                                |

Exemplo de input simplificado:

```php
[
    'user' => [
        'email' => 'client@mail.com',
        'created_at' => '2020-05-01',
        'updated_at' => '2024-01-01',
        'suspicious' => false,
        'phone' => '+238 9912345'
    ]
]
```

O SDK transforma isso em um payload 3DS para PurchaseRequest.

---

## 5. 🌍 DCC — Dynamic Currency Conversion

Se o banco do cliente oferecer DCC, o SISP envia:

```json
{
  "dcc": "Y",
  "dccAmount": "23.50",
  "dccCurrency": "EUR",
  "dccMarkup": "3.1",
  "dccRate": "0.00923"
}
```

O SDK converte automaticamente para:

```php
[
    'enabled' => true,
    'amount' => 23.50,
    'currency' => 'EUR',
    'markup' => 3.1,
    'rate' => 0.00923
]
```

---

## 6. 🧩 Normalização Avançada de Billing

Exemplo de conversão automática de telefone:

Entrada:

```
+238 9912345
```

Saída:

```php
[
    'cc' => '238',
    'subscriber' => '9912345'
]
```

Regras:

* Remove caracteres não numéricos
* Tenta detectar código do país automaticamente
* Usa fallback DEFAULT: 238 (Cabo Verde)

---

## 7. ⚠️ Validações do SISP

Para Purchase:

| Campo            | Obrigatório | Observações   |
| ---------------- | ----------- | ------------- |
| billAddrCountry  | ✔           | ISO numérico  |
| billAddrCity     | ✔           | —             |
| billAddrLine1    | ✔           | Endereço      |
| billAddrPostCode | ✔           | Código postal |
| email            | ✔           | —             |

Se faltar, SDK dispara:

```
InvalidArgumentException("Campo obrigatório ausente em billing: ...")
```

---

## 8. 🧪 Boas Práticas de Produção

### Evite valores decimais no SISP

O SISP não lida com floats; o SDK converte, mas recomenda-se evitar:

```
1500.00 → OK  
1500.5  → Pode causar rejeição
```

### Sempre valide fingerprint

Nunca confie apenas no *messageType*.

### Use HTTPS no responseUrl

É obrigatório para certificações PCI/3DS.

### Registre logs em caso de INVALID_FINGERPRINT

O SDK já entrega:

```php
'debug' => [
    'recebido' => '',
    'calculado' => ''
]
```

---

## 9. 🏗️ Modelo de Resposta do SDK

```php
[
    'success' => true|false,
    'status' => 'SUCCESS|ERROR|CANCELLED|INVALID_FINGERPRINT',
    'message' => '...',
    'data' => [...],   // Dados brutos $_POST do SISP
    'dcc' => [
        'enabled' => bool,
        'amount' => float|null,
        'currency' => string|null,
        'markup' => float|null,
        'rate' => float|null
    ],
    'debug' => [...],
    'detail' => string|null
]
```

---

## 10. 🛠️ Troubleshooting

### ❌ Fingerprint inválido

Causas comuns:

* posAuthCode errado
* merchantRef/merchantSession modificados
* amount convertido incorretamente
* timezone do servidor
* encoding UTF-8 quebrado

---

### ❌ messageType correto, mas status FAILED

Motivo:

* Não confundir *messageType* (tipo de mensagem) com *resultado*

---

### ❌ DCC não aparece

Motivos:

* Banco emissor não oferece DCC
* Cliente não aceitou conversão
* Transação não é PURCHASE

---

### ❌ "ONLY 1 PAYMENT REQUEST MUST BE PREPARED"

O SDK não permite reusar a mesma instância para múltiplas transações.
Faça:

```php
$vinti4 = new Vinti4NetLegacy(...); // nova instância
```
