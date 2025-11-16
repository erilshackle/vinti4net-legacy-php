<?php

namespace Erilshk\Vinti4NetLegacy;

use Exception;
use InvalidArgumentException;

/**
 ** Class Vinti4Net Legacy
 *
 * SDK de integração com o Vinti4Net (SISP, Cabo Verde) para pagamentos online .
 *
 * Suporta operações:
 * - Compra (Purchase) com 3D Secure
 * - Pagamento de Serviços
 * - Recarga de contas e cartões
 * - Reembolso (Refund)
 *
 * Funcionalidades principais:
 * - Geração de formulário HTML auto-submit para iniciar pagamentos
 * - Processamento e validação de respostas do gateway
 * - Cálculo de fingerprints para integridade de dados
 * - Suporte a DCC (Dynamic Currency Conversion)
 * - Normalização de dados de faturamento (billing)
 *
 * ? Compatível com PHP 5.6+.
 *
 * @package Erilshk\Vinti4Net
 * @author  Erilando TS Carvalho
 * @license MIT
 * @version 1.0
 */

class Vinti4NetLegacy
{
    /** @var string */
    const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment";

    /** @var string */
    const TRANSACTION_TYPE_PURCHASE = '1';
    /** @var string */
    const TRANSACTION_TYPE_SERVICE  = '2';
    /** @var string */
    const TRANSACTION_TYPE_RECHARGE = '3';
    /** @var string */
    const TRANSACTION_TYPE_REFUND   = '4';

    /** @var string */
    const CURRENCY_CVE = '132';

    /** @var array Mensagens consideradas sucesso */
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    /** @var string */
    private $posID;

    /** @var string */
    private $posAuthCode;

    /** @var string */
    private $baseUrl;

    /** @var array Dados do pedido */
    private $request = [];

    /** @var bool */
    private $prepared = false;

    /**
     * Construtor da classe Vinti4Net.
     *
     * Cria uma nova instância do cliente para integração com o sistema de pagamentos Vinti4Net (SISP Cabo Verde).
     * Inicializa os dados de POS (Point of Sale) e define a URL base do endpoint do gateway.
     *
     * @param string      $posID        Identificador do POS fornecido pelo SISP.
     * @param string      $posAuthCode  Código de autenticação do POS fornecido pelo SISP.
     * @param string|null $endpoint     URL base do endpoint do SISP. Caso não seja fornecida, será usado o padrão de produção.
     *
     * @example
     * $vinti4 = new Vinti4Net7('POS123', 'ABCDEF', 'https://vinti4.sisp.cv');
     *
     * @return void
     */
    public function __construct($posID, $posAuthCode, $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->baseUrl = $endpoint ? $endpoint : self::DEFAULT_BASE_URL;
    }


    /**
     * Define parâmetros opcionais adicionais para a requisição de pagamento.
     *
     * Este método permite configurar manualmente valores que não são definidos
     * automaticamente durante a preparação do pagamento. Apenas chaves conhecidas
     * e permitidas podem ser informadas — qualquer chave inválida resultará em
     * uma InvalidArgumentException.
     *
     * A lista de campos permitidos reflete parâmetros aceitos pelo SISP/Vinti4Net:
     *
     * - merchantRef          Identificador único do pedido gerado pelo comerciante.
     * - merchantSession      ID de sessão do comerciante.
     * - languageMessages     Idioma das mensagens exibidas ao utilizador (ex: 'pt').
     * - entityCode           Entidade de pagamento (para serviços ou recargas).
     * - referenceNumber      Referência de pagamento (para serviços ou recargas).
     * - timeStamp            Data/hora da requisição (Y-m-d H:i:s).
     * - billing              Dados completos de faturação/3DS.
     * - currency             Código da moeda (ISO3 ou numérico). É convertido automaticamente.
     * - acctID               Identificador da conta do cliente.
     * - acctInfo             Dados 3DS relacionados ao histórico da conta.
     * - addrMatch            Indica se endereço de entrega corresponde ao de faturação.
     * - billAddrCountry      País do endereço de faturação.
     * - billAddrCity         Cidade do endereço de faturação.
     * - billAddrLine1        Linha 1 do endereço (obrigatória para compras).
     * - billAddrPostCode     Código postal.
     * - email                E-mail do cliente.
     * - clearingPeriod       Período de compensação (utilizado em REFUND).
     *
     * Caso o parâmetro "currency" seja enviado como string ISO3 (ex: "CVE", "EUR", "USD"),
     * ele será automaticamente convertido para o respetivo código numérico.
     *
     * @param array $params  Lista de parâmetros opcionais a definir.
     *
     * @return $this  Retorna a instância para permitir encadeamento ("method chaining").
     *
     * @throws InvalidArgumentException Caso algum parâmetro informado não seja permitido.
     */
    public function setRequestParams(array $params)

    {
        $allowed = [
            'merchantRef',
            'merchantSession',
            'languageMessages',
            'entityCode',
            'referenceNumber',
            'timeStamp',
            'billing',
            'currency',
            'acctID',
            'acctInfo',
            'addrMatch',
            'billAddrCountry',
            'billAddrCity',
            'billAddrLine1',
            'billAddrPostCode',
            'email',
            'clearingPeriod',
        ];

        foreach ($params as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("Parâmetro não permitido: {$key}");
            }
            if ($key == 'currency') {
                $value = $this->currencyToCode($value);
            }
            $this->request[$key] = $value;
        }

        return $this;
    }

    /**
     * Prepara uma requisição de pagamento do tipo **compra (3DS)**.
     *
     * Este método configura todos os dados necessários para iniciar uma transação de compra com autenticação 3D Secure.
     * Ele monta o payload interno que será posteriormente convertido em formulário de pagamento.
     *
     * O array `$billing` pode conter campos diretos ou um sub-array `user`, que será automaticamente normalizado:
     * - Campos obrigartórios: `email`, `billAddrCountry`, `billAddrCity`, `billAddrLine1`, `billAddrPostCode`.
     * - Campos opcionais: `billAddrLine2`, `billAddrLine3`, `billAddrState`, `mobilePhone`, `workPhone`, `acctID`.
     * - Sub-array `user` pode incluir: `id`, `created_at`, `updated_at`, `suspicious`, `phone`, `mobilePhoneCC`, `workPhoneCC`, etc.
     *
     * @param float|int $amount  Valor da transação (em escudos ou outra moeda configurada).
     * @param array     $billing Dados de faturamento do cliente.
     * @param string|int $currency Código da moeda (padrão: 'CVE' ou código numérico equivalente).
     *
     * @return $this Retorna a própria instância para permitir encadeamento de chamadas (fluent interface).
     *
     * @throws InvalidArgumentException Se algum dado obrigatório estiver ausente ou inválido durante a preparação interna.
     *
     * @example
     * $vinti4->preparePurchasePayment(1500, [
     *     'user' => [
     *         'email' => 'cliente@example.com',
     *         'country' => '132',
     *         'city' => 'Praia',
     *         'mobilePhone' => '+23899123456'
     *     ]
     * ]);
     */
    public function preparePurchasePayment($amount, array $billing, $currency = 'CVE')
    {
        $this->preparePaymentRequest([
            'amount' => $amount,
            'transactionCode' => self::TRANSACTION_TYPE_PURCHASE,
            'currency' => $currency,
            'billing' => $billing,
        ]);
        return $this;
    }


    /**
     * Prepara uma requisição de pagamento do tipo **serviço**.
     *
     * Usado para pagamentos de serviços com código de entidade e número de referência.
     *
     * @param float|int $amount Valor da transação.
     * @param string|int $entity Código da entidade do serviço.
     * @param string|int $number Número de referência da transação de serviço.
     *
     * @return $this Retorna a própria instância para encadeamento (fluent interface).
     *
     * @throws InvalidArgumentException Se algum dado necessário estiver ausente ou inválido.
     */
    public function prepareServicePayment($amount, $entity, $number)
    {
        $this->preparePaymentRequest([
            'amount' => $amount,
            'transactionCode' => self::TRANSACTION_TYPE_SERVICE,
            'entityCode' => $entity,
            'referenceNumber' => $number
        ]);
        return $this;
    }

    /**
     * Prepara uma requisição de pagamento do tipo **recarga**.
     *
     * Usado para recargas de contas, cartões ou telefones com entidade e número de referência.
     *
     * @param float|int $amount Valor da recarga.
     * @param string|int $entity Código da entidade da recarga.
     * @param string|int $number Número de referência da recarga.
     *
     * @return $this Retorna a própria instância para encadeamento (fluent interface).
     *
     * @throws InvalidArgumentException Se algum dado necessário estiver ausente ou inválido.
     */
    public function prepareRechargePayment($amount, $entity, $number)
    {
        $this->preparePaymentRequest([
            'amount' => $amount,
            'transactionCode' => self::TRANSACTION_TYPE_RECHARGE,
            'entityCode' => $entity,
            'referenceNumber' => $number
        ]);
        return $this;
    }

    /**
     * Prepara uma requisição de **reembolso (refund)**.
     *
     * Este método é usado para estornar uma transação previamente aprovada.
     * É necessário informar o ID do comerciante, sessão, ID da transação original e período de compensação.
     *
     * @param float|int $amount Valor do reembolso (deve ser inteiro em unidade da moeda, sem decimais para SISP).
     * @param string $merchantRef Identificador único do pedido original no sistema do comerciante.
     * @param string $merchantSession Identificador da sessão do pedido original.
     * @param string $transactionID ID da transação que será estornada.
     * @param string|int $clearingPeriod Período de compensação relacionado ao estorno.
     *
     * @return $this Retorna a própria instância para encadeamento (fluent interface).
     *
     * @throws InvalidArgumentException Se algum dado obrigatório estiver ausente ou inválido.
     */
    public function prepareRefundPayment($amount, $merchantRef, $merchantSession, $transactionID, $clearingPeriod)
    {
        $this->preparePaymentRequest([
            'transactionCode'   => self::TRANSACTION_TYPE_REFUND,
            'amount'            => $amount,
            'clearingPeriod'    => $clearingPeriod,
            'transactionID'     => $transactionID,
            'merchantRef'       => $merchantRef,
            'merchantSession'   => $merchantSession,
        ]);

        return $this;
    }


    /**
     * Gera um **formulário HTML de pagamento** com auto-submit para iniciar a transação no Vinti4Net.
     *
     * Este método constrói um formulário HTML com campos ocultos contendo todos os dados necessários
     * para a transação, incluindo fingerprint de segurança. Ao carregar a página, o formulário é
     * automaticamente submetido para a URL do gateway.
     *
     * @param string $responseUrl URL para a qual o gateway deve enviar a resposta da transação.
     * @param string|null $merchantRef Opcional. Referência única do comerciante para identificar a transação.
     *
     * @return string HTML completo do formulário com auto-submit.
     *
     * @throws InvalidArgumentException Se algum parâmetro necessário estiver ausente ou inválido.
     */
    public function createPaymentForm($responseUrl, $merchantRef = null)
    {
        $this->request['urlMerchantResponse'] = $responseUrl;
        if ($merchantRef !== null) {
            $this->request['merchantRef'] = $merchantRef;
        }

        $paymentData = $this->processRequest($this->request);

        $fields = '';
        foreach ($paymentData['fields'] as $k => $v) {
            $fields .= "<input type='hidden' name='{$k}' value='" . htmlspecialchars((string)$v) . "'>\n";
        }

        return "
        <html>
        <head><title>Pagamento Vinti4Net</title></head>
        <body onload='document.forms[0].submit()'>
            <form method='post' action='{$paymentData['postUrl']}'>
                {$fields}
            </form>
            <p>processando...</p>
        </body>
        </html>";
    }

    /**
 * Processa o retorno enviado pelo Vinti4Net após o pagamento.
 *
 * Este método valida a resposta recebida do gateway, verifica se o usuário cancelou a transação,
 * calcula o fingerprint para garantir integridade dos dados, e extrai informações adicionais como DCC (Dynamic Currency Conversion)
 * quando aplicável.
 *
 * A resposta retornada possui a seguinte estrutura:
 * - `status` (string): Status da transação (`SUCCESS`, `ERROR`, `CANCELLED`, `INVALID_FINGERPRINT`).
 * - `message` (string): Mensagem descritiva do status.
 * - `success` (bool): Indica se a transação foi processada com sucesso.
 * - `data` (array): Dados brutos recebidos do gateway.
 * - `dcc` (array): Informações de conversão de moeda dinâmica (se aplicável), incluindo:
 *     - `enabled` (bool)
 *     - `amount` (float|null)
 *     - `currency` (string|null)
 *     - `markup` (float|null)
 *     - `rate` (float|null)
 * - `debug` (array): Informações de depuração, especialmente se o fingerprint não for válido.
 * - `detail` (string|null): Detalhes adicionais de erro, se houver.
 *
 * @param array $postData Dados recebidos via POST do gateway Vinti4Net.
 *
 * @return array{
 *     status:string,
 *     message:string,
 *     success:bool,
 *     data:array,
 *     dcc:array,
 *     debug:array,
 *     detail:string|null
 * }
 *
 * @example
 * $response = $vinti4->processResponse($_POST);
 * if ($response['status'] === 'SUCCESS') {
 *     echo "Pagamento concluído com sucesso!";
 * } elseif ($response['status'] === 'CANCELLED') {
 *     echo "Usuário cancelou a transação.";
 * } else {
 *     echo "Falha no pagamento: " . $response['message'];
 * }
 */
    public function processResponse(array $postData)
    {
        $result = [
            'status' => 'ERROR',
            'message' => 'Erro desconhecido na transação.',
            'success' => false,
            'data' => $postData,
            'dcc' => [],
            'debug' => [],
            'detail' => ''
        ];

        if (isset($postData['UserCancelled']) && $postData['UserCancelled'] === 'true') {
            $result['status'] = 'CANCELLED';
            $result['message'] = 'Utilizador cancelou a transação.';
            return $result;
        }

        if (isset($postData['messageType']) && in_array($postData['messageType'], self::SUCCESS_MESSAGE_TYPES, true)) {

            $finger = $this->fingerprintResponse($postData);
            $fingerValid = isset($postData['resultFingerPrint']) && $postData['resultFingerPrint'] === $finger;
            $result['success'] = $fingerValid;

            $result['status'] = $fingerValid ? 'SUCCESS' : 'INVALID_FINGERPRINT';
            $result['message'] = $fingerValid ? 'Transação válida.' : 'Fingerprint inválido.';

            if (!$fingerValid) {
                $result['debug'] = [
                    'recebido' => isset($postData['resultFingerPrint']) ? $postData['resultFingerPrint'] : '',
                    'calculado' => $finger
                ];
            }

            if (!empty($postData['merchantRespDCCData'])) {
                $dcc = json_decode($postData['merchantRespDCCData'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($dcc)) {
                    $result['dcc'] = [
                        'enabled' => isset($dcc['dcc']) && $dcc['dcc'] === 'Y',
                        'amount'  => isset($dcc['dccAmount']) ? $dcc['dccAmount'] : null,
                        'currency' => isset($dcc['dccCurrency']) ? $dcc['dccCurrency'] : null,
                        'markup'  => isset($dcc['dccMarkup']) ? $dcc['dccMarkup'] : null,
                        'rate'    => isset($dcc['dccRate']) ? $dcc['dccRate'] : null
                    ];
                }
            }

            return $result;
        }

        $result['message'] = isset($postData['merchantRespErrorDescription'])
            ? $postData['merchantRespErrorDescription']
            : 'Transação falhou.';

        $result['detail'] = isset($postData['merchantRespErrorDetail'])
            ? $postData['merchantRespErrorDetail']
            : null;

        return $result;
    }

    /**
     * Prepara a estrutura interna do pedido.
     *
     * @param array $params
     * @return void
     *
     * @throws Exception
     */
    private function preparePaymentRequest(array $params)
    {
        if ($this->prepared) {
            throw new Exception("Vinti4Net: ONLY 1 PAYMENT REQUEST MUST BE PREPARED");
        }

        $this->request = [
            'posID' => $this->posID,
            'merchantRef' => isset($params['merchantRef']) ? $params['merchantRef'] : 'R' . date('YmdHis'),
            'merchantSession' => isset($params['merchantSession']) ? $params['merchantSession'] : 'S' . date('YmdHis'),
            'amount' => (int)(float)$params['amount'],
            'currency' => $this->currencyToCode(isset($params['currency']) ? $params['currency'] : self::CURRENCY_CVE),
            'transactionCode' => isset($params['transactionCode']) ? $params['transactionCode'] : self::TRANSACTION_TYPE_PURCHASE,
            'languageMessages' => isset($params['languageMessages']) ? $params['languageMessages'] : 'pt',
            'entityCode' => isset($params['entityCode']) ? $params['entityCode'] : '',
            'referenceNumber' => isset($params['referenceNumber']) ? $params['referenceNumber'] : '',
            'timeStamp' => date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'is3DSec' => '1',
            'urlMerchantResponse' => isset($params['urlMerchantResponse']) ? $params['urlMerchantResponse'] : '',
            'billing' => isset($params['billing']) ? $params['billing'] : []
        ];

        $this->prepared = true;
    }

    /**
     * Monta os campos finais enviados ao POST do SISP.
     *
     * @param array $fields
     * @return array{postUrl:string,fields:array}
     */
    private function processRequest(array $fields)
    {
        if ($fields['transactionCode'] === self::TRANSACTION_TYPE_PURCHASE && !empty($fields['billing'])) {
            $fields['purchaseRequest'] = $this->generatePurchaseRequest($fields['billing']);
            $fields = array_merge($fields, $fields['billing']);
        }

        $type = $fields['transactionCode'] != self::TRANSACTION_TYPE_REFUND ? 'payment' : 'refund';

        unset($fields['billing']);

        $fields['fingerprint'] = $this->fingerprintRequest($fields, $type);

        $postUrl = $this->baseUrl . '?' . http_build_query([
            'FingerPrint' => $fields['fingerprint'],
            'TimeStamp' => $fields['timeStamp'],
            'FingerPrintVersion' => $fields['fingerprintversion']
        ]);

        return [
            'postUrl' => $postUrl,
            'fields' => $fields
        ];
    }

    /**
     * Normaliza os dados de billing para envio ao SISP.
     *
     * @param array $billing
     * @return array
     */
    private function normalizeBilling(array $billing)
    {
        $user = isset($billing['user']) ? $billing['user'] : [];
        unset($billing['user']);

        $get = function ($src, $key, $default = null) {
            if (is_array($src)) {
                return isset($src[$key]) ? $src[$key] : $default;
            }
            return isset($src->$key) ? $src->$key : $default;
        };

        $extractCC = function ($phone, $default = '238') {
            $phone = preg_replace('/\D+/', '', $phone);
            if (preg_match('/^(?:\+|00)?(\d{1,3})/', $phone, $m)) {
                return $m[1];
            }
            return $default;
        };

        $email    = isset($billing['email']) ? $billing['email'] : $get($user, 'email');
        $country  = isset($billing['billAddrCountry']) ? $billing['billAddrCountry'] : $get($user, 'country', '132');
        $city     = isset($billing['billAddrCity']) ? $billing['billAddrCity'] : $get($user, 'city', '');
        $line1    = isset($billing['billAddrLine1']) ? $billing['billAddrLine1'] : $get($user, 'address', '');
        $line2    = isset($billing['billAddrLine2']) ? $billing['billAddrLine2'] : $get($user, 'address2', '');
        $line3    = isset($billing['billAddrLine3']) ? $billing['billAddrLine3'] : $get($user, 'address3', '');
        $postcode = isset($billing['billAddrPostCode']) ? $billing['billAddrPostCode'] : $get($user, 'postCode', '');
        $state    = isset($billing['billAddrState']) ? $billing['billAddrState'] : $get($user, 'state');

        $mobile = isset($billing['mobilePhone']) ? $billing['mobilePhone'] : $get($user, 'mobilePhone', $get($user, 'phone'));
        $work   = isset($billing['workPhone']) ? $billing['workPhone'] : $get($user, 'workPhone');

        $mobilePhone = $mobile ? [
            'cc' => $get($user, 'mobilePhoneCC', $extractCC($mobile)),
            'subscriber' => preg_replace('/\D+/', '', $mobile)
        ] : null;

        $workPhone = $work ? [
            'cc' => $get($user, 'workPhoneCC', $extractCC($work)),
            'subscriber' => preg_replace('/\D+/', '', $work)
        ] : null;

        $acctID      = $get($user, 'id');
        $createdAt   = $get($user, 'created_at');
        $updatedAt   = $get($user, 'updated_at');
        $suspicious  = $get($user, 'suspicious');

        $chAccAgeInd = $get($user, 'chAccAgeInd', ($createdAt ? '05' : '01'));
        $chAccPwInd  = $get($user, 'chAccPwChangeInd', ($updatedAt ? '05' : '01'));

        $acctInfo = [
            'chAccAgeInd' => $chAccAgeInd,
            'chAccChange' => $updatedAt ? date('Ymd', strtotime($updatedAt)) : '',
            'chAccDate' => $createdAt ? date('Ymd', strtotime($createdAt)) : '',
            'chAccPwChange' => $updatedAt ? date('Ymd', strtotime($updatedAt)) : '',
            'chAccPwChangeInd' => $chAccPwInd,
            'suspiciousAccActivity' => isset($suspicious) ? ($suspicious ? '02' : '01') : ''
        ];

        foreach ($acctInfo as $k => $v) {
            if ($v === '') unset($acctInfo[$k]);
        }

        $data = [
            'email'            => $email,
            'billAddrCountry'  => $country,
            'billAddrCity'     => $city,
            'billAddrLine1'    => $line1,
            'billAddrLine2'    => $line2,
            'billAddrLine3'    => $line3,
            'billAddrPostCode' => $postcode,
            'billAddrState'    => $state,
            'mobilePhone'      => $mobilePhone,
            'workPhone'        => $workPhone,
            'acctID'           => $acctID,
            'acctInfo'         => !empty($acctInfo) ? $acctInfo : null,
        ];

        foreach ($data as $k => $v) {
            if ($v === null || $v === '') unset($data[$k]);
        }

        return $data;
    }

    /**
     * Converte moeda ISO para código numérico SISP.
     *
     * @param string|int $currency
     * @return int
     *
     * @throws InvalidArgumentException
     */
    private function currencyToCode($currency)
    {
        $currency = strtoupper($currency);

        switch ($currency) {
            case 'CVE':
                return 132;
            case 'USD':
                return 840;
            case 'EUR':
                return 978;
            case 'BRL':
                return 986;
            case 'GBP':
                return 826;
            case 'JPY':
                return 392;
            case 'CNY':
                return 156;
        }

        if (is_numeric($currency)) {
            return (int)$currency;
        }

        throw new InvalidArgumentException("Invalid currency code: $currency");
    }

    /**
     * Calcula fingerprint do pedido.
     *
     * @param array $data
     * @param string $type payment|refund
     * @return string Base64 do hash SHA512
     */
    private function fingerprintRequest(array $data, $type = 'payment')
    {
        $encodedPOSAuthCode = base64_encode(hash('sha512', $this->posAuthCode, true));

        // ------------------------------------------------------
        // PAYMENT
        // ------------------------------------------------------
        if ($type === 'payment') {

            $amount = isset($data['amount']) ? (float)$data['amount'] : 0;
            $amountLong = (int)bcmul($amount, '1000', 0);

            $entity = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
            $reference = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';

            $toHash = $encodedPOSAuthCode .
                (isset($data['timeStamp']) ? $data['timeStamp'] : '') .
                $amountLong .
                (isset($data['merchantRef']) ? $data['merchantRef'] : '') .
                (isset($data['merchantSession']) ? $data['merchantSession'] : '') .
                (isset($data['posID']) ? $data['posID'] : '') .
                (isset($data['currency']) ? $data['currency'] : '') .
                (isset($data['transactionCode']) ? $data['transactionCode'] : '') .
                $entity .
                $reference;

            return base64_encode(hash('sha512', $toHash, true));
        }

        // ------------------------------------------------------
        // REFUND
        // ------------------------------------------------------
        if ($type === 'refund') {

            // amount deve ser inteiro
            $amount = isset($data['amount']) ? (string)$data['amount'] : '';
            if (!preg_match('/^\d+$/', $amount)) {
                throw new InvalidArgumentException("Amount deve ser inteiro, sem casas decimais.");
            }

            $toHash = $encodedPOSAuthCode .
                (isset($data['posID']) ? $data['posID'] : '') .
                (isset($data['merchantRef']) ? $data['merchantRef'] : '') .
                (isset($data['merchantSession']) ? $data['merchantSession'] : '') .
                $amount .
                (isset($data['currency']) ? $data['currency'] : '') .
                (isset($data['transactionCode']) ? $data['transactionCode'] : '') .
                (isset($data['reversal']) ? $data['reversal'] : ''); // 'R'

            return base64_encode(hash('sha512', $toHash, true));
        }

        throw new InvalidArgumentException("Invalid fingerprint type: {$type}");
    }


    /**
     * Calcula fingerprint de resposta enviada pelo SISP.
     *
     * @param array $data
     * @param string $type
     * @return string Base64 do hash SHA512
     */
    private function fingerprintResponse(array $data, $type = 'payment')
    {
        $encodedPOSAuthCode = base64_encode(hash('sha512', $this->posAuthCode, true));

        // ------------------------------------------------------
        // PAYMENT
        // ------------------------------------------------------
        if ($type === 'payment') {

            $amount = isset($data["merchantRespPurchaseAmount"]) ? (float)$data["merchantRespPurchaseAmount"] : 0;
            $amountLong = (int)bcmul($amount, '1000', 0);

            $toHash =
                $encodedPOSAuthCode .
                (isset($data["messageType"]) ? $data["messageType"] : '') .
                (isset($data["merchantRespCP"]) ? $data["merchantRespCP"] : '') .
                (isset($data["merchantRespTid"]) ? $data["merchantRespTid"] : '') .
                (isset($data["merchantRespMerchantRef"]) ? $data["merchantRespMerchantRef"] : '') .
                (isset($data["merchantRespMerchantSession"]) ? $data["merchantRespMerchantSession"] : '') .
                $amountLong .
                (isset($data["merchantRespMessageID"]) ? $data["merchantRespMessageID"] : '') .
                (isset($data["merchantRespPan"]) ? $data["merchantRespPan"] : '') .
                (isset($data["merchantResp"]) ? $data["merchantResp"] : '') .
                (isset($data["merchantRespTimeStamp"]) ? $data["merchantRespTimeStamp"] : '') .
                (isset($data['merchantRespReferenceNumber']) && $data['merchantRespReferenceNumber'] !== '' ? (int)$data['merchantRespReferenceNumber'] : '') .
                (isset($data['merchantRespEntityCode']) && $data['merchantRespEntityCode'] !== '' ? (int)$data['merchantRespEntityCode'] : '') .
                (isset($data["merchantRespClientReceipt"]) ? $data["merchantRespClientReceipt"] : '') .
                trim(isset($data["merchantRespAdditionalErrorMessage"]) ? $data["merchantRespAdditionalErrorMessage"] : '') .
                (isset($data["merchantRespReloadCode"]) ? $data["merchantRespReloadCode"] : '');

            return base64_encode(hash('sha512', $toHash, true));
        }

        // ------------------------------------------------------
        // REFUND
        // ------------------------------------------------------
        if ($type === 'refund') {

            $amount = isset($data['merchantRespPurchaseAmount'])
                ? (int)$data['merchantRespPurchaseAmount']
                : 0;

            $toHash =
                $encodedPOSAuthCode .
                (isset($data["messageType"]) ? $data["messageType"] : '') .
                (isset($data["merchantRespClearingPeriod"]) ? $data["merchantRespClearingPeriod"] : '') .
                (isset($data["merchantRespTransactionID"]) ? $data["merchantRespTransactionID"] : '') .
                (isset($data["merchantRespMerchantRef"]) ? $data["merchantRespMerchantRef"] : '') .
                (isset($data["merchantRespMerchantSession"]) ? $data["merchantRespMerchantSession"] : '') .
                $amount .
                (isset($data["merchantRespMessageID"]) ? $data["merchantRespMessageID"] : '') .
                (isset($data["merchantResp"]) ? $data["merchantResp"] : '') .
                (isset($data["merchantRespTimeStamp"]) ? $data["merchantRespTimeStamp"] : '');

            return base64_encode(hash('sha512', $toHash, true));
        }

        throw new InvalidArgumentException("Invalid fingerprint type: {$type}");
    }


    /**
     * Gera o JSON codificado da secção billing.
     *
     * @param array $billing
     * @return string Base64 JSON
     *
     * @throws InvalidArgumentException
     */
    private function generatePurchaseRequest(array $billing)
    {
        $required = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];

        $billing = $this->normalizeBilling($billing);

        foreach ($required as $key) {
            if (!isset($billing[$key]) || $billing[$key] === '') {
                throw new InvalidArgumentException("Campo obrigatório ausente em billing: {$key}");
            }
        }

        $json = json_encode($billing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException("Erro ao gerar JSON de billing.");
        }

        return base64_encode($json);
    }
}
