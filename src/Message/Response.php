<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Message;

use Money\Currency;
use Money\Money;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;

class Response extends AbstractResponse implements RedirectResponseInterface
{
    public function validate(): void
    {
        if (array_key_exists('errorCode', $this->data)
            && ((!is_int($this->data['errorCode']) && !is_string($this->data['errorCode']))
                || !preg_match('/^\d+$/D', (string) $this->data['errorCode']))) {
            throw new InvalidResponseException('IDBank returned an invalid error code.');
        }
        if ($this->hasError()) {
            return;
        }
        $this->bankReference();
        if ($this->request->isRegistration()) {
            if (empty($this->data['orderId']) || !is_string($this->data['orderId'])
                || empty($this->data['formUrl']) || !is_string($this->data['formUrl'])
                || !filter_var($this->data['formUrl'], FILTER_VALIDATE_URL)
                || parse_url($this->data['formUrl'], PHP_URL_SCHEME) !== 'https') {
                throw new InvalidResponseException('IDBank registration response is missing a valid orderId or HTTPS formUrl.');
            }
        } elseif ($this->request->isStatusRequest()) {
            if (!isset($this->data['orderStatus']) || (!is_int($this->data['orderStatus']) && !is_string($this->data['orderStatus']))
                || !preg_match('/^-?\d+$/D', (string) $this->data['orderStatus'])) {
                throw new InvalidResponseException('IDBank status response is missing a valid orderStatus.');
            }
            if ($this->request->isCompletion()) {
                $this->validateExpectedPayment();
            }
            if (array_key_exists('paymentAmountInfo', $this->data)) {
                if (!is_array($this->data['paymentAmountInfo'])) {
                    throw new InvalidResponseException('IDBank returned invalid payment amount information.');
                }
                foreach (['depositedAmount', 'refundedAmount'] as $field) {
                    if (array_key_exists($field, $this->data['paymentAmountInfo'])) {
                        $value = $this->data['paymentAmountInfo'][$field];
                        if ((!is_string($value) && !is_int($value)) || !preg_match('/^\d{1,19}$/D', (string) $value)) {
                            throw new InvalidResponseException('IDBank returned an invalid payment amount.');
                        }
                    }
                }
                if ($this->request->getOperation() === 'completePurchase' && $this->getOrderStatus() === 2
                    && !isset($this->data['paymentAmountInfo']['depositedAmount'])) {
                    throw new InvalidResponseException('IDBank payment amount information is missing depositedAmount.');
                }
            }
        } elseif (!array_key_exists('errorCode', $this->data)) {
            throw new InvalidResponseException('IDBank operation response is missing errorCode.');
        }
    }

    public function isSuccessful(): bool
    {
        if ($this->hasError() || $this->request->isRegistration()) {
            return false;
        }
        if ($this->request->isStatusRequest()) {
            if (isset($this->data['actionCode']) && (string) $this->data['actionCode'] !== '0') {
                return false;
            }
            if ($this->request->getOperation() === 'completePurchase' && isset($this->data['paymentAmountInfo']['depositedAmount'])) {
                $currency = new Currency($this->request->getCurrency());
                $captured = new Money((string) $this->data['paymentAmountInfo']['depositedAmount'], $currency);
                $refunded = new Money((string) ($this->data['paymentAmountInfo']['refundedAmount'] ?? '0'), $currency);
                if ($captured->subtract($refunded)->lessThan(new Money($this->request->getMinorAmount(), $currency))) {
                    return false;
                }
            }
            return $this->getOrderStatus() === ($this->request->getOperation() === 'completeAuthorize' ? 1 : 2);
        }
        return $this->getCode() === '0';
    }

    public function isRedirect(): bool
    {
        return $this->request->isRegistration() && !$this->hasError() && !empty($this->data['formUrl']);
    }

    public function isPending(): bool
    {
        return $this->request->isStatusRequest() && !$this->hasError()
            && ($this->getOrderStatus() === 0
                || ($this->getOrderStatus() === 1 && $this->request->getOperation() !== 'completeAuthorize'));
    }

    public function isCancelled(): bool
    {
        return $this->request->isStatusRequest() && !$this->hasError() && $this->getOrderStatus() === 3;
    }

    public function getOrderStatus(): ?int
    {
        return isset($this->data['orderStatus']) ? (int) $this->data['orderStatus'] : null;
    }

    public function getCode(): ?string
    {
        return isset($this->data['errorCode']) ? (string) $this->data['errorCode'] : null;
    }

    public function getMessage(): ?string
    {
        $message = $this->data['errorMessage'] ?? $this->data['actionCodeDescription'] ?? null;
        if (!is_string($message)) {
            return null;
        }
        $secrets = array_filter([$this->request->getPassword(), rawurlencode($this->request->getPassword()), urlencode($this->request->getPassword())]);
        return str_replace($secrets, '[redacted]', $message);
    }

    public function getTransactionReference(): ?string
    {
        $reference = $this->bankReference() ?? $this->request->getTransactionReference();
        return $reference !== null ? (string) $reference : null;
    }

    public function getTransactionId(): ?string
    {
        $id = $this->data['orderNumber'] ?? $this->request->getTransactionId();
        return $id !== null ? (string) $id : null;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->isRedirect() ? $this->data['formUrl'] : null;
    }

    private function hasError(): bool
    {
        return isset($this->data['errorCode']) && (string) $this->data['errorCode'] !== '0';
    }

    private function validateExpectedPayment(): void
    {
        $request = $this->request;
        if (!isset($this->data['orderNumber'], $this->data['amount'], $this->data['currency'])
            || !is_scalar($this->data['orderNumber']) || !is_scalar($this->data['amount']) || !is_scalar($this->data['currency'])
            || (string) $this->data['orderNumber'] !== (string) $request->getTransactionId()
            || (string) $this->data['amount'] !== $request->getMinorAmount()
            || str_pad((string) $this->data['currency'], 3, '0', STR_PAD_LEFT) !== $request->getNumericCurrency()) {
            throw new InvalidResponseException('IDBank order number, amount or currency does not match the expected payment.');
        }
        if ($this->bankReference() !== null && $this->bankReference() !== $request->getTransactionReference()) {
            throw new InvalidResponseException('IDBank orderId does not match the expected transaction reference.');
        }
    }

    private function bankReference(): ?string
    {
        $reference = $this->data['orderId'] ?? null;
        if ($reference !== null && (!is_string($reference) || $reference === '')) {
            throw new InvalidResponseException('IDBank returned an invalid orderId.');
        }
        if (isset($this->data['attributes'])) {
            if (!is_array($this->data['attributes'])) {
                throw new InvalidResponseException('IDBank returned invalid order attributes.');
            }
            foreach ($this->data['attributes'] as $attribute) {
                if (is_array($attribute) && ($attribute['name'] ?? null) === 'mdOrder') {
                    $value = $attribute['value'] ?? null;
                    if (!is_string($value) || $value === '' || ($reference !== null && $reference !== $value)) {
                        throw new InvalidResponseException('IDBank returned an invalid or conflicting order reference.');
                    }
                    $reference = $value;
                }
            }
        }
        return $reference;
    }
}
