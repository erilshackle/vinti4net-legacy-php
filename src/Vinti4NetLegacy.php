<?php



/**
 * Vinti4Net Legacy Standalone SDK
 *
 * Standalone PHP integration for Vinti4Net (SISP - Cabo Verde), providing
 * payment processing without requiring Composer or external dependencies.
 *
 * This legacy implementation is compatible with PHP 5.6+ and provides
 * a single-file integration intended for projects running older PHP versions.
 *
 * Supported operations:
 * - Purchase (3D Secure)
 * - Service Payment
 * - Recharge / Top-up
 * - Refund
 *
 * Main features:
 * - Auto-submit HTML forms for redirecting customers to Vinti4Net
 * - Request and response fingerprint generation and validation
 * - Gateway response processing with normalized array results
 * - Billing data normalization for 3D Secure transactions
 * - Dynamic Currency Conversion (DCC) response support
 * - Merchant reference and session generation
 * - No Composer or external dependencies required
 *
 * Responses are returned as arrays and receipt rendering is intentionally
 * not included in this standalone implementation.
 *
 * This is a community integration and is not an official SISP SDK.
 *
 * @package   Erilshk\Vinti4Net
 * @author    Erilando TS Carvalho
 * @license   MIT
 * @version   2.0.0
 */

/**
 * Exception thrown for invalid Vinti4Net configuration, requests or responses.
 */
class Vinti4Exception extends RuntimeException {}

/**
 * Vinti4Net Legacy Standalone SDK
 *
 * Standalone PHP integration for Vinti4Net (SISP – Cabo Verde),
 * compatible with PHP 5.6+.
 * 
 * Provides a fluent API for preparing transactions, generating the
 * auto-submit payment form and processing responses returned by SISP.
 *
 * @package Erilshk\Vinti4NetLegacy
 * @author  Erilando TS Carvalho
 * @license MIT
 * @version 2.0.0
 */
final class Vinti4Net
{
    const DEFAULT_BASE_URL = 'https://mc.vinti4net.cv/BizMPIOnUsSisp';

    const TRANSACTION_TYPE_PURCHASE = '1';
    const TRANSACTION_TYPE_SERVICE = '2';
    const TRANSACTION_TYPE_RECHARGE = '3';
    const TRANSACTION_TYPE_REFUND = '4';

    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_ERROR = 'ERROR';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_INVALID_FINGERPRINT = 'INVALID_FINGERPRINT';

    const CURRENCY_CVE = '132';
    const ENDPOINT_PATH = '/CardPayment';
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    /** @var string POS identifier provided by SISP. */
    private $posID;

    /** @var string POS authentication code provided by SISP. */
    private $posAuthCode;

    /** @var string|null Custom CardPayment endpoint. */
    private $endpoint;

    /** @var array<string, mixed> Currently prepared transaction data. */
    private $request = array();

    /** @var bool Whether a transaction has been prepared. */
    private $prepared = false;

    /**
     * Create a standalone Vinti4Net client.
     *
     * @param string      $posID       POS identifier provided by SISP.
     * @param string      $posAuthCode POS authentication code provided by SISP.
     * @param string|null $endpoint    Optional custom CardPayment endpoint.
     *
     * @throws Vinti4Exception If credentials are empty or the endpoint is invalid.
     */
    public function __construct(
        $posID,
        $posAuthCode,
        $endpoint = null
    ) {
        $posID = trim($posID);
        $posAuthCode = trim($posAuthCode);
        $endpoint = $endpoint !== null ? trim($endpoint) : null;

        if ($posID === '') {
            throw new Vinti4Exception('O POS ID não pode estar vazio.');
        }

        if ($posAuthCode === '') {
            throw new Vinti4Exception('O código de autenticação não pode estar vazio.');
        }

        if ($endpoint !== null && filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new Vinti4Exception('A URL base da SISP deve ser válida.');
        }

        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->endpoint = $endpoint;
    }

    /**
     * Generate a 15-character merchant reference.
     *
     * Format: R + ymdHis + two random alphanumeric characters.
     *
     * @return string
     */
    public static function generateMerchantRef()
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $suffix = $characters[self::secureRandomInt(0, 35)] . $characters[self::secureRandomInt(0, 35)];

        return 'R' . date('ymdHis') . $suffix;
    }


    /** PHP 5.6 compatible random integer helper. */
    /**
     * Generate a random integer while remaining compatible with PHP 5.6.
     *
     * Uses random_int() when available, OpenSSL on PHP 5.6 when possible,
     * and falls back to mt_rand() as a last resort.
     *
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     * @return int
     */
    private static function secureRandomInt($min, $max)
    {
        if (function_exists('random_int')) {
            return random_int($min, $max);
        }

        $range = $max - $min;
        if ($range <= 0) {
            return $min;
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $limit = 0;
            do {
                $bytes = openssl_random_pseudo_bytes(4, $strong);
                if ($bytes !== false && $strong) {
                    $value = unpack('N', $bytes);
                    $value = $value[1] & 0x7fffffff;
                    $limit = 0x7fffffff - (0x7fffffff % ($range + 1));
                } else {
                    $value = false;
                }
            } while ($value !== false && $value >= $limit);

            if ($value !== false) {
                return $min + ($value % ($range + 1));
            }
        }

        return mt_rand($min, $max);
    }

    /**
     * Configure the merchant reference and session.
     *
     * SISP requires both values to contain exactly 15 characters. When the
     * session is omitted, a timestamp-based merchant session is generated.
     *
     * @param string      $reference Merchant reference.
     * @param string|null $session   Optional merchant session.
     * @return $this
     */
    public function setMerchant($reference, $session = null)
    {
        $this->request['merchantRef'] = trim($reference);
        $this->request['merchantSession'] = $session !== null ? trim($session) : 'S' . date('YmdHis');
        return $this;
    }

    /**
     * Prepare a purchase transaction.
     *
     * Billing data is optional. When provided, it is normalized and encoded
     * into the purchaseRequest field used for 3D Secure processing. Friendly
     * billing names and original SISP field names are accepted.
     *
     * @param int|float|string $amount Transaction amount.
     * @param array $billing Optional billing / 3D Secure data.
     * @param string|int $currency ISO 4217 alphabetic or numeric currency.
     * @return $this
     */
    public function preparePurchase(
        $amount,
        $billing = [],
        $currency = 'CVE'
    ) {
        $this->prepareRequest([
            'transactionCode' => self::TRANSACTION_TYPE_PURCHASE,
            'amount' => $amount,
            'currency' => $currency,
            'billing' => $billing,
        ]);

        return $this;
    }

    /** Prepare a service payment. */
    /**
     * Prepare a service payment.
     *
     * @param int|float|string $amount Transaction amount.
     * @param int|string $entity SISP entity code.
     * @param string|int $number Payment reference number.
     * @return $this
     */
    public function prepareServicePayment(
        $amount,
        $entity,
        $number
    ) {
        $this->prepareRequest([
            'transactionCode' => self::TRANSACTION_TYPE_SERVICE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
        ]);

        return $this;
    }

    /** Prepare a recharge payment. */
    /**
     * Prepare a recharge / top-up transaction.
     *
     * @param int|float|string $amount Recharge amount.
     * @param int|string $entity SISP entity code.
     * @param string|int $number Recharge reference number.
     * @return $this
     */
    public function prepareRecharge(
        $amount,
        $entity,
        $number
    ) {
        $this->prepareRequest([
            'transactionCode' => self::TRANSACTION_TYPE_RECHARGE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
        ]);

        return $this;
    }

    /** Prepare a refund. */
    /**
     * Prepare a refund transaction.
     *
     * Refunds require the original SISP transaction identifier and clearing
     * period and are submitted using transaction code 4 in CVE.
     *
     * @param int|float|string $amount Amount to refund.
     * @param string $transactionID Original SISP transaction ID.
     * @param string|int $clearingPeriod Original clearing period.
     * @return $this
     */
    public function prepareRefund(
        $amount,
        $transactionID,
        $clearingPeriod
    ) {
        $this->prepareRequest([
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'amount' => $amount,
            'transactionID' => $transactionID,
            'clearingPeriod' => $clearingPeriod,
        ]);

        return $this;
    }

    /**
     * Generate an auto-submitting HTML payment form.
     *
     * The prepared transaction is validated, signed and converted into hidden
     * HTML fields before submission to the configured Vinti4Net endpoint.
     *
     * @param string $responseUrl Merchant callback URL.
     * @param string $lang Message language: pt, en or fr.
     * @return string Complete auto-submit HTML document.
     * @throws Vinti4Exception If no transaction is prepared or validation fails.
     */
    public function createPaymentForm($responseUrl, $lang = 'pt')
    {
        if (!$this->prepared) {
            throw new Vinti4Exception('Nenhum pagamento preparado.');
        }

        $params = $this->request;
        $params['languageMessages'] = strtolower(trim($lang));
        $params['urlMerchantResponse'] = trim($responseUrl);

        $prepared = ((isset($params['transactionCode']) ? $params['transactionCode'] : '')) === self::TRANSACTION_TYPE_REFUND
            ? $this->prepareRefundRequest($params)
            : $this->preparePaymentRequest($params);

        $fields = $prepared['fields'];
        $postUrl = $prepared['postUrl'];
        $inputs = '';

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $name = htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $inputs .= "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\">\n";
        }

        $action = htmlspecialchars($postUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $processing = $params['languageMessages'] === 'pt'
            ? 'Processando...'
            : 'Processing...';

        return <<<HTML
<!doctype html>
<html lang="{$params['languageMessages']}">
<head>
    <meta charset="UTF-8">
    <title>Pagamento Vinti4Net</title>
</head>
<body onload="document.forms[0].submit()">
    <form method="post" action="{$action}">
{$inputs}    </form>
    <p>{$processing}</p>
</body>
</html>
HTML;
    }

    /**
     * Process and normalize a response returned by SISP.
     *
     * Validates successful response fingerprints, detects cancellation, resolves
     * provider errors, masks PAN data and extracts DCC information.
     *
     * @param array $postData Raw POST data received from SISP.
     * @return array Normalized response with status, message, success, data,
     *               dcc, debug, detail and operation.
     * @throws Vinti4Exception If the response is empty or cannot be processed.
     */
    public function processResponse($postData)
    {
        if ($postData === []) {
            throw new Vinti4Exception('A resposta da SISP está vazia.');
        }

        $messageType = trim((string) ((isset($postData['messageType']) ? $postData['messageType'] : '')));
        $successType = in_array($messageType, self::SUCCESS_MESSAGE_TYPES, true);
        $transactionSuccessful = false;


        $transactionSuccessful =
            // compra sucesso
            ($successType && ((isset($postData['merchantResp']) ? $postData['merchantResp'] : '')) === 'C')
            // estorno sucesso
            || $messageType == '10';



        $fingerprintValid = true;
        $calculatedFingerprint = null;

        if ($successType) {
            $calculatedFingerprint = $this->fingerprintResponse($postData);
            $receivedFingerprint = trim((string) ((isset($postData['resultFingerPrint']) ? $postData['resultFingerPrint'] : '')));
            $fingerprintValid = $receivedFingerprint !== ''
                && hash_equals($calculatedFingerprint, $receivedFingerprint);
        }

        if ($fingerprintValid === false) {
            $status = self::STATUS_INVALID_FINGERPRINT;
        } elseif (filter_var((isset($postData['UserCancelled']) ? $postData['UserCancelled'] : false), FILTER_VALIDATE_BOOLEAN)) {
            $status = self::STATUS_CANCELLED;
        } elseif ($transactionSuccessful) {
            $status = self::STATUS_SUCCESS;
        } else {
            $status = self::STATUS_ERROR;
        }

        $operation = null;
        switch ($messageType) {
            case '10':
                $operation = 'refund';
                break;
            case '8':
                $operation = 'purchase';
                break;
            case 'P':
                $operation = 'service_payment';
                break;
            case 'M':
                $operation = 'recharge';
                break;
        }

        if ($status === self::STATUS_CANCELLED) {
            $message = 'Utilizador cancelou a transação.';
        } elseif ($status === self::STATUS_SUCCESS) {
            $message = $operation === 'refund'
                ? 'Reembolso processado com sucesso.'
                : 'Transação válida.';
        } elseif ($status === self::STATUS_INVALID_FINGERPRINT) {
            $message = 'Fingerprint inválido (verificar segurança).';
        } else {
            $message = 'Transação falhou.';

            foreach (
                [
                    'merchantRespAdditionalErrorMessage',
                    'merchantRespErrorDetail',
                    'merchantRespErrorDescription',
                ] as $field
            ) {
                $providerMessage = trim((string) (isset($postData[$field]) ? $postData[$field] : ''));

                if ($providerMessage !== '') {
                    $message = $providerMessage;
                    break;
                }
            }
        }

        $dcc = $this->extractDcc($postData);
        $debug = $fingerprintValid === false
            ? [
                'received' => (string) ((isset($postData['resultFingerPrint']) ? $postData['resultFingerPrint'] : '')),
                'calculated' => (string) $calculatedFingerprint,
            ]
            : [];

        $safeData = $postData;

        if (isset($safeData['merchantRespPan'])) {
            $pan = preg_replace('/\D+/', '', (string) $safeData['merchantRespPan']);
            $pan = $pan === null ? '' : $pan;
            $safeData['merchantRespPan'] = $pan !== '' && $pan !== '0'
                ? '•••• ' . substr($pan, -4)
                : null;
        }

        return [
            'status' => $status,
            'message' => $message,
            'success' => $status === self::STATUS_SUCCESS,
            'data' => $safeData,
            'dcc' => $dcc,
            'debug' => $debug,
            'detail' => isset($postData['merchantRespErrorDetail'])
                ? (string) $postData['merchantRespErrorDetail']
                : null,
            'operation' => $operation,
        ];
    }

    /** Return the currently prepared transaction data. */
    /**
     * Return the currently prepared transaction data.
     *
     * @return array
     */
    public function getRequest()
    {
        return $this->request;
    }

    /** @param array<string, mixed> $transaction */
    /**
     * Replace the current transaction while preserving persistent merchant data.
     *
     * @param array $transaction
     * @return void
     */
    private function prepareRequest($transaction)
    {
        $persistent = array_intersect_key(
            $this->request,
            array_flip([
                'merchantRef',
                'merchantSession',
                'languageMessages',
                'timeStamp',
            ])
        );

        $this->request = array_merge($transaction, $persistent);
        $this->prepared = true;
    }

    /** @return array{fields: array<string, mixed>, postUrl: string} */
    /**
     * Build and validate a purchase, service payment or recharge request.
     *
     * @param array $params
     * @return array Array containing postUrl and fields.
     * @throws Vinti4Exception
     */
    private function preparePaymentRequest($params)
    {
        $transactionCode = (string) ((isset($params['transactionCode']) ? $params['transactionCode'] : ''));

        if ($transactionCode === '') {
            throw new Vinti4Exception('transactionCode é obrigatório.');
        }

        $request = [
            'posID' => $this->posID,
            'merchantRef' => (isset($params['merchantRef']) ? $params['merchantRef'] : self::generateMerchantRef()),
            'merchantSession' => (isset($params['merchantSession']) ? $params['merchantSession'] : 'S' . date('YmdHis')),
            'amount' => $this->normalizeRequestAmount((isset($params['amount']) ? $params['amount'] : '')),
            'currency' => $this->currencyToCode((isset($params['currency']) ? $params['currency'] : self::CURRENCY_CVE)),
            'transactionCode' => $transactionCode,
            'languageMessages' => (isset($params['languageMessages']) ? $params['languageMessages'] : 'pt'),
            'entityCode' => (isset($params['entityCode']) ? $params['entityCode'] : ''),
            'referenceNumber' => (isset($params['referenceNumber']) ? $params['referenceNumber'] : ''),
            'timeStamp' => (isset($params['timeStamp']) ? $params['timeStamp'] : date('Y-m-d H:i:s')),
            'fingerprintversion' => '1',
            'is3DSec' => '1',
            'urlMerchantResponse' => (isset($params['urlMerchantResponse']) ? $params['urlMerchantResponse'] : ''),
        ];

        if ($transactionCode === self::TRANSACTION_TYPE_PURCHASE && !empty($params['billing'])) {
            $billing = $this->normalizeBilling((array) $params['billing']);
            $request['purchaseRequest'] = $this->generatePurchaseRequest($billing);
        }

        if ($error = $this->validateRequest($request)) {
            throw new Vinti4Exception($error);
        }

        $request['fingerprint'] = $this->fingerprintRequest($request);

        return [
            'postUrl' => $this->buildPostUrl($request),
            'fields' => $request,
        ];
    }

    /** @return array{fields: array<string, mixed>, postUrl: string} */
    /**
     * Build and validate a refund request.
     *
     * @param array $params
     * @return array Array containing postUrl and fields.
     * @throws Vinti4Exception
     */
    private function prepareRefundRequest($params)
    {
        foreach (['amount', 'urlMerchantResponse', 'clearingPeriod', 'transactionID'] as $field) {
            if (empty($params[$field])) {
                throw new Vinti4Exception("Campo obrigatório faltando: {$field}");
            }
        }

        $request = [
            'posID' => $this->posID,
            'merchantRef' => (isset($params['merchantRef']) ? $params['merchantRef'] : self::generateMerchantRef()),
            'merchantSession' => (isset($params['merchantSession']) ? $params['merchantSession'] : 'S' . date('YmdHis')),
            'amount' => $this->normalizeRequestAmount($params['amount']),
            'currency' => self::CURRENCY_CVE,
            'is3DSec' => '1',
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'urlMerchantResponse' => $params['urlMerchantResponse'],
            'languageMessages' => (isset($params['languageMessages']) ? $params['languageMessages'] : 'pt'),
            'timeStamp' => (isset($params['timeStamp']) ? $params['timeStamp'] : date('Y-m-d H:i:s')),
            'fingerprintversion' => '1',
            'entityCode' => '',
            'referenceNumber' => '',
            'reversal' => 'R',
            'clearingPeriod' => $params['clearingPeriod'],
            'transactionID' => $params['transactionID'],
        ];

        if ($error = $this->validateRequest($request)) {
            throw new Vinti4Exception($error);
        }

        $request['fingerprint'] = $this->fingerprintRequest($request);

        return [
            'postUrl' => $this->buildPostUrl($request),
            'fields' => $request,
        ];
    }

    /** @param array<string, mixed> $request */
    /**
     * Build the CardPayment URL including fingerprint metadata.
     *
     * @param array $request
     * @return string
     */
    private function buildPostUrl($request)
    {
        $endpoint = $this->endpoint !== null
            ? $this->endpoint
            : rtrim(self::DEFAULT_BASE_URL, '/') . self::ENDPOINT_PATH;

        return $endpoint . '?' . http_build_query([
            'FingerPrint' => $request['fingerprint'],
            'TimeStamp' => $request['timeStamp'],
            'FingerPrintVersion' => $request['fingerprintversion'],
        ]);
    }

    /** @param array<string, mixed> $data */
    /**
     * Generate the request fingerprint expected by SISP.
     *
     * @param array $data Request fields.
     * @return string Base64-encoded SHA-512 fingerprint.
     */
    private function fingerprintRequest($data)
    {
        $entity = !empty($data['entityCode']) ? (int) $data['entityCode'] : '';
        $reference = !empty($data['referenceNumber']) ? (int) $data['referenceNumber'] : '';

        $toHash = $this->encodedAuthCode()
            . ((isset($data['timeStamp']) ? $data['timeStamp'] : ''))
            . $this->amountToLong((isset($data['amount']) ? $data['amount'] : null))
            . ((isset($data['merchantRef']) ? $data['merchantRef'] : ''))
            . ((isset($data['merchantSession']) ? $data['merchantSession'] : ''))
            . ((isset($data['posID']) ? $data['posID'] : ''))
            . ((isset($data['currency']) ? $data['currency'] : ''))
            . ((isset($data['transactionCode']) ? $data['transactionCode'] : ''))
            . $entity
            . $reference;

        return base64_encode(hash('sha512', $toHash, true));
    }

    /** @param array<string, mixed> $data */
    /**
     * Generate the expected fingerprint for a SISP response.
     *
     * @param array $data Raw response fields.
     * @return string Base64-encoded SHA-512 fingerprint.
     */
    private function fingerprintResponse($data)
    {
        $toHash = $this->encodedAuthCode()
            . ((isset($data['messageType']) ? $data['messageType'] : ''))
            . ((isset($data['merchantRespCP']) ? $data['merchantRespCP'] : ''))
            . ((isset($data['merchantRespTid']) ? $data['merchantRespTid'] : ''))
            . ((isset($data['merchantRespMerchantRef']) ? $data['merchantRespMerchantRef'] : ''))
            . ((isset($data['merchantRespMerchantSession']) ? $data['merchantRespMerchantSession'] : ''))
            . $this->amountToLong((isset($data['merchantRespPurchaseAmount']) ? $data['merchantRespPurchaseAmount'] : null))
            . ((isset($data['merchantRespMessageID']) ? $data['merchantRespMessageID'] : ''))
            . ((isset($data['merchantRespPan']) ? $data['merchantRespPan'] : ''))
            . ((isset($data['merchantResp']) ? $data['merchantResp'] : ''))
            . ((isset($data['merchantRespTimeStamp']) ? $data['merchantRespTimeStamp'] : ''))
            . (!empty($data['merchantRespReferenceNumber'])
                ? (int) $data['merchantRespReferenceNumber']
                : '')
            . (!empty($data['merchantRespEntityCode'])
                ? (int) $data['merchantRespEntityCode']
                : '')
            . ((isset($data['merchantRespClientReceipt']) ? $data['merchantRespClientReceipt'] : ''))
            . trim((string) ((isset($data['merchantRespAdditionalErrorMessage']) ? $data['merchantRespAdditionalErrorMessage'] : '')))
            . ((isset($data['merchantRespReloadCode']) ? $data['merchantRespReloadCode'] : ''));

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     * Generate the encoded POS authentication value used by fingerprints.
     *
     * @return string
     */
    private function encodedAuthCode()
    {
        return base64_encode(hash('sha512', $this->posAuthCode, true));
    }

    /**
     * Normalize and validate an amount sent in a request.
     *
     * @param int|float|string $amount
     * @return string
     * @throws Vinti4Exception
     */
    private function normalizeRequestAmount($amount)
    {
        $value = trim((string) $amount);

        if (!preg_match('/^[1-9]\d{0,12}$/', $value)) {
            throw new Vinti4Exception(
                'Amount deve ser um inteiro positivo com no máximo 13 dígitos.'
            );
        }

        return $value;
    }

    /**
     * Convert a SISP amount into the integer representation used by fingerprints.
     *
     * The conversion avoids floating-point arithmetic and BCMath.
     *
     * @param int|float|string|null $amount
     * @return string
     * @throws Vinti4Exception
     */
    private function amountToLong($amount)
    {
        $value = trim((string) ($amount !== null ? $amount : '0'));

        if (!preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $value, $matches)) {
            throw new Vinti4Exception(
                'O valor da resposta da SISP possui formato inválido.'
            );
        }

        $integer = ltrim($matches[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad(isset($matches[2]) ? $matches[2] : '', 3, '0');
        $result = ltrim($integer . $fraction, '0');

        return $result === '' ? '0' : $result;
    }

    /**
     * Convert an ISO 4217 alphabetic currency to its numeric representation.
     *
     * @param string|int $currency
     * @return string
     * @throws Vinti4Exception
     */
    private function currencyToCode($currency)
    {
        $currency = strtoupper(trim((string) $currency));

        switch ($currency) {
            case 'CVE':
                return '132';
            case 'USD':
                return '840';
            case 'EUR':
                return '978';
            case 'BRL':
                return '986';
            case 'GBP':
                return '826';
            case 'JPY':
                return '392';
        }

        if (preg_match('/^\d{3}$/', $currency)) {
            return $currency;
        }

        throw new Vinti4Exception("Moeda inválida: {$currency}.");
    }

    /** @param array<string, mixed> $params */
    /**
     * Validate a prepared SISP request.
     *
     * @param array $params
     * @return string|null Validation error or null when valid.
     */
    private function validateRequest($params)
    {
        $transactionCode = (string) ((isset($params['transactionCode']) ? $params['transactionCode'] : ''));

        if (!in_array($transactionCode, ['1', '2', '3', '4'], true)) {
            return 'TransactionCode não suportado. Valores válidos: 1,2,3,4.';
        }

        if (strlen(trim((string) ((isset($params['merchantRef']) ? $params['merchantRef'] : '')))) !== 15) {
            return 'MerchantRef é obrigatório e deve ter exatamente 15 caracteres.';
        }

        if (strlen(trim((string) ((isset($params['merchantSession']) ? $params['merchantSession'] : '')))) !== 15) {
            return 'MerchantSession é obrigatório e deve ter exatamente 15 caracteres.';
        }

        if (in_array($transactionCode, ['2', '3'], true)) {
            if (!preg_match('/^\d+$/', (string) ((isset($params['entityCode']) ? $params['entityCode'] : '')))) {
                return 'EntityCode é obrigatório e deve ser numérico.';
            }

            if (!preg_match('/^\d{1,9}$/', (string) ((isset($params['referenceNumber']) ? $params['referenceNumber'] : '')))) {
                return 'ReferenceNumber é obrigatório e deve ter até 9 dígitos.';
            }
        }

        if (!preg_match('/^[1-9]\d{0,12}$/', (string) ((isset($params['amount']) ? $params['amount'] : '')))) {
            return 'Amount deve ser um inteiro positivo com até 13 dígitos.';
        }

        if (!preg_match('/^\d{3}$/', (string) ((isset($params['currency']) ? $params['currency'] : '')))) {
            return 'Currency deve ser um código numérico ISO 4217 de 3 dígitos.';
        }

        if (
            $transactionCode === self::TRANSACTION_TYPE_REFUND
            && $params['currency'] !== self::CURRENCY_CVE
        ) {
            return "Currency para estorno deve ser '132' (CVE).";
        }

        if (filter_var((isset($params['urlMerchantResponse']) ? $params['urlMerchantResponse'] : null), FILTER_VALIDATE_URL) === false) {
            return 'UrlMerchantResponse deve ser uma URL válida.';
        }

        if (!in_array(strtolower((string) ((isset($params['languageMessages']) ? $params['languageMessages'] : ''))), ['pt', 'en', 'fr'], true)) {
            return "LanguageMessages deve ser 'pt', 'en' ou 'fr'.";
        }

        if ($transactionCode === self::TRANSACTION_TYPE_REFUND) {
            if (!preg_match('/^\d{1,4}$/', (string) ((isset($params['clearingPeriod']) ? $params['clearingPeriod'] : '')))) {
                return 'ClearingPeriod deve ter até 4 dígitos numéricos.';
            }

            if (!preg_match('/^[A-Za-z0-9]{1,8}$/', (string) ((isset($params['transactionID']) ? $params['transactionID'] : '')))) {
                return 'TransactionID deve ter até 8 caracteres alfanuméricos.';
            }
        }

        return null;
    }

    /**
     * Normalize billing and 3D Secure customer data.
     *
     * Accepts friendly field names and original SISP equivalents and normalizes
     * phones, account information and address matching values.
     *
     * @param array $billing
     * @return array
     */
    private function normalizeBilling($billing)
    {
        $map = [
            'email' => 'email',
            'country' => 'billAddrCountry',
            'billAddrCountry' => 'billAddrCountry',
            'city' => 'billAddrCity',
            'billAddrCity' => 'billAddrCity',
            'address' => 'billAddrLine1',
            'billAddrLine1' => 'billAddrLine1',
            'address2' => 'billAddrLine2',
            'billAddrLine2' => 'billAddrLine2',
            'address3' => 'billAddrLine3',
            'billAddrLine3' => 'billAddrLine3',
            'postalCode' => 'billAddrPostCode',
            'billAddrPostCode' => 'billAddrPostCode',
            'state' => 'billAddrState',
            'billAddrState' => 'billAddrState',
            'shipCountry' => 'shipAddrCountry',
            'shipAddrCountry' => 'shipAddrCountry',
            'shipCity' => 'shipAddrCity',
            'shipAddrCity' => 'shipAddrCity',
            'shipAddress' => 'shipAddrLine1',
            'shipAddrLine1' => 'shipAddrLine1',
            'shipPostalCode' => 'shipAddrPostCode',
            'shipAddrPostCode' => 'shipAddrPostCode',
            'shipState' => 'shipAddrState',
            'shipAddrState' => 'shipAddrState',
            'phone' => 'mobilePhone',
            'mobilePhone' => 'mobilePhone',
            'workPhone' => 'workPhone',
            'accountId' => 'acctID',
            'acctID' => 'acctID',
            'accountInfo' => 'acctInfo',
            'acctInfo' => 'acctInfo',
            'addressMatchesShipping' => 'addrMatch',
            'addrMatch' => 'addrMatch',
        ];

        $normalized = ['billAddrCountry' => '132'];

        foreach ($billing as $key => $value) {
            $field = isset($map[$key]) ? $map[$key] : null;

            if ($field === null) {
                continue;
            }

            if ($field === 'mobilePhone' || $field === 'workPhone') {
                if (is_string($value) || is_int($value)) {
                    $value = ['cc' => '238', 'subscriber' => (string) $value];
                }

                if (is_array($value)) {
                    $cc = preg_replace('/\D+/', '', (string) (isset($value['cc']) ? $value['cc'] : '238'));
                    $cc = $cc === null ? '' : $cc;
                    $subscriber = preg_replace('/\D+/', '', (string) (isset($value['subscriber']) ? $value['subscriber'] : ''));
                    $subscriber = $subscriber === null ? '' : $subscriber;
                    $value = $subscriber !== ''
                        ? [
                            'cc' => $cc !== '' ? $cc : '238',
                            'subscriber' => $subscriber,
                        ]
                        : null;
                } else {
                    $value = null;
                }
            }

            if ($field === 'addrMatch' && is_bool($value)) {
                $value = $value ? 'Y' : 'N';
            }

            $normalized[$field] = $value;
        }

        if (array_key_exists('suspicious', $billing)) {
            $accountInfo = is_array((isset($normalized['acctInfo']) ? $normalized['acctInfo'] : null))
                ? $normalized['acctInfo']
                : [];
            $accountInfo['suspiciousAccActivity'] = $billing['suspicious'] ? '02' : '01';
            $normalized['acctInfo'] = $accountInfo;
        }

        if (isset($normalized['acctInfo']) && is_array($normalized['acctInfo'])) {
            $normalized['acctInfo'] = array_filter(
                array_merge([
                    'chAccAgeInd' => '01',
                    'chAccChange' => '',
                    'chAccDate' => '',
                    'chAccPwChange' => '',
                    'chAccPwChangeInd' => '01',
                    'suspiciousAccActivity' => '01',
                ], $normalized['acctInfo']),
                function ($value) {
                    return $value !== null && $value !== '';
                }
            );
        }

        return array_filter(
            $normalized,
            function ($value) {
                return $value !== null && $value !== '' && $value !== array();
            }
        );
    }

    /** @param array<string, mixed> $billing */
    /**
     * Generate the Base64-encoded 3D Secure purchaseRequest.
     *
     * @param array $billing
     * @return string
     * @throws Vinti4Exception If required fields are missing or JSON encoding fails.
     */
    private function generatePurchaseRequest($billing)
    {
        $required = [
            'email',
            'billAddrCountry',
            'billAddrCity',
            'billAddrLine1',
            'billAddrPostCode',
        ];

        $missing = array_filter(
            $required,
            function ($field) use ($billing) {
                return !isset($billing[$field]) || trim((string) $billing[$field]) === '';
            }
        );

        if ($missing !== []) {
            throw new Vinti4Exception(
                'Campos obrigatórios ausentes em billing: '
                    . implode(', ', $missing)
                    . '.'
            );
        }

        $json = json_encode($billing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new Vinti4Exception(
                'Erro ao gerar JSON para billing (purchaseRequest): ' . json_last_error_msg()
            );
        }

        return base64_encode($json);
    }

    /** @param array<string, mixed> $data */
    /**
     * Extract Dynamic Currency Conversion data from a SISP response.
     *
     * @param array $data Raw response data.
     * @return array Normalized DCC information.
     */
    private function extractDcc($data)
    {
        $rawDcc = trim((string) ((isset($data['merchantRespDCCData']) ? $data['merchantRespDCCData'] : '')));

        if ($rawDcc === '') {
            return ['enabled' => false];
        }

        $dcc = json_decode($rawDcc, true);

        if (!is_array($dcc)) {
            return [
                'enabled' => false,
                'error' => 'DCC inválido ou mal formatado.',
            ];
        }

        return [
            'enabled' => ((isset($dcc['dcc']) ? $dcc['dcc'] : 'N')) === 'Y',
            'amount' => (isset($dcc['dccAmount']) ? $dcc['dccAmount'] : null),
            'currency' => (isset($dcc['dccCurrency']) ? $dcc['dccCurrency'] : null),
            'markup' => (isset($dcc['dccMarkup']) ? $dcc['dccMarkup'] : null),
            'rate' => (isset($dcc['dccRate']) ? $dcc['dccRate'] : null),
        ];
    }
}
