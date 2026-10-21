<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Message;

use Money\Money;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\IDBank\Parameters;

class Request extends AbstractRequest
{
    use Parameters;

    private const METHODS = [
        'purchase' => 'register.do',
        'authorize' => 'registerPreAuth.do',
        'completePurchase' => 'getOrderStatusExtended.do',
        'completeAuthorize' => 'getOrderStatusExtended.do',
        'fetchTransaction' => 'getOrderStatusExtended.do',
        'capture' => 'deposit.do',
        'refund' => 'refund.do',
        'void' => 'reverse.do',
    ];

    public function getOperation(): string
    {
        return (string) $this->getParameter('operation');
    }

    public function setOperation($value): static
    {
        if (!isset(self::METHODS[$value])) {
            throw new InvalidRequestException('Unsupported IDBank operation.');
        }
        return $this->setParameter('operation', $value);
    }

    public function isRegistration(): bool
    {
        return in_array($this->getOperation(), ['purchase', 'authorize'], true);
    }

    public function isCompletion(): bool
    {
        return in_array($this->getOperation(), ['completePurchase', 'completeAuthorize'], true);
    }

    public function isStatusRequest(): bool
    {
        return $this->isCompletion() || $this->getOperation() === 'fetchTransaction';
    }

    public function getEndpoint(): string
    {
        $endpoint = $this->getTestMode() ? $this->getTestEndpoint() : $this->getLiveEndpoint();
        $parts = parse_url($endpoint);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidRequestException('Configure a valid HTTPS ' . ($this->getTestMode() ? 'testEndpoint' : 'liveEndpoint') . '.');
        }
        if (!isset(self::METHODS[$this->getOperation()])) {
            throw new InvalidRequestException('Unsupported IDBank operation.');
        }
        return rtrim($endpoint, '/') . '/' . self::METHODS[$this->getOperation()];
    }

    public function getData(): array
    {
        $this->validate('userName', 'password');
        $this->getEndpoint();
        $data = ['userName' => $this->getUserName(), 'password' => $this->getPassword()];
        if ($this->getLanguage() !== '') {
            if (!preg_match('/^[a-z]{2}$/iD', $this->getLanguage())) {
                throw new InvalidRequestException('language must be a two-letter ISO code.');
            }
            $data['language'] = $this->getLanguage();
        }

        if ($this->isRegistration()) {
            $this->validate('transactionId', 'returnUrl', 'amount', 'currency');
            $this->validateOrderNumber();
            $returnUrl = $this->getReturnUrl();
            if (!filter_var($returnUrl, FILTER_VALIDATE_URL)
                || !in_array(parse_url($returnUrl, PHP_URL_SCHEME), ['http', 'https'], true)
                || strlen($returnUrl) > 2000) {
                throw new InvalidRequestException('returnUrl must be an HTTP or HTTPS URL up to 2000 bytes.');
            }
            $data += [
                'orderNumber' => (string) $this->getTransactionId(),
                'amount' => $this->getMinorAmount(),
                'currency' => $this->getNumericCurrency(),
                'returnUrl' => $returnUrl,
            ];
            if ($this->getDescription() !== null && $this->getDescription() !== '') {
                if (mb_strlen($this->getDescription(), 'UTF-8') > 600) {
                    throw new InvalidRequestException('description must not exceed 600 characters.');
                }
                $data['description'] = $this->getDescription();
            }
        } else {
            if ($this->getOperation() === 'fetchTransaction' && !$this->getTransactionReference()) {
                $this->validate('transactionId');
                $this->validateOrderNumber();
                $data['orderNumber'] = (string) $this->getTransactionId();
            } else {
                $this->validate('transactionReference');
                $data['orderId'] = (string) $this->getTransactionReference();
            }
            if (in_array($this->getOperation(), ['capture', 'refund'], true)) {
                $data['amount'] = $this->getMinorAmount();
            }
            if ($this->isCompletion()) {
                $this->validate('transactionId', 'amount', 'currency');
                $this->getMinorAmount();
                $this->getNumericCurrency();
            }
        }
        return $data;
    }

    public function getMinorAmount(): string
    {
        $this->getNumericCurrency();
        $raw = $this->getParameter('amount');
        if ($raw instanceof Money) {
            if ($raw->getCurrency()->getCode() !== $this->getCurrency()) {
                throw new InvalidRequestException('Money currency must match the request currency.');
            }
            $amount = $raw->getAmount();
        } elseif (is_int($raw)) {
            // Omnipay setAmountInteger() stores minor units; setAmount() stores a decimal string.
            $amount = (string) $raw;
        } else {
            if (!is_string($raw) || !preg_match('/^\d+(?:\.\d+)?$/D', $raw)) {
                throw new InvalidRequestException('amount must be a positive decimal amount.');
            }
            [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
            $exponent = $this->getCurrencyDecimalPlaces();
            if (strlen($fraction) > $exponent) {
                throw new InvalidRequestException('amount has too many decimal places for the currency.');
            }
            $amount = ltrim($whole . str_pad($fraction, $exponent, '0'), '0');
        }
        if (!preg_match('/^\d+$/D', $amount) || $amount === '0' || strlen($amount) > 12) {
            throw new InvalidRequestException('amount must be between 1 and 999999999999 minor units.');
        }
        return $amount;
    }

    public function getNumericCurrency(): string
    {
        if (!is_string($this->getCurrency()) || !preg_match('/^[A-Z]{3}$/D', $this->getCurrency())) {
            throw new InvalidRequestException('currency must be an ISO 4217 alphabetic code.');
        }
        $numeric = $this->getCurrencyNumeric();
        if ($numeric === null) {
            throw new InvalidRequestException('Unknown currency.');
        }
        return str_pad($numeric, 3, '0', STR_PAD_LEFT);
    }

    public function sendData($data): Response
    {
        $endpoint = $this->getEndpoint();
        try {
            $httpResponse = $this->httpClient->request('POST', $endpoint, [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ], http_build_query($data, '', '&', PHP_QUERY_RFC3986));
        } catch (\Exception) {
            // Do not chain an HTTP exception that may include a credential-bearing request.
            throw new InvalidResponseException('IDBank transport failed; the transaction outcome is unknown. Check its status before retrying.');
        }
        $status = $httpResponse->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new InvalidResponseException('IDBank returned HTTP ' . $status . '. Check the transaction status before retrying.');
        }
        try {
            $body = json_decode((string) $httpResponse->getBody(), true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            throw new InvalidResponseException('IDBank returned invalid JSON.');
        }
        if (!is_array($body) || $body === [] || array_is_list($body)) {
            throw new InvalidResponseException('IDBank returned an invalid response object.');
        }
        $response = new Response($this, $body);
        $response->validate();
        return $this->response = $response;
    }

    private function validateOrderNumber(): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', (string) $this->getTransactionId())) {
            throw new InvalidRequestException('transactionId must contain 1 to 32 letters, digits, hyphens or underscores.');
        }
    }
}
