<?php

declare(strict_types=1);

namespace Tests\Unit;

use \Vinti4Exception;
use \Vinti4Net;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class Vinti4NetLegacyTest extends TestCase
{
    /** @var Vinti4Net */
    private $vinti4;

    protected function setUp(): void
    {
        $this->vinti4 = new Vinti4Net(
            'POS123',
            'ABCDEF123456789',
            'https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment'
        );
    }

    // =========================================================
    // Constructor
    // =========================================================

    public function testConstructorRejectsEmptyPosId(): void
    {
        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage('O POS ID não pode estar vazio.');

        new Vinti4Net(
            '',
            'ABCDEF123456789'
        );
    }

    public function testConstructorRejectsEmptyAuthCode(): void
    {
        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'O código de autenticação não pode estar vazio.'
        );

        new Vinti4Net(
            'POS123',
            ''
        );
    }

    public function testConstructorRejectsInvalidEndpoint(): void
    {
        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'A URL base da SISP deve ser válida.'
        );

        new Vinti4Net(
            'POS123',
            'ABCDEF123456789',
            'not-a-url'
        );
    }

    // =========================================================
    // Merchant reference
    // =========================================================

    public function testGenerateMerchantRefReturnsExactly15Characters(): void
    {
        $reference = Vinti4Net::generateMerchantRef();

        self::assertSame(15, strlen($reference));
        self::assertSame('R', $reference[0]);
        self::assertMatchesRegularExpression(
            '/^R\d{12}[A-Z0-9]{2}$/',
            $reference
        );
    }

    public function testSetMerchantStoresReferenceAndSession(): void
    {
        $result = $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        self::assertSame($this->vinti4, $result);

        $request = $this->vinti4->getRequest();

        self::assertSame(
            'R12345678901234',
            $request['merchantRef']
        );

        self::assertSame(
            'S12345678901234',
            $request['merchantSession']
        );
    }

    public function testSetMerchantGeneratesDefaultSession(): void
    {
        $this->vinti4->setMerchant('R12345678901234');

        $request = $this->vinti4->getRequest();

        self::assertArrayHasKey('merchantSession', $request);
        self::assertSame(15, strlen($request['merchantSession']));
        self::assertSame('S', $request['merchantSession'][0]);
    }

    // =========================================================
    // Prepare transactions
    // =========================================================

    public function testPreparePurchase(): void
    {
        $result = $this->vinti4->preparePurchase(
            1500,
            $this->billing()
        );

        self::assertSame($this->vinti4, $result);

        $request = $this->vinti4->getRequest();

        self::assertSame('1', $request['transactionCode']);
        self::assertSame(1500, $request['amount']);
        self::assertSame('CVE', $request['currency']);
        self::assertSame(
            'cliente@example.com',
            $request['billing']['email']
        );
    }

    public function testPreparePurchaseWithoutBilling(): void
    {
        $this->vinti4->preparePurchase(1500);

        $request = $this->vinti4->getRequest();

        self::assertSame('1', $request['transactionCode']);
        self::assertSame([], $request['billing']);
    }

    public function testPreparePurchaseWithDifferentCurrency(): void
    {
        $this->vinti4->preparePurchase(
            1500,
            [],
            'USD'
        );

        $request = $this->vinti4->getRequest();

        self::assertSame('USD', $request['currency']);
    }

    public function testPrepareServicePayment(): void
    {
        $result = $this->vinti4->prepareServicePayment(
            2500,
            123,
            '456789'
        );

        self::assertSame($this->vinti4, $result);

        $request = $this->vinti4->getRequest();

        self::assertSame('2', $request['transactionCode']);
        self::assertSame(2500, $request['amount']);
        self::assertSame(123, $request['entityCode']);
        self::assertSame('456789', $request['referenceNumber']);
    }

    public function testPrepareRecharge(): void
    {
        $result = $this->vinti4->prepareRecharge(
            500,
            220,
            '990123456'
        );

        self::assertSame($this->vinti4, $result);

        $request = $this->vinti4->getRequest();

        self::assertSame('3', $request['transactionCode']);
        self::assertSame(500, $request['amount']);
        self::assertSame(220, $request['entityCode']);
        self::assertSame(
            '990123456',
            $request['referenceNumber']
        );
    }

    public function testPrepareRefund(): void
    {
        $result = $this->vinti4->prepareRefund(
            1500,
            'TID987',
            '2401'
        );

        self::assertSame($this->vinti4, $result);

        $request = $this->vinti4->getRequest();

        self::assertSame('4', $request['transactionCode']);
        self::assertSame(1500, $request['amount']);
        self::assertSame('TID987', $request['transactionID']);
        self::assertSame('2401', $request['clearingPeriod']);
    }

    // =========================================================
    // Reusing the instance
    // =========================================================

    public function testItCanPrepareMoreThanOneTransaction(): void
    {
        $this->vinti4->preparePurchase(
            100,
            $this->billing()
        );

        $this->vinti4->prepareRecharge(
            500,
            220,
            '123456789'
        );

        $request = $this->vinti4->getRequest();

        self::assertSame('3', $request['transactionCode']);
        self::assertSame(500, $request['amount']);

        self::assertArrayNotHasKey('billing', $request);
    }

    public function testMerchantDataSurvivesNewTransaction(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->preparePurchase(
            100,
            $this->billing()
        );

        $this->vinti4->prepareRecharge(
            500,
            220,
            '123456789'
        );

        $request = $this->vinti4->getRequest();

        self::assertSame(
            'R12345678901234',
            $request['merchantRef']
        );

        self::assertSame(
            'S12345678901234',
            $request['merchantSession']
        );
    }

    // =========================================================
    // Payment form
    // =========================================================

    public function testCreatePaymentFormRequiresPreparedTransaction(): void
    {
        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'Nenhum pagamento preparado.'
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    public function testCreatePurchasePaymentForm(): void
    {
        $this->prepareValidPurchase();

        $html = $this->vinti4->createPaymentForm(
            'https://example.com/callback',
            'pt'
        );

        self::assertStringContainsString('<form', $html);
        self::assertStringContainsString(
            'method="post"',
            $html
        );
        self::assertStringContainsString(
            'name="transactionCode" value="1"',
            $html
        );
        self::assertStringContainsString(
            'name="amount" value="1500"',
            $html
        );
        self::assertStringContainsString(
            'name="currency" value="132"',
            $html
        );
        self::assertStringContainsString(
            'name="purchaseRequest"',
            $html
        );
        self::assertStringContainsString(
            'name="fingerprint"',
            $html
        );
        self::assertStringContainsString(
            'Processando...',
            $html
        );
    }

    public function testCreatePaymentFormInEnglish(): void
    {
        $this->prepareValidPurchase();

        $html = $this->vinti4->createPaymentForm(
            'https://example.com/callback',
            'en'
        );

        self::assertStringContainsString(
            'Processing...',
            $html
        );
    }

    public function testPaymentFormEscapesResponseUrl(): void
    {
        $this->prepareValidPurchase();

        $html = $this->vinti4->createPaymentForm(
            'https://example.com/callback?foo=1&bar=2'
        );

        self::assertStringContainsString(
            'https://example.com/callback?foo=1&amp;bar=2',
            $html
        );
    }

    public function testCreateServicePaymentForm(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->prepareServicePayment(
            2500,
            123,
            '456789'
        );

        $html = $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );

        self::assertStringContainsString(
            'name="transactionCode" value="2"',
            $html
        );
        self::assertStringContainsString(
            'name="entityCode" value="123"',
            $html
        );
        self::assertStringContainsString(
            'name="referenceNumber" value="456789"',
            $html
        );
    }

    public function testCreateRechargePaymentForm(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->prepareRecharge(
            500,
            220,
            '990123456'
        );

        $html = $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );

        self::assertStringContainsString(
            'name="transactionCode" value="3"',
            $html
        );
    }

    public function testCreateRefundPaymentForm(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->prepareRefund(
            1500,
            'TID987',
            '2401'
        );

        $html = $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );

        self::assertStringContainsString(
            'name="transactionCode" value="4"',
            $html
        );
        self::assertStringContainsString(
            'name="reversal" value="R"',
            $html
        );
        self::assertStringContainsString(
            'name="transactionID" value="TID987"',
            $html
        );
        self::assertStringContainsString(
            'name="clearingPeriod" value="2401"',
            $html
        );
    }

    // =========================================================
    // Request validation
    // =========================================================

    public function testRejectsZeroAmount(): void
    {
        $this->vinti4->preparePurchase(
            0,
            $this->billing()
        );

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'Amount deve ser um inteiro positivo'
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    public function testRejectsDecimalRequestAmount(): void
    {
        $this->vinti4->preparePurchase(
            '10.50',
            $this->billing()
        );

        $this->expectException(Vinti4Exception::class);

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    public function testRejectsInvalidCurrency(): void
    {
        $this->vinti4->preparePurchase(
            100,
            [],
            'INVALID'
        );

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'Moeda inválida'
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    public function testAcceptsNumericCurrency(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->preparePurchase(
            100,
            [],
            '840'
        );

        $html = $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );

        self::assertStringContainsString(
            'name="currency" value="840"',
            $html
        );
    }

    public function testRejectsInvalidCallbackUrl(): void
    {
        $this->prepareValidPurchase();

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'UrlMerchantResponse deve ser uma URL válida.'
        );

        $this->vinti4->createPaymentForm(
            'invalid-url'
        );
    }

    public function testRejectsInvalidLanguage(): void
    {
        $this->prepareValidPurchase();

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            "LanguageMessages deve ser 'pt', 'en' ou 'fr'."
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback',
            'de'
        );
    }

    public function testRejectsInvalidMerchantReferenceLength(): void
    {
        $this->vinti4->setMerchant(
            'SHORT',
            'S12345678901234'
        );

        $this->vinti4->preparePurchase(
            100,
            []
        );

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'MerchantRef é obrigatório e deve ter exatamente 15 caracteres.'
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    public function testRejectsInvalidMerchantSessionLength(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'SHORT'
        );

        $this->vinti4->preparePurchase(
            100,
            []
        );

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'MerchantSession é obrigatório e deve ter exatamente 15 caracteres.'
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    public function testServiceRequiresNumericEntity(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->prepareServicePayment(
            100,
            0,
            '12E4S'
        );

        $this->expectException(Vinti4Exception::class);

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    public function testServiceRejectsReferenceLongerThanNineDigits(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->prepareServicePayment(
            100,
            123,
            '1234567890'
        );

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'ReferenceNumber é obrigatório e deve ter até 9 dígitos.'
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    public function testRefundRejectsInvalidClearingPeriod(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->prepareRefund(
            100,
            'TID123',
            '12345'
        );

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'ClearingPeriod deve ter até 4 dígitos numéricos.'
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    public function testRefundRejectsInvalidTransactionId(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->prepareRefund(
            100,
            'TRANSACTION-TOO-LONG',
            '2401'
        );

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'TransactionID deve ter até 8 caracteres alfanuméricos.'
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    // =========================================================
    // Billing
    // =========================================================

    public function testPurchaseRequestContainsNormalizedBilling(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->preparePurchase(1500, [
            'email' => 'cliente@example.com',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Rua Cidade Nova',
            'postalCode' => '7600',
            'phone' => '+23899123456',
            'addressMatchesShipping' => true,
            'suspicious' => true,
        ]);

        $html = $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );

        $purchaseRequest = $this->hiddenField(
            $html,
            'purchaseRequest'
        );

        $billing = json_decode(
            base64_decode($purchaseRequest),
            true
        );

        self::assertSame(
            'cliente@example.com',
            $billing['email']
        );
        self::assertSame('132', $billing['billAddrCountry']);
        self::assertSame('Praia', $billing['billAddrCity']);
        self::assertSame(
            'Rua Cidade Nova',
            $billing['billAddrLine1']
        );
        self::assertSame('7600', $billing['billAddrPostCode']);
        self::assertSame('Y', $billing['addrMatch']);

        self::assertSame(
            '02',
            $billing['acctInfo']['suspiciousAccActivity']
        );

        self::assertSame(
            '23899123456',
            $billing['mobilePhone']['subscriber']
        );
    }

    public function testBillingAcceptsStructuredPhone(): void
    {
        $billing = $this->billing();

        $billing['mobilePhone'] = [
            'cc' => '+238',
            'subscriber' => '991-23-45',
        ];

        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->preparePurchase(100, $billing);

        $html = $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );

        $purchaseRequest = $this->hiddenField(
            $html,
            'purchaseRequest'
        );

        $decoded = json_decode(
            base64_decode($purchaseRequest),
            true
        );

        self::assertSame(
            [
                'cc' => '238',
                'subscriber' => '9912345',
            ],
            $decoded['mobilePhone']
        );
    }

    public function testBillingRejectsMissingRequiredFields(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->preparePurchase(100, [
            'email' => 'cliente@example.com',
        ]);

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'Campos obrigatórios ausentes em billing:'
        );

        $this->vinti4->createPaymentForm(
            'https://example.com/callback'
        );
    }

    // =========================================================
    // Response - ordinary errors
    // =========================================================

    public function testProcessResponseRejectsEmptyResponse(): void
    {
        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'A resposta da SISP está vazia.'
        );

        $this->vinti4->processResponse([]);
    }

    public function testProcessResponseReturnsGenericError(): void
    {
        $response = $this->vinti4->processResponse([
            'messageType' => '6',
        ]);

        self::assertSame('ERROR', $response['status']);
        self::assertFalse($response['success']);
        self::assertSame(
            'Transação falhou.',
            $response['message']
        );
        self::assertNull($response['operation']);
    }

    public function testProcessResponseUsesAdditionalErrorMessage(): void
    {
        $response = $this->vinti4->processResponse([
            'messageType' => '6',
            'merchantRespAdditionalErrorMessage' =>
                'Cartão recusado',
        ]);

        self::assertSame('ERROR', $response['status']);
        self::assertSame(
            'Cartão recusado',
            $response['message']
        );
    }

    public function testProcessResponseUsesErrorDetail(): void
    {
        $response = $this->vinti4->processResponse([
            'messageType' => '6',
            'merchantRespErrorDetail' => 'Erro detalhado',
        ]);

        self::assertSame(
            'Erro detalhado',
            $response['message']
        );
        self::assertSame(
            'Erro detalhado',
            $response['detail']
        );
    }

    public function testProcessResponseUsesErrorDescription(): void
    {
        $response = $this->vinti4->processResponse([
            'messageType' => '6',
            'merchantRespErrorDescription' =>
                'Operação recusada',
        ]);

        self::assertSame(
            'Operação recusada',
            $response['message']
        );
    }

    // =========================================================
    // Response - cancellation
    // =========================================================

    public function testProcessResponseCancelled(): void
    {
        $response = $this->vinti4->processResponse([
            'UserCancelled' => 'true',
        ]);

        self::assertSame(
            'CANCELLED',
            $response['status']
        );
        self::assertFalse($response['success']);
        self::assertSame(
            'Utilizador cancelou a transação.',
            $response['message']
        );
    }

    // =========================================================
    // Response - invalid fingerprint
    // =========================================================

    public function testProcessResponseInvalidFingerprint(): void
    {
        $response = $this->vinti4->processResponse([
            'messageType' => '8',
            'merchantRespPurchaseAmount' => '1000',
            'merchantRespMerchantRef' => 'R12345678901234',
            'merchantRespMerchantSession' => 'S12345678901234',
            'merchantRespTid' => 'TID123',
            'merchantResp' => 'C',
            'resultFingerPrint' => 'INVALID',
        ]);

        self::assertSame(
            'INVALID_FINGERPRINT',
            $response['status']
        );

        self::assertFalse($response['success']);

        self::assertSame(
            'INVALID',
            $response['debug']['received']
        );

        self::assertNotEmpty(
            $response['debug']['calculated']
        );
    }

    // =========================================================
    // Response - valid fingerprints
    // =========================================================

    public function testProcessSuccessfulPurchaseResponse(): void
    {
        $postData = $this->signedResponse([
            'messageType' => '8',
            'merchantResp' => 'C',
            'merchantRespPurchaseAmount' => '1500',
            'merchantRespTid' => 'TID123',
            'merchantRespMerchantRef' => 'R12345678901234',
            'merchantRespMerchantSession' => 'S12345678901234',
            'merchantRespPan' => '1234567890123456',
        ]);

        $response = $this->vinti4->processResponse($postData);

        self::assertSame('SUCCESS', $response['status']);
        self::assertTrue($response['success']);
        self::assertSame('purchase', $response['operation']);
        self::assertSame(
            'Transação válida.',
            $response['message']
        );

        self::assertSame(
            '•••• 3456',
            $response['data']['merchantRespPan']
        );

        self::assertSame([], $response['debug']);
    }

    public function testProcessSuccessfulRefundResponse(): void
    {
        $postData = $this->signedResponse([
            'messageType' => '10',
            'merchantRespPurchaseAmount' => '1500',
            'merchantRespTid' => 'REF123',
        ]);

        $response = $this->vinti4->processResponse($postData);

        self::assertSame('SUCCESS', $response['status']);
        self::assertTrue($response['success']);
        self::assertSame('refund', $response['operation']);

        self::assertSame(
            'Reembolso processado com sucesso.',
            $response['message']
        );
    }

    public function testServiceResponseOperationIsDetected(): void
    {
        $postData = $this->signedResponse([
            'messageType' => 'P',
            'merchantResp' => 'C',
            'merchantRespPurchaseAmount' => '1000',
        ]);

        $response = $this->vinti4->processResponse($postData);

        self::assertSame(
            'service_payment',
            $response['operation']
        );
    }

    public function testRechargeResponseOperationIsDetected(): void
    {
        $postData = $this->signedResponse([
            'messageType' => 'M',
            'merchantResp' => 'C',
            'merchantRespPurchaseAmount' => '500',
        ]);

        $response = $this->vinti4->processResponse($postData);

        self::assertSame(
            'recharge',
            $response['operation']
        );
    }

    // =========================================================
    // PAN
    // =========================================================

    public function testResponseMasksPan(): void
    {
        $response = $this->vinti4->processResponse([
            'messageType' => '6',
            'merchantRespPan' => '1234-5678-9012-3456',
        ]);

        self::assertSame(
            '•••• 3456',
            $response['data']['merchantRespPan']
        );
    }

    public function testResponseConvertsEmptyPanToNull(): void
    {
        $response = $this->vinti4->processResponse([
            'messageType' => '6',
            'merchantRespPan' => '0',
        ]);

        self::assertNull(
            $response['data']['merchantRespPan']
        );
    }

    // =========================================================
    // DCC
    // =========================================================

    public function testResponseWithoutDcc(): void
    {
        $response = $this->vinti4->processResponse([
            'messageType' => '6',
        ]);

        self::assertSame(
            ['enabled' => false],
            $response['dcc']
        );
    }

    public function testProcessResponseDcc(): void
    {
        $dcc = [
            'dcc' => 'Y',
            'dccAmount' => '10.58',
            'dccCurrency' => 'USD',
            'dccMarkup' => '0.31',
            'dccRate' => '92.65882',
        ];

        $response = $this->vinti4->processResponse([
            'messageType' => '6',
            'merchantRespDCCData' => json_encode($dcc),
        ]);

        self::assertTrue($response['dcc']['enabled']);
        self::assertSame(
            '10.58',
            $response['dcc']['amount']
        );
        self::assertSame(
            'USD',
            $response['dcc']['currency']
        );
        self::assertSame(
            '0.31',
            $response['dcc']['markup']
        );
        self::assertSame(
            '92.65882',
            $response['dcc']['rate']
        );
    }

    public function testMalformedDccIsReported(): void
    {
        $response = $this->vinti4->processResponse([
            'messageType' => '6',
            'merchantRespDCCData' => '{invalid-json',
        ]);

        self::assertFalse($response['dcc']['enabled']);

        self::assertSame(
            'DCC inválido ou mal formatado.',
            $response['dcc']['error']
        );
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function prepareValidPurchase(): void
    {
        $this->vinti4->setMerchant(
            'R12345678901234',
            'S12345678901234'
        );

        $this->vinti4->preparePurchase(
            1500,
            $this->billing()
        );
    }

    private function billing(): array
    {
        return [
            'email' => 'cliente@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Cidade Nova',
            'billAddrPostCode' => '7600',
        ];
    }

    /**
     * Generate a response fingerprint using the SDK itself.
     *
     * This allows testing the public response-processing path with
     * a cryptographically valid SISP response.
     */
    private function signedResponse(array $data): array
    {
        $method = new ReflectionMethod(
            Vinti4Net::class,
            'fingerprintResponse'
        );

        $method->setAccessible(true);

        $data['resultFingerPrint'] = $method->invoke(
            $this->vinti4,
            $data
        );

        return $data;
    }

    /**
     * Extract a hidden field value from the generated form.
     */
    private function hiddenField(
        string $html,
        string $name
    ): string {
        $pattern = '/name="' . preg_quote($name, '/') .
            '" value="([^"]*)"/';

        self::assertSame(
            1,
            preg_match($pattern, $html, $matches),
            "Hidden field {$name} was not found."
        );

        return html_entity_decode(
            $matches[1],
            ENT_QUOTES,
            'UTF-8'
        );
    }
}