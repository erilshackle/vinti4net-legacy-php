# Exemplos de Integração Laravel — Vinti4Net Legacy PHP SDK

> Assumimos Laravel 8+ ou 9+.

---

> ⚠️ **Atenção:** Este SDK **Legacy** funciona em Laravel e PHP 5.6+, mas não é recomendado para projetos modernos. Para Laravel e PHP 8.1+, é melhor usar o **[erilshk/vinti4net](https://github.com/erilshackle/vinti4net-php)**, que é mais moderno, mantém compatibilidade total e oferece recursos atualizados para integração com o SISP/Vinti4Net.

## 1. Instalação

Via Composer, instale o SDK:

```bash
composer require erilshk/vinti4net-legacy
```

> Opcional: você pode criar um provider ou facade, mas neste exemplo usamos diretamente a classe.

---

## 2. Configuração

Crie um arquivo de configuração `config/vinti4net.php`:

```php
<?php

return [
    'pos_id' => env('VINTI4NET_POS_ID', 'POS123'),
    'pos_auth_code' => env('VINTI4NET_POS_AUTH', 'ABCDEF123456789'),
    'endpoint' => env('VINTI4NET_ENDPOINT', 'https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment'),
];
```

No `.env`:

```env
VINTI4NET_POS_ID=POS123
VINTI4NET_POS_AUTH=ABCDEF123456789
VINTI4NET_ENDPOINT=https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment
```

---

## 3. Controller Base

Crie `app/Http/Controllers/Vinti4NetController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Vinti4NetLegacy;

class Vinti4NetController extends Controller
{
    protected $vinti4;

    public function __construct()
    {
        $this->vinti4 = new Vinti4NetLegacy(
            config('vinti4net.pos_id'),
            config('vinti4net.pos_auth_code'),
            config('vinti4net.endpoint')
        );
    }
}
```

---

## 4. Purchase (3D Secure)

```php
public function purchase()
{
    $html = $this->vinti4
        ->preparePurchasePayment(1500, [
            'email' => 'cliente@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Cidade Nova',
            'billAddrPostCode' => '7600',
            'mobilePhone' => '+23899123456',
        ])
        ->createPaymentForm(route('vinti4net.callback'), 'PEDIDO-123');

    return response($html);
}
```

> `route('vinti4net.callback')` deve apontar para o callback abaixo.

---

## 5. Service Payment

```php
public function servicePayment()
{
    $html = $this->vinti4
        ->prepareServicePayment(2500, 123, 456789)
        ->createPaymentForm(route('vinti4net.callback'), 'SERVICO-555');

    return response($html);
}
```

---

## 6. Recharge

```php
public function recharge()
{
    $html = $this->vinti4
        ->prepareRechargePayment(500, 220, 990123456)
        ->createPaymentForm(route('vinti4net.callback'), 'RECARGA-001');

    return response($html);
}
```

---

## 7. Refund (Estorno)

```php
public function refund()
{
    $html = $this->vinti4
        ->prepareRefundPayment(
            1500,
            'PEDIDO-123',
            'SESSAO-123',
            'TID987654321',
            202401
        )
        ->createPaymentForm(route('vinti4net.callback'), 'REFUND-01');

    return response($html);
}
```

---

## 8. Callback / Processamento de Retorno

```php
public function callback(Request $request)
{
    $response = $this->vinti4->processResponse($request->post());

    // Log para debug
    \Log::info('Vinti4Net Callback', $response);

    switch ($response['status']) {
        case 'SUCCESS':
            return "Pagamento concluído! TID: " . $response['data']['merchantRespTid'];
        case 'CANCELLED':
            return "O utilizador cancelou a transação.";
        case 'INVALID_FINGERPRINT':
            return "Atenção: fingerprint inválido!";
        default:
            return "Falha no pagamento: " . $response['message'];
    }
}
```

> Não se esqueça de adicionar a rota:

```php
use App\Http\Controllers\Vinti4NetController;

Route::post('/vinti4net/callback', [Vinti4NetController::class, 'callback'])->name('vinti4net.callback');
```

---

## 9. Dicas extras

* Para **ambiente de teste**, use o endpoint fornecido pelo SISP.
* Sempre **logar o callback** antes de processar.
* Recomenda-se **armazenar** `merchantRef` e `merchantSession` no banco para rastreio.
* Se usar **DCC**, você pode acessar `$response['dcc']`.