# Guia Rápido

Este guia mostra como integrar rapidamente um fluxo de pagamento usando o **Vinti4Net Legacy PHP SDK**.

---

## 1. Instalação

Via Composer:

```bash
composer require erilshk/vinti4net-legacy
```

Ou incluindo manualmente:

```php
require 'Vinti4NetLegacy.php';
```

---

## 2. Criando uma instância

```php
require 'Vinti4NetLegacy.php';

$vinti4 = new Vinti4NetLegacy(
    'POS123',          // POS ID
    'ABCDEF123456'     // POS Auth Code
);
```

---

## 3. Pagamento rápido (Purchase 3DS)

```php
$html = $vinti4
    ->preparePurchasePayment(1500, [
        'user' => [
            'email' => 'cliente@example.com',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Achada S. António',
            'postCode' => '7600'
        ]
    ])
    ->createPaymentForm('https://seusite.cv/retorno');

echo $html;
```

Isso irá gerar automaticamente um **formulário HTML com auto-submit** para o SISP.

---

## 4. Processando o retorno

No seu endpoint de retorno:

```php
$response = $vinti4->processResponse($_POST);

if ($response['success']) {
    echo "Pagamento concluído com sucesso!";
} else {
    echo "Falhou: " . $response['message'];
}
```

---

## 5. Outros fluxos suportados

### Pagamento de Serviços

```php
$vinti4->prepareServicePayment(2300, '999', '123456789');
```

### Recargas

```php
$vinti4->prepareRechargePayment(500, '9012345');
```

### Refund

```php
$vinti4->prepareRefundPayment(1500, $merchantRef, $merchantSession, $transactionID, $clearingPeriod);
```
> esses parametros, normalmente você deve ter salvo na Base de dados. Elas vêm no POST de resposta do SISP
 
---

## 6. Próximos passos

* Leia a **Documentação Avançada**
* Veja todos os métodos na **API Reference**
* Consulte exemplos completos