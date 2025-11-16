# Exemplos de Integração

Este guia apresenta exemplos práticos, completos e prontos para uso para todas as operações suportadas pelo SDK:

* ✔️ Purchase (Compra 3D Secure)
* ✔️ Pagamento de Serviço
* ✔️ Recarga
* ✔️ Refund (Reembolso)
* ✔️ Processamento de Callback (retorno do SISP)

Em todos os exemplos assumimos:

```php
require_once "Vinti4NetLegacy.php";

$posID       = "POS123";
$posAuthCode = "ABCDEF123456789";
$endpoint    = "https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment";

$vinti4 = new Vinti4NetLegacy($posID, $posAuthCode, $endpoint);
```

---

# 🟦 1. Exemplo — Purchase (Compra 3D Secure)

```php
require_once "Vinti4NetLegacy.php";

$vinti4 = new Vinti4NetLegacy("POS123", "ABCDEF123456789");

// Prepara cobrança 3D Secure
$vinti4->preparePurchasePayment(
    1500, // valor em escudos
    [
        'email' => 'cliente@example.com',
        'billAddrCountry' => '132',
        'billAddrCity' => 'Praia',
        'billAddrLine1' => 'Avenida Cidade Lisboa',
        'billAddrPostCode' => '7600',
        'mobilePhone' => '+23899123456',
    ]
);

// Cria formulário HTML de pagamento
echo $vinti4->createPaymentForm(
    "https://seusite.com/callback",
    "PEDIDO-123"
);
```

➡ O formulário gerado será auto‐submetido ao SISP.

---

---

# 🟦 2. Exemplo — Pagamento de Serviço

```php
require_once "Vinti4NetLegacy.php";

$vinti4 = new Vinti4NetLegacy("POS123", "ABCDEF123456789");

$vinti4->prepareServicePayment(
    2500,     // valor
    123,      // código da entidade
    4567890   // referência
);

echo $vinti4->createPaymentForm(
    "https://seusite.com/callback",
    "SERVICO-555"
);
```

---

---

# 🟦 3. Exemplo — Recarga

```php
require_once "Vinti4NetLegacy.php";

$vinti4 = new Vinti4NetLegacy("POS123", "ABCDEF123456789");

$vinti4->prepareRechargePayment(
    500,      // valor
    220,      // entidade
    990123456 // referência de recarga
);

echo $vinti4->createPaymentForm(
    "https://seusite.com/callback",
    "RECARGA-001"
);
```

---

---

# 🟦 4. Exemplo — Refund (Estorno)

```php
require_once "Vinti4NetLegacy.php";

$vinti4 = new Vinti4NetLegacy("POS123", "ABCDEF123456789");

$vinti4->prepareRefundPayment(
    1500,              // valor original
    "PEDIDO-123",      // merchantRef original
    "SESSAO-123",      // session original
    "TID987654321",    // ID da transação original
    202401             // clearingPeriod recebido na compra
);

echo $vinti4->createPaymentForm(
    "https://seusite.com/callback-refund",
    "REFUND-01"
);
```

---

---

# 🟦 5. Exemplo — Callback / Processamento do Retorno (SISP → Seu servidor)

Este script deve estar na URL que você configurou em:

```php
$vinti4->createPaymentForm("https://seusite.com/callback");
```

Crie por exemplo: **callback.php**

```php
require_once "Vinti4NetLegacy.php";

$posID       = "POS123";
$posAuthCode = "ABCDEF123456789";

$vinti4 = new Vinti4NetLegacy($posID, $posAuthCode);

// Recebe POST do gateway
$response = $vinti4->processResponse($_POST);

// Log opcional
file_put_contents("callback.log", print_r($response, true), FILE_APPEND);

if ($response['status'] === 'SUCCESS') {
    echo "Pagamento bem-sucedido. TID: " . $response['data']['merchantRespTid'];
    exit;
}

if ($response['status'] === 'CANCELLED') {
    echo "O utilizador cancelou o pagamento.";
    exit;
}

if ($response['status'] === 'INVALID_FINGERPRINT') {
    echo "Aviso: fingerprint inválido — pode indicar adulteração dos dados!";
    exit;
}

echo "Falha no pagamento: " . $response['message'];
```

---

# 📌 Extras úteis

### 🔹 Acesso a dados DCC (se disponíveis)

```php
if (!empty($response['dcc']) && $response['dcc']['enabled']) {
    echo "Valor em moeda estrangeira: " . $response['dcc']['amount'];
    echo "Moeda: " . $response['dcc']['currency'];
    echo "Taxa: " . $response['dcc']['rate'];
}
```

### 🔹 Estrutura completa retornada em `$response`

```php
print_r($response);
/*
[
  'status' => 'SUCCESS',
  'message' => 'Transação válida.',
  'success' => true,
  'data' => [...],
  'dcc' => [...],
  'debug' => [],
  'detail' => null
]
*/
```
