<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Tests;

use GuzzleHttp\Psr7\Response as HttpResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\IDBank\Gateway;
use Omnipay\Omnipay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GatewayTest extends TestCase
{
    private array $calls = [];

    private function gateway(array|string $body = [], int $status = 200): Gateway
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willReturnCallback(function ($method, $url, $headers, $data) use ($body, $status) {
            parse_str($data, $parameters);
            $this->calls[] = compact('method', 'url', 'headers', 'parameters');
            return new HttpResponse($status, ['Content-Type' => 'application/json'], is_array($body) ? json_encode($body) : $body);
        });
        $gateway = Omnipay::create('IDBank', $client);
        $gateway->initialize(['userName' => 'merchant', 'password' => 'p&?=secret']);
        return $gateway;
    }

    private function payment(): array
    {
        return ['transactionId' => 'ORDER-42', 'amount' => '10.25', 'currency' => 'AMD', 'returnUrl' => 'https://shop.example/return'];
    }

    private function orderStatus(int $state = 2): array
    {
        return ['errorCode' => '0', 'orderStatus' => $state, 'orderNumber' => 'ORDER-42', 'amount' => 1025, 'currency' => '051', 'actionCode' => 0];
    }

    #[DataProvider('registrationMethods')]
    public function testRegistrationIsARedirectAndUsesEncodedPost(string $method, string $endpoint): void
    {
        $gateway = $this->gateway(['orderId' => 'bank-order', 'formUrl' => 'https://ipayproxy.idp.am/payment/form']);
        $response = $gateway->$method($this->payment())->send();
        self::assertSame('IDBank', $gateway->getShortName());
        self::assertFalse($response->isSuccessful());
        self::assertTrue($response->isRedirect());
        self::assertSame('bank-order', $response->getTransactionReference());
        self::assertSame('ORDER-42', $response->getTransactionId());
        self::assertSame('https://ipayproxy.idp.am/payment/form', $response->getRedirectUrl());
        self::assertSame('GET', $response->getRedirectMethod());
        $call = $this->calls[0];
        self::assertSame('POST', $call['method']);
        self::assertSame('https://ipayproxy.idp.am/payment/rest/' . $endpoint, $call['url']);
        self::assertSame('application/x-www-form-urlencoded', $call['headers']['Content-Type']);
        self::assertSame('p&?=secret', $call['parameters']['password']);
        self::assertSame('1025', $call['parameters']['amount']);
        self::assertSame('051', $call['parameters']['currency']);
        self::assertSame('ORDER-42', $call['parameters']['orderNumber']);
        self::assertArrayNotHasKey('operation', $call['parameters']);
    }

    public static function registrationMethods(): array
    {
        return [['purchase', 'register.do'], ['authorize', 'registerPreAuth.do']];
    }

    public function testTestModeRequiresAnExplicitEndpointAndNeverFallsBackToLive(): void
    {
        $gateway = $this->gateway();
        $gateway->setTestMode(true);
        $this->expectException(InvalidRequestException::class);
        try {
            $gateway->purchase($this->payment())->send();
        } finally {
            self::assertSame([], $this->calls);
        }
    }

    public function testEndpointsCanBeOverridden(): void
    {
        $gateway = $this->gateway(['orderId' => 'bank-order', 'formUrl' => 'https://sandbox.example/form']);
        $gateway->setTestMode(true)->setTestEndpoint('https://sandbox.example/rest');
        $gateway->purchase($this->payment())->send();
        self::assertSame('https://sandbox.example/rest/register.do', $this->calls[0]['url']);
        $gateway->setTestMode(false)->setLiveEndpoint('https://bank.example/rest/');
        $gateway->purchase($this->payment())->send();
        self::assertSame('https://bank.example/rest/register.do', $this->calls[1]['url']);
    }

    #[DataProvider('invalidEndpoints')]
    public function testEndpointsCannotExposeCredentials(string $endpoint): void
    {
        $gateway = $this->gateway();
        $gateway->setLiveEndpoint($endpoint);
        $this->expectException(InvalidRequestException::class);
        $gateway->purchase($this->payment())->send();
    }

    public static function invalidEndpoints(): array
    {
        return [['http://bank.example/rest/'], ['https://username@bank.example/rest/'], ['https://username:password@bank.example/rest/'], ['https://bank.example/rest/?password=secret']];
    }

    #[DataProvider('currencyAmounts')]
    public function testAmountsUseTheCurrencyExponent(string $currency, string $amount, string $minor, string $numeric): void
    {
        $gateway = $this->gateway();
        $data = $gateway->purchase(array_replace($this->payment(), compact('currency', 'amount')))->getData();
        self::assertSame($minor, $data['amount']);
        self::assertSame($numeric, $data['currency']);
    }

    public static function currencyAmounts(): array
    {
        return [['AMD', '10.25', '1025', '051'], ['USD', '0.01', '1', '840'], ['JPY', '12', '12', '392'], ['KWD', '1.234', '1234', '414']];
    }

    #[DataProvider('invalidAmounts')]
    public function testInvalidAmountsAreRejectedBeforeSending(string $amount): void
    {
        $gateway = $this->gateway();
        $this->expectException(InvalidRequestException::class);
        try {
            $gateway->purchase(array_replace($this->payment(), compact('amount')))->send();
        } finally {
            self::assertSame([], $this->calls);
        }
    }

    public static function invalidAmounts(): array
    {
        return [['0'], ['-1'], ['1.001'], ['1e3'], ['NaN'], ['10000000000']];
    }

    #[DataProvider('statusCases')]
    public function testCompletionDistinguishesPaidHeldPendingAndFailed(string $operation, int $state, bool $success, bool $pending, bool $cancelled): void
    {
        $gateway = $this->gateway($this->orderStatus($state));
        $response = $gateway->$operation($this->payment() + ['transactionReference' => 'bank-order'])->send();
        self::assertSame($success, $response->isSuccessful());
        self::assertSame($pending, $response->isPending());
        self::assertSame($cancelled, $response->isCancelled());
        self::assertFalse($response->isRedirect());
        self::assertSame('bank-order', $response->getTransactionReference());
        self::assertSame('ORDER-42', $response->getTransactionId());
        self::assertSame('https://ipayproxy.idp.am/payment/rest/getOrderStatusExtended.do', $this->calls[0]['url']);
        self::assertArrayNotHasKey('amount', $this->calls[0]['parameters']);
    }

    public static function statusCases(): array
    {
        return [
            ['completePurchase', 2, true, false, false],
            ['completePurchase', 1, false, true, false],
            ['completeAuthorize', 1, true, false, false],
            ['completeAuthorize', 2, false, false, false],
            ['completePurchase', 0, false, true, false],
            ['completePurchase', 3, false, false, true],
            ['completePurchase', 4, false, false, false],
            ['completePurchase', 6, false, false, false],
            ['completePurchase', -1, false, false, false],
            ['completePurchase', 99, false, false, false],
        ];
    }

    #[DataProvider('mismatchedOrders')]
    public function testCompletionRejectsDifferentOrderAmountOrCurrency(array $changes): void
    {
        $gateway = $this->gateway(array_replace($this->orderStatus(), $changes));
        $this->expectException(InvalidResponseException::class);
        $gateway->completePurchase($this->payment() + ['transactionReference' => 'expected-order'])->send();
    }

    public static function mismatchedOrders(): array
    {
        return [[['orderNumber' => 'OTHER']], [['amount' => 1000]], [['currency' => '840']], [['orderId' => 'different-order']]];
    }

    public function testCompletionRequiresExpectedPaymentDetails(): void
    {
        $gateway = $this->gateway($this->orderStatus());
        $this->expectException(InvalidRequestException::class);
        $gateway->completePurchase(['transactionReference' => 'bank-order'])->send();
    }

    public function testFetchCanReconcileAnOrderByMerchantNumber(): void
    {
        $gateway = $this->gateway($this->orderStatus() + ['attributes' => [['name' => 'mdOrder', 'value' => 'bank-order']]]);
        $response = $gateway->fetchTransaction(['transactionId' => 'ORDER-42'])->send();
        self::assertTrue($response->isSuccessful());
        self::assertSame('bank-order', $response->getTransactionReference());
        self::assertSame('ORDER-42', $this->calls[0]['parameters']['orderNumber']);
        self::assertArrayNotHasKey('orderId', $this->calls[0]['parameters']);
    }

    #[DataProvider('capturedAmounts')]
    public function testCompletionChecksTheActualNetCapturedAmount(string $deposited, string $refunded, bool $success): void
    {
        $gateway = $this->gateway($this->orderStatus() + [
            'paymentAmountInfo' => ['depositedAmount' => $deposited, 'refundedAmount' => $refunded],
        ]);
        $response = $gateway->completePurchase($this->payment() + ['transactionReference' => 'bank-order'])->send();
        self::assertSame($success, $response->isSuccessful());
    }

    public static function capturedAmounts(): array
    {
        return [['1025', '0', true], ['500', '0', false], ['1025', '500', false], ['1025', '1025', false]];
    }

    public function testCompletionChecksTheBankReferenceInAttributes(): void
    {
        $gateway = $this->gateway($this->orderStatus() + ['attributes' => [['name' => 'mdOrder', 'value' => 'OTHER']]]);
        $this->expectException(InvalidResponseException::class);
        $gateway->completePurchase($this->payment() + ['transactionReference' => 'bank-order'])->send();
    }

    public function testStandardOmnipayAmountRepresentationsWork(): void
    {
        $gateway = $this->gateway();
        $request = $gateway->purchase($this->payment());
        $request->setAmountInteger(1025);
        self::assertSame('1025', $request->getData()['amount']);
        $request->setMoney(new \Money\Money(1025, new \Money\Currency('AMD')));
        self::assertSame('1025', $request->getData()['amount']);
    }

    #[DataProvider('moneyOperations')]
    public function testMoneyOperationsUseGatewayReferenceAndExplicitAmount(string $operation, string $endpoint): void
    {
        $gateway = $this->gateway(['errorCode' => '0', 'errorMessage' => 'Success']);
        $response = $gateway->$operation(['transactionReference' => 'bank-order', 'amount' => '2.50', 'currency' => 'AMD'])->send();
        self::assertTrue($response->isSuccessful());
        self::assertSame('bank-order', $response->getTransactionReference());
        self::assertSame('https://ipayproxy.idp.am/payment/rest/' . $endpoint, $this->calls[0]['url']);
        self::assertSame('bank-order', $this->calls[0]['parameters']['orderId']);
        if ($operation !== 'void') {
            self::assertSame('250', $this->calls[0]['parameters']['amount']);
        } else {
            self::assertArrayNotHasKey('amount', $this->calls[0]['parameters']);
        }
    }

    public static function moneyOperations(): array
    {
        return [['capture', 'deposit.do'], ['refund', 'refund.do'], ['void', 'reverse.do']];
    }

    #[DataProvider('explicitAmounts')]
    public function testCaptureAndRefundCannotSilentlyUseTheFullAmount(string $operation, ?string $amount): void
    {
        $gateway = $this->gateway();
        $this->expectException(InvalidRequestException::class);
        $gateway->$operation(['transactionReference' => 'bank-order', 'amount' => $amount])->send();
    }

    public static function explicitAmounts(): array
    {
        return [['capture', null], ['capture', '0'], ['refund', null], ['refund', '0']];
    }

    public function testApiErrorsAreNotSuccessfulPayments(): void
    {
        $gateway = $this->gateway(['errorCode' => '5', 'errorMessage' => 'Access denied']);
        $response = $gateway->purchase($this->payment())->send();
        self::assertFalse($response->isSuccessful());
        self::assertFalse($response->isRedirect());
        self::assertSame('5', $response->getCode());
        self::assertSame('Access denied', $response->getMessage());
    }

    #[DataProvider('malformedResponses')]
    public function testMalformedSuccessResponsesAreRejected(string $operation, array|string $body): void
    {
        $gateway = $this->gateway($body);
        $this->expectException(InvalidResponseException::class);
        $gateway->$operation($this->payment() + ['transactionReference' => 'bank-order'])->send();
    }

    public static function malformedResponses(): array
    {
        return [
            ['purchase', 'not json'], ['purchase', 'null'], ['purchase', '[]'], ['purchase', '{}'],
            ['purchase', ['errorCode' => 0, 'orderId' => 'x']],
            ['purchase', ['orderId' => 'x', 'formUrl' => 'javascript:alert(1)']],
            ['capture', '{}'], ['capture', ['errorCode' => 'garbage']],
            ['capture', ['errorCode' => true]],
            ['completePurchase', ['errorCode' => 0]],
            ['completePurchase', ['orderStatus' => true, 'orderNumber' => 'ORDER-42', 'amount' => 1025, 'currency' => '051']],
        ];
    }

    public function testHttpErrorsDoNotLeakCredentials(): void
    {
        $gateway = $this->gateway('p&?=secret', 502);
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('HTTP 502');
        $gateway->purchase($this->payment())->send();
    }

    public function testTransportFailuresAreNotRetriedOrExposed(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('request')->willThrowException(new \RuntimeException('password=p&?=secret'));
        $gateway = new Gateway($client);
        $gateway->initialize(['userName' => 'merchant', 'password' => 'p&?=secret']);
        try {
            $gateway->purchase($this->payment())->send();
            self::fail('Expected a transport exception');
        } catch (InvalidResponseException $exception) {
            self::assertStringNotContainsString('secret', (string) $exception);
            self::assertStringContainsString('unknown', $exception->getMessage());
        }
    }
}
