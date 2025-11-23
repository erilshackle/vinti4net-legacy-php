# 📦 **vinti4net-legacy**

![PHP Version](https://img.shields.io/packagist/php-v/erilshk/vinti4net-legacy?color=purple) ![Packagist Version](https://img.shields.io/packagist/v/erilshk/vinti4net-legacy?color=blue&label=version) [![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE) ![Tests](https://github.com/erilshackle/vinti4net-legacy-php/actions/workflows/phpunit.yml/badge.svg)

> ##### ⚠️ Considere usar **[erilshk\vinti4net](github.com/erilshackle/vinti4net-php)** para **php +8.1**

Biblioteca PHP **estável** e compativel com `erilshk\vinti4net` para integração com o **Vinti4Net (SISP – Cabo Verde)**, com suporte total a:

* **Compras (3D Secure)**
* **Pagamentos de serviços**
* **Recargas**
* **Estornos (Refund)**
* **DCC (Dynamic Currency Conversion)**
* **Fingerprints SHA512**
  
_Apesar de ter sido projetada para funcionar em ambientes modernos, a biblioteca mantém **compatibilidade com PHP 5.6+**, tornando-a ideal para sistemas legados que precisam de uma solução atualizada, segura e bem estruturada._

---

## 📚 Instalação

Via Composer:

```bash
composer require erilshk/vinti4net-legacy
```

Ou manualmente, incluindo a classe diretamente no seu projeto legado.

[baixar aqui](https://github.com/erilshackle/vinti4net-legacy-php/releases/download/v1.0.0/Vinti4NetLegacy.php)

---

## 🔧 Exemplo rápido de uso

### Criar pagamento (3D Secure)

```php
require 'Vinti4NetLegacy.php';

$vinti4 = new Vinti4NetLegacy('POS123', 'ABCDEF123456');

$html = $vinti4
    ->preparePurchasePayment(1500, [
        'user' => [
            'email'   => 'cliente@example.com',
            'country' => '132',
            'city'    => 'Praia',
            'address' => 'Safende',
            'postCode'=> '7600'
        ]
    ])
    ->createPaymentForm('https://seusite.cv/retorno');

echo $html;
```

> Isso irá gerar um formulário HTML com auto-submit apontando para o Vinti4Net.

---

## 🔄 Processar retorno do pagamento

```php
$response = $vinti4->processResponse($_POST);

if ($response['status'] === 'SUCCESS') {
    echo "Pagamento concluído!";
} elseif ($response['status'] === 'CANCELLED') {
    echo "O utilizador cancelou a operação.";
} else {
    echo "Falha: " . $response['message'];
}
```

A resposta já vem normalizada e inclui:

* `success`
* `message`
* `dcc` (se aplicável)
* `debug` (em caso de fingerprint inválido)

---

## 🧩 Métodos principais

### 🔹 **preparePurchasePayment()**

Prepara um pagamento de compra com 3D Secure.

### 🔹 **prepareServicePayment()**

Pagamentos de serviços com entidade + referência.

### 🔹 **prepareRechargePayment()**

Recargas de contas ou cartões.

### 🔹 **prepareRefundPayment()**

Reembolso de transações anteriores.

### 🔹 **setRequestParams()**

Define parâmetros adicionais opcionais.

### 🔹 **createPaymentForm()**

Gera o formulário HTML que inicia a transação.

### 🔹 **processResponse()**

Valida e interpreta as respostas do SISP.

---

## 🛠️ Requisitos

* **PHP 5.6+**
* Extensões:

  * `json`
  * `bcmath`

---

## 🔐 Segurança

* Fingerprint SHA512 implementado conforme especificações do SISP no código _MOP021_
* Sanitização e normalização de todos os campos enviados
* Prevenção de valores inesperados durante preparação do pedi
---

## 📜 Licença

MIT — livre para uso pessoal e comercial.

---

## 👨‍💻 Autor

**Eril TS Carvalho**
Criador e mantenedor do SDK Legado.

[![GitHub Stars](https://img.shields.io/github/stars/erilshackle/vinti4net-legacy-php?color=yellow)](https://github.com/erilshackle/vinti4net-legacy-php/stargazers) 

---

[![Coverage](https://codecov.io/gh/erilshackle/vinti4net-legacy-php/branch/main/graph/badge.svg?token=4a355bba-cd40-4919-808e-40f649f7a99a)](https://codecov.io/gh//erilshackle/vinti4net-legacy-php) [![GitHub Issues](https://img.shields.io/github/issues/erilshackle/vinti4net-legacy-php?color=red)](https://github.com/erilshackle/vinti4net-legacy-php/issues) [![GitHub Forks](https://img.shields.io/github/forks/erilshackle/vinti4net-legacy-php?color=blue)](https://github.com/erilshackle/vinti4net-legacy-php/network/members)
