# 📦 vinti4net-legacy

![PHP Version](https://img.shields.io/packagist/php-v/erilshk/vinti4net-legacy?color=purple)
![Packagist Version](https://img.shields.io/packagist/v/erilshk/vinti4net-legacy?color=blue\&label=version)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
![Tests](https://github.com/erilshackle/vinti4net-legacy-php/actions/workflows/phpunit.yml/badge.svg)

> ⚠️ Para projetos em **PHP 8.1+**, considere usar o SDK principal [`erilshk/vinti4net`](https://packagist.org/packages/erilshk/vinti4net).

---

SDK PHP para integração com o **Vinti4Net / SISP Cabo Verde**, desenvolvido especialmente para aplicações que ainda precisam de suporte a **PHP 5.6+**.

O `vinti4net-legacy` mantém uma API simples para integração com o serviço **MOP021**, incluindo:

* **Compras com 3D Secure**
* **Pagamentos de serviços**
* **Recargas / Top-up**
* **Estornos (Refund)**
* **DCC (Dynamic Currency Conversion)**
* **Fingerprints SHA-512**
* **Billing / dados 3DS**
* **Validação e normalização das respostas da SISP**

A versão **2.0** é uma reestruturação do SDK legacy, mantendo compatibilidade com **PHP 5.6+**, mas aproximando a API e o comportamento da implementação moderna do Vinti4Net.

---

## 📚 Instalação

### Composer

A forma recomendada de instalação é através do Composer:

```bash
composer require erilshk/vinti4net-legacy
```

Depois:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$vinti4 = new Vinti4Net(
    'SEU_POS_ID',
    'SEU_POS_AUTH_CODE'
);
```

### Standalone

Para projetos sem Composer, uma versão **standalone em arquivo único** também é disponibilizada nos Releases do GitHub.

O standalone não possui namespace nem dependências externas:

```php
<?php

require_once __DIR__ . '/Vinti4NetLegacy.php';

$vinti4 = new Vinti4Net(
    'SEU_POS_ID',
    'SEU_POS_AUTH_CODE'
);
```

Consulte os [Releases](https://github.com/erilshackle/vinti4net-legacy-php/releases) para baixar a versão correspondente ao release utilizado.

---

## 🔧 Exemplo rápido

### Compra com 3D Secure

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$vinti4 = new Vinti4Net(
    'SEU_POS_ID',
    'SEU_POS_AUTH_CODE'
);

$html = $vinti4
    ->preparePurchase(1500, [
        'email'            => 'cliente@example.com',
        'billAddrCountry'  => '132',
        'billAddrCity'     => 'Praia',
        'billAddrLine1'    => 'Achada Santo António',
        'billAddrPostCode' => '7600',
        'mobilePhone'      => '+2389912345',
    ])
    ->createPaymentForm(
        'https://seusite.cv/pagamentos/callback'
    );

echo $html;
```

`createPaymentForm()` gera um documento HTML com formulário `POST` e auto-submit para o endpoint do Vinti4Net.

---

## 🛒 Compra

Uma compra é preparada através de:

```php
$vinti4->preparePurchase(
    1500,           // valor
    $billing        // dados do purchaseRequest (Billing)
);
```

Também é possível informar outra moeda:

```php
$vinti4->preparePurchase(
    1500,
    $billing,       
    'EUR'           // moeda
);
```

O padrão é **CVE**, mas tem-se suporte também para `EUR`, `USD`, etc.

### Billing

Para transações 3D Secure, os principais campos são:

```php
$billing = [
    'email'            => 'cliente@example.com',        
    'billAddrCountry'  => '132',                        // country
    'billAddrCity'     => 'Praia',                      // city
    'billAddrLine1'    => 'Achada Santo António',       // address
    'billAddrPostCode' => '7600',                       // postalCode
    'mobilePhone'      => '+2389912345',                // phone
];
```

O SDK normaliza os dados e gera automaticamente o `purchaseRequest` enviado ao Vinti4Net.

---

## 🧾 Pagamento de serviços

```php
$html = $vinti4
    ->prepareServicePayment(
        2500,
        00123,
        '123456789'
    )
    ->createPaymentForm(
        'https://seusite.cv/pagamentos/callback'
    );

echo $html;
```

Onde:

* `2500` é o valor;
* `00123` é a entidade;
* `123456789` é a referência.

---

## 📱 Recarga

```php
$html = $vinti4
    ->prepareRecharge(
        500,
        00002,
        '9901234'
    )
    ->createPaymentForm(
        'https://seusite.cv/pagamentos/callback'
    );

echo $html;
```
Onde:

* `500` é o valor (saldo);
* `00002` é a entidade (+info);
* `9901234` é o número de telefone/serviço.

---

## ↩️ Estorno

Para efetuar um refund são necessários os dados da transação original:

```php
$html = $vinti4
    ->prepareRefund(
        1500,           // amount
        'TN987',        // transactionID
        '2401'          // clearingPeriod
    )
    ->createPaymentForm(
        'https://seusite.cv/pagamentos/callback'
    );

echo $html;
```

---

## 🏷️ Merchant Reference e Session

O SDK pode gerar automaticamente os identificadores necessários para a transação.

Também é possível defini-los explicitamente:

```php
$vinti4->setMerchant(
    'R12345678901234',
    'S12345678901234'
);
```

O Vinti4Net exige identificadores com **15 caracteres**.

Uma referência também pode ser gerada através de:

```php
$reference = Vinti4Net::generateMerchantRef();
```

assim, voce pode persitir essa referencia na sua BD de forma mais tranquila antes da chamada ao endpoint.

---

## 🌐 Idioma

O idioma utilizado pelo Vinti4Net pode ser informado em `createPaymentForm()`:

```php
$vinti4->createPaymentForm(
    'https://seusite.cv/pagamentos/callback',   // return_url
    'pt'                                        // lang
);
```

Idiomas (lang) suportados:

```text
pt
en
fr
```

O padrão é `pt`.

---

## 🔄 Processar retorno

No endpoint informado em `createPaymentForm()`, processe os dados enviados pela SISP:

```php
$response = $vinti4->processResponse($_POST);

if ($response['status'] === 'SUCCESS') {
    echo 'Pagamento concluído!';
} elseif ($response['status'] === 'CANCELLED') {
    echo 'O utilizador cancelou a operação.';
} else {
    echo 'Falha: ' . $response['message'];
}
```

A resposta é normalizada pelo SDK e contém informações como:

```php
[
    'status'    => 'SUCCESS',
    'message'   => 'Transação válida.',
    'success'   => true,
    'data'      => [],
    'dcc'       => [],
    'debug'     => [],
    'detail'    => null,
    'operation' => 'purchase',
]
```

---

## 🚦 Status

Os principais status retornados por `processResponse()` são:

| Status                | Descrição                                   |
| --------------------- | ------------------------------------------- |
| `SUCCESS`             | Transação processada e fingerprint validado |
| `ERROR`               | Erro retornado pelo gateway                 |
| `CANCELLED`           | Operação cancelada pelo utilizador          |
| `INVALID_FINGERPRINT` | Resposta recebida com fingerprint inválido  |

---

## 🔍 Operação

O campo `operation` identifica o tipo de resposta:

| `operation`       | Operação             |
| ----------------- | -------------------- |
| `purchase`        | Compra               |
| `service_payment` | Pagamento de serviço |
| `recharge`        | Recarga              |
| `refund`          | Estorno              |

Exemplo:

```php
if (
    $response['success'] &&
    $response['operation'] === 'refund'
) {
    echo 'Estorno concluído.';
}
```

---

## 💱 DCC

Quando a SISP retorna informações de **Dynamic Currency Conversion**, elas são disponibilizadas em:

```php
$response['dcc'];
```

Exemplo:

```php
[
    'enabled'  => true,
    'amount'   => '10.58',
    'currency' => 'USD',
    'markup'   => '0.31',
    'rate'     => '92.65882',
]
```

Quando DCC não é utilizado:

```php
[
    'enabled' => false,
]
```

---

## 🧩 Métodos principais

### `preparePurchase()`

Prepara uma compra, opcionalmente com dados de billing para 3D Secure.

```php
$vinti4->preparePurchase($amount, $billing, $currency);
```

### `prepareServicePayment()`

Prepara um pagamento de serviço utilizando entidade e referência.

```php
$vinti4->prepareServicePayment($amount, $entity, $reference);
```

### `prepareRecharge()`

Prepara uma operação de recarga.

```php
$vinti4->prepareRecharge($amount, $entity, $reference);
```

### `prepareRefund()`

Prepara o estorno de uma transação anterior.

```php
$vinti4->prepareRefund($amount, $transactionID, $clearingPeriod);
```

### `setMerchant()`

Define manualmente `merchantRef` e `merchantSession`.

```php
$vinti4->setMerchant($reference, $session);
```

### `createPaymentForm()`

Valida a transação preparada, gera o fingerprint e retorna o formulário HTML que será enviado ao Vinti4Net.

```php
$html = $vinti4->createPaymentForm($callbackUrl, 'pt');
```

### `processResponse()`

Valida e normaliza a resposta recebida da SISP.

```php
$response = $vinti4->processResponse($_POST);
```

### `getRequest()`

Permite consultar os dados da transação atualmente preparada.

```php
$request = $vinti4->getRequest();
```

---

## 🛠️ Requisitos

* **PHP 5.6+**
* Extensão `json`
* Extensão `hash`

_Não é necessário utilizar BCMath._

A instalação standalone não requer Composer nem outras bibliotecas externas.

---

## 🔐 Segurança

O SDK implementa:

* Fingerprints **SHA-512** conforme o fluxo utilizado pelo SISP/Vinti4Net;
* validação do fingerprint das respostas de sucesso;
* comparação segura de fingerprints;
* validação de valores, referências, URLs e parâmetros da transação;
* normalização dos dados utilizados em `purchaseRequest`;
* escaping dos valores inseridos no formulário HTML;
* mascaramento do PAN retornado pelo gateway.

Uma resposta com `messageType` de sucesso somente é considerada válida após a verificação do fingerprint.

---

## 🔄 Vinti4Net moderno

Para aplicações executando **PHP 8.1+**, utilize preferencialmente o SDK principal:

[`erilshk/vinti4net`](https://packagist.org/packages/erilshk/vinti4net)

O `vinti4net-legacy` é mantido especificamente para projetos que necessitam compatibilidade com versões antigas do PHP.

---

## 📜 Licença

Distribuído sob a licença **MIT**. Consulte o arquivo [LICENSE](LICENSE).

---

## 👨‍💻 Autor

**Erilando TS Carvalho**

Criador e mantenedor do Vinti4Net Legacy.

[![GitHub Stars](https://img.shields.io/github/stars/erilshackle/vinti4net-legacy-php?color=yellow)](https://github.com/erilshackle/vinti4net-legacy-php/stargazers)

---

[![Coverage](https://codecov.io/gh/erilshackle/vinti4net-legacy-php/branch/main/graph/badge.svg?token=4a355bba-cd40-4919-808e-40f649f7a99a)](https://codecov.io/gh/erilshackle/vinti4net-legacy-php)
[![GitHub Issues](https://img.shields.io/github/issues/erilshackle/vinti4net-legacy-php?color=red)](https://github.com/erilshackle/vinti4net-legacy-php/issues)
[![GitHub Forks](https://img.shields.io/github/forks/erilshackle/vinti4net-legacy-php?color=blue)](https://github.com/erilshackle/vinti4net-legacy-php/network/members)
