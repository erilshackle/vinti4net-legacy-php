<?php

/**
 * PHP 5.6 compatibility tests.
 *
 * This file intentionally does not use PHPUnit or Composer.
 *
 * Run:
 *   php tests/php56.php
 */

require_once __DIR__ . '/../src/Vinti4NetLegacy.php';

$passed = 0;
$failed = 0;

function test($description, $callback)
{
    global $passed, $failed;

    try {
        $result = call_user_func($callback);

        if ($result !== true) {
            throw new Exception('Assertion failed');
        }

        $passed++;
        echo '[OK]   ' . $description . PHP_EOL;
    } catch (Exception $e) {
        $failed++;
        echo '[FAIL] ' . $description . PHP_EOL;
        echo '       ' . $e->getMessage() . PHP_EOL;
    }
}

function assertTrue($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }

    return true;
}

function newVinti4()
{
    return new Vinti4Net(
        '123456789',
        'TEST_AUTH_CODE'
    );
}

/*
|--------------------------------------------------------------------------
| Basic
|--------------------------------------------------------------------------
*/

test('Vinti4Net can be instantiated', function () {
    $vinti4 = newVinti4();

    return assertTrue(
        $vinti4 instanceof Vinti4Net,
        'Expected an instance of Vinti4Net.'
    );
});

test('Merchant reference contains exactly 15 characters', function () {
    $reference = Vinti4Net::generateMerchantRef();

    return assertTrue(
        strlen($reference) === 15,
        'Expected merchant reference length to be 15.'
    );
});

test('Generated merchant references start with R', function () {
    $reference = Vinti4Net::generateMerchantRef();

    return assertTrue(
        substr($reference, 0, 1) === 'R',
        'Expected merchant reference to start with R.'
    );
});

/*
|--------------------------------------------------------------------------
| Purchase
|--------------------------------------------------------------------------
*/

test('Purchase can be prepared', function () {
    $vinti4 = newVinti4();

    $vinti4->preparePurchase(1500);

    $request = $vinti4->getRequest();

    return assertTrue(
        is_array($request) && !empty($request),
        'Expected a non-empty purchase request.'
    );
});

/*
|--------------------------------------------------------------------------
| Service Payment
|--------------------------------------------------------------------------
*/

test('Service payment can be prepared', function () {
    $vinti4 = newVinti4();

    $vinti4->prepareServicePayment(
        1500,
        123,
        '456789'
    );

    $request = $vinti4->getRequest();

    return assertTrue(
        is_array($request) && !empty($request),
        'Expected a non-empty service payment request.'
    );
});

/*
|--------------------------------------------------------------------------
| Recharge
|--------------------------------------------------------------------------
*/

test('Recharge can be prepared', function () {
    $vinti4 = newVinti4();

    $vinti4->prepareRecharge(
        500,
        123,
        '9912345'
    );

    $request = $vinti4->getRequest();

    return assertTrue(
        is_array($request) && !empty($request),
        'Expected a non-empty recharge request.'
    );
});

/*
|--------------------------------------------------------------------------
| Refund
|--------------------------------------------------------------------------
*/

test('Refund can be prepared', function () {
    $vinti4 = newVinti4();

    $vinti4->prepareRefund(
        1500,
        '123456789',
        '2401'
    );

    $request = $vinti4->getRequest();

    return assertTrue(
        is_array($request) && !empty($request),
        'Expected a non-empty refund request.'
    );
});

/*
|--------------------------------------------------------------------------
| Reuse
|--------------------------------------------------------------------------
*/

test('Instance can be reused for multiple transactions', function () {
    $vinti4 = newVinti4();

    $vinti4->preparePurchase(1000);
    $first = $vinti4->getRequest();

    $vinti4->preparePurchase(2000);
    $second = $vinti4->getRequest();

    return assertTrue(
        $first !== $second,
        'Expected the second transaction to replace the first one.'
    );
});

/*
|--------------------------------------------------------------------------
| Invalid Request
|--------------------------------------------------------------------------
*/

test('Invalid amount is rejected', function () {
    $vinti4 = newVinti4();

    try {
        $vinti4
            ->preparePurchase(0)
            ->createPaymentForm('https://example.com/callback');
    } catch (Exception $e) {
        return true;
    }

    throw new Exception('Expected an exception for amount 0.');
});

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo '----------------------------------------' . PHP_EOL;
echo 'PHP ' . PHP_VERSION . PHP_EOL;
echo 'Passed: ' . $passed . PHP_EOL;
echo 'Failed: ' . $failed . PHP_EOL;
echo '----------------------------------------' . PHP_EOL;

exit($failed > 0 ? 1 : 0);