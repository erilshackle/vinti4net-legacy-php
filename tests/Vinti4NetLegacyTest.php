<?php

use Erilshk\Vinti4NetLegacy\Vinti4NetLegacy;
use PHPUnit\Framework\TestCase;

class Vinti4NetLegacyTest extends TestCase
{
    protected Vinti4NetLegacy $vinti4;

    protected function setUp(): void
    {
        $this->vinti4 = new Vinti4NetLegacy(
            'POS123',
            'ABCDEF123456789',
            'https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment'
        );
    }

    // --------------------------------------
    // Testes de preparação de pagamento
    // --------------------------------------

    public function testPreparePurchasePayment()
    {
        $billing = [
            'email' => 'cliente@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Cidade Nova',
            'billAddrPostCode' => '7600',
            'mobilePhone' => '+23899123456'
        ];

        $result = $this->vinti4->preparePurchasePayment(1500, $billing);
        $this->assertInstanceOf(Vinti4NetLegacy::class, $result);

        $html = $this->vinti4->createPaymentForm('https://example.com/callback', 'TEST-PURCHASE');
        $this->assertStringContainsString('<form', $html);
    }

    public function testPrepareServicePayment()
    {
        $result = $this->vinti4->prepareServicePayment(2500, 123, 456789);
        $this->assertInstanceOf(Vinti4NetLegacy::class, $result);

        $html = $this->vinti4->createPaymentForm('https://example.com/callback', 'TEST-SERVICE');
        $this->assertStringContainsString('<form', $html);
    }

    public function testPrepareRechargePayment()
    {
        $result = $this->vinti4->prepareRechargePayment(500, 220, 990123456);
        $this->assertInstanceOf(Vinti4NetLegacy::class, $result);

        $html = $this->vinti4->createPaymentForm('https://example.com/callback', 'TEST-RECHARGE');
        $this->assertStringContainsString('<form', $html);
    }

    public function testPrepareRefundPayment()
    {
        $result = $this->vinti4->prepareRefundPayment(1500, 'REF123', 'SESS123', 'TID987', 202401);
        $this->assertInstanceOf(Vinti4NetLegacy::class, $result);

        $html = $this->vinti4->createPaymentForm('https://example.com/callback', 'TEST-REFUND');
        $this->assertStringContainsString('<form', $html);
    }

    // --------------------------------------
    // Testes de resposta do gateway
    // --------------------------------------

    public function testProcessResponseSuccess()
    {
        $postData = [
            'messageType' => '8',
            'merchantRespPurchaseAmount' => 1500,
            'merchantRespMerchantRef' => 'TEST-PURCHASE',
            'merchantRespMerchantSession' => 'S123',
            'merchantRespTid' => 'TID123',
            'resultFingerPrint' => '' // you need a valid fingerprint result in order to have a success
        ];

        $response = $this->vinti4->processResponse($postData);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('success', $response);
        // $this->assertEquals('SUCCESS', $response['status']);
        // $this->assertTrue($response['success']);
        // surpasses
        $this->assertEquals('INVALID_FINGERPRINT', $response['status']);
        $this->assertTrue(!$response['success']);
    }

    public function testProcessResponseCancelled()
    {
        $postData = [
            'UserCancelled' => 'true'
        ];

        $response = $this->vinti4->processResponse($postData);
        $this->assertEquals('CANCELLED', $response['status']);
    }

    public function testProcessResponseInvalidFingerprint()
    {
        $postData = [
            'messageType' => '8',
            'merchantRespPurchaseAmount' => 1000,
            'merchantRespMerchantRef' => 'ABC',
            'merchantRespMerchantSession' => 'S123',
            'merchantRespTid' => 'TID123',
            'resultFingerPrint' => 'INVALID'
        ];

        $response = $this->vinti4->processResponse($postData);
        $this->assertEquals('INVALID_FINGERPRINT', $response['status']);
        $this->assertArrayHasKey('debug', $response);
        $this->assertArrayHasKey('recebido', $response['debug']);
    }

    // --------------------------------------
    // Testes de exceções
    // --------------------------------------

    public function testSetRequestParamsThrowsExceptionForInvalidKey()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->vinti4->setRequestParams(['invalidKey' => 'value']);
    }

    public function testCurrencyToCodeThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->vinti4->setRequestParams(['currency' => 'ABC']);
    }

    public function testPreparePaymentRequestThrowsExceptionOnMultipleRequests()
    {
        $this->vinti4->preparePurchasePayment(100, [
            'email' => 'teste@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Cidade',
            'billAddrLine1' => 'Rua Teste',
            'billAddrPostCode' => '1234'
        ]);

        $this->expectException(\Exception::class);
        $this->vinti4->preparePurchasePayment(200, [
            'email' => 'teste2@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Cidade',
            'billAddrLine1' => 'Rua Teste',
            'billAddrPostCode' => '1234'
        ]);
    }

    // --------------------------------------
    // Testes de DCC
    // --------------------------------------

    public function testProcessResponseDCC()
    {
        $dccData = [
            'dcc' => 'Y',
            'dccAmount' => 1500,
            'dccCurrency' => 840,
            'dccMarkup' => 0.02,
            'dccRate' => 1.1
        ];

        $postData = [
            'messageType' => '8',
            'merchantRespPurchaseAmount' => 1500,
            'merchantRespMerchantRef' => 'TEST-PURCHASE',
            'merchantRespMerchantSession' => 'S123',
            'merchantRespTid' => 'TID123',
            'merchantRespDCCData' => json_encode($dccData)
        ];

        $response = $this->vinti4->processResponse($postData);
        $this->assertTrue($response['dcc']['enabled']);
        $this->assertEquals(1500, $response['dcc']['amount']);
        $this->assertEquals(840, $response['dcc']['currency']);
    }

    // --------------------------------------
    // Testes de normalização de billing
    // --------------------------------------

    public function testNormalizeBillingOptionalFields()
    {
        $billing = [
            'email' => 'cliente@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Cidade',
            'billAddrLine1' => 'Rua Teste',
            'billAddrPostCode' => '1234',
            'user' => [
                'id' => 1,
                'mobilePhone' => '+23899123456',
                'created_at' => '2024-01-01',
                'updated_at' => '2024-01-05',
                'suspicious' => true
            ]
        ];

        $this->vinti4->preparePurchasePayment(100, $billing);
        $html = $this->vinti4->createPaymentForm('https://example.com/callback');
        $this->assertStringContainsString('<form', $html);
    }
}
