<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Message;

use Omnipay\Common\Exception\InvalidResponseException;

class ApiResponse extends Response
{
    private function kind(): string
    {
        return $this->request->getDefinition()['response'] ?? 'api';
    }

    public function validate(): void
    {
        if ($this->kind() === 'raw') {
            if (isset($this->data['redirect'])) {
                $this->validateUrl($this->data['redirect']);
            }
            return;
        }
        foreach (['errorCode', 'ErrorCode'] as $field) {
            if (array_key_exists($field, $this->data)
                && ((!is_string($this->data[$field]) && !is_int($this->data[$field]))
                    || !preg_match('/^\d+$/D', (string) $this->data[$field]))) {
                throw new InvalidResponseException('IDBank returned an invalid API error code.');
            }
        }
        if (isset($this->data['errorCode'], $this->data['ErrorCode'])
            && (string) $this->data['errorCode'] !== (string) $this->data['ErrorCode']) {
            throw new InvalidResponseException('IDBank returned conflicting API error codes.');
        }
        if ($this->hasApiError()) {
            return;
        }
        if ($this->kind() === 'boolean') {
            if (!isset($this->data['result']) || !is_bool($this->data['result'])) {
                throw new InvalidResponseException('IDBank returned an invalid boolean result.');
            }
            return;
        }
        if ($this->kind() === 'fee') {
            if (!in_array($this->data['status'] ?? null, ['SUCCESS', 'ERROR', 'UNAVAILABLE'], true)) {
                throw new InvalidResponseException('IDBank returned an invalid fee calculation status.');
            }
            if ($this->data['status'] !== 'SUCCESS') {
                return;
            }
        }
        foreach ($this->request->getDefinition()['successFields'] ?? [] as $field) {
            if (!array_key_exists($field, $this->data) || $this->data[$field] === null) {
                throw new InvalidResponseException('IDBank API response is missing ' . $field . '.');
            }
        }
        if ($this->kind() === 'api' && $this->getCode() === null) {
            throw new InvalidResponseException('IDBank API response is missing errorCode.');
        }
        if ($this->kind() === 'registration') {
            if (!is_string($this->data['orderId'] ?? null) || $this->data['orderId'] === '') {
                throw new InvalidResponseException('IDBank registration response is missing orderId.');
            }
            $this->validateUrl($this->data['formUrl'] ?? null);
        }
        if ($this->kind() === 'status') {
            $field = $this->request->getDefinition()['statusField'] ?? 'orderStatus';
            if (!array_key_exists($field, $this->data)) {
                throw new InvalidResponseException('IDBank status response is missing ' . $field . '.');
            }
        }
        foreach (['orderStatus', 'OrderStatus', 'orderStatusCode', 'actionCode'] as $field) {
            if (isset($this->data[$field]) && ((!is_int($this->data[$field]) && !is_string($this->data[$field]))
                || !preg_match('/^-?\d+$/D', (string) $this->data[$field]))) {
                throw new InvalidResponseException('IDBank returned an invalid transaction status.');
            }
        }
        if ($this->kind() === 'payment') {
            if ($this->getCode() === null && $this->getOrderStatus() === null) {
                throw new InvalidResponseException('IDBank payment response is missing its outcome.');
            }
            foreach (['paReq', 'cReq'] as $field) {
                if (isset($this->data[$field]) && !is_string($this->data[$field])) {
                    throw new InvalidResponseException('IDBank returned an invalid authentication message.');
                }
            }
            if (isset($this->data['acsUrl'])) {
                $this->validateUrl($this->data['acsUrl']);
                if (!empty($this->data['paReq'])) {
                    $this->validateUrl($this->data['termUrl'] ?? null);
                    if ($this->getTransactionReference() === null) {
                        throw new InvalidResponseException('IDBank 3DS challenge is missing its transaction reference.');
                    }
                } elseif (empty($this->data['cReq'])) {
                    throw new InvalidResponseException('IDBank 3DS challenge is missing paReq or cReq.');
                }
            }
            if (isset($this->data['redirect'])) {
                $this->validateUrl($this->data['redirect']);
            }
        }
    }

    public function isSuccessful(): bool
    {
        if ($this->hasApiError()) {
            return false;
        }
        return match ($this->kind()) {
            'registration', 'raw' => false,
            'boolean' => $this->data['result'] === true,
            'fee' => $this->data['status'] === 'SUCCESS',
            'payment' => empty($this->data['acsUrl']) && $this->actionSucceeded()
                && in_array($this->getOrderStatus(), $this->request->getDefinition()['successStatuses'] ?? [2], true),
            'status' => $this->getOrderStatus() === 2 && $this->actionSucceeded(),
            'p2pFinished' => ($this->data['status'] ?? null) === 'DEPOSITED',
            'api' => $this->getCode() === '0',
            default => true,
        };
    }

    public function isPending(): bool
    {
        if ($this->hasApiError()) {
            return false;
        }
        if ($this->kind() === 'payment') {
            $status = $this->getOrderStatus();
            return $status === null || in_array($status, [0, 1, 5], true);
        }
        return $this->kind() === 'status' && in_array($this->getOrderStatus(), [0, 1, 5], true);
    }

    public function isCancelled(): bool
    {
        return !$this->hasApiError() && ($this->getOrderStatus() === 3 || ($this->data['status'] ?? null) === 'REVERSED');
    }

    public function isRedirect(): bool
    {
        if ($this->hasApiError()) {
            return false;
        }
        if ($this->kind() === 'registration') {
            return isset($this->data['formUrl']);
        }
        return in_array($this->kind(), ['payment', 'raw'], true)
            && (!empty($this->data['acsUrl']) || (!empty($this->data['redirect']) && !$this->isSuccessful()));
    }

    public function getRedirectUrl(): ?string
    {
        if (!$this->isRedirect()) {
            return null;
        }
        return $this->data['acsUrl'] ?? $this->data['formUrl'] ?? $this->data['redirect'] ?? null;
    }

    public function getRedirectMethod(): string
    {
        return !empty($this->data['acsUrl']) ? 'POST' : 'GET';
    }

    public function getRedirectData(): array
    {
        if (empty($this->data['acsUrl']) || !$this->isRedirect()) {
            return [];
        }
        if (!empty($this->data['cReq'])) {
            return ['creq' => $this->data['cReq']];
        }
        return ['PaReq' => $this->data['paReq'], 'MD' => $this->getTransactionReference(), 'TermUrl' => $this->data['termUrl']];
    }

    public function getCode(): ?string
    {
        $code = $this->data['errorCode'] ?? $this->data['ErrorCode'] ?? null;
        return $code !== null ? (string) $code : null;
    }

    public function getMessage(): ?string
    {
        $message = $this->data['errorMessage'] ?? $this->data['ErrorMessage'] ?? $this->data['actionCodeDescription'] ?? $this->data['info'] ?? null;
        return is_string($message) ? $this->request->redact($message) : null;
    }

    public function getOrderStatus(): ?int
    {
        $field = $this->request->getDefinition()['statusField'] ?? 'orderStatus';
        $status = $this->data[$field] ?? $this->data['orderStatusCode'] ?? $this->data['OrderStatus'] ?? null;
        return $status !== null ? (int) $status : null;
    }

    public function getTransactionReference(): ?string
    {
        foreach (['orderId', 'mdOrder', 'MDORDER'] as $field) {
            if (is_string($this->data[$field] ?? null) && $this->data[$field] !== '') {
                return $this->data[$field];
            }
        }
        foreach (is_array($this->data['attributes'] ?? null) ? $this->data['attributes'] : [] as $attribute) {
            if (is_array($attribute) && ($attribute['name'] ?? null) === 'mdOrder' && is_string($attribute['value'] ?? null)) {
                return $attribute['value'];
            }
        }
        $parameters = $this->request->getApiParameters();
        $field = $this->request->getDefinition()['reference'] ?? 'orderId';
        $reference = $this->request->getTransactionReference() ?? $parameters[$field] ?? null;
        return is_scalar($reference) ? (string) $reference : null;
    }

    public function getTransactionId(): ?string
    {
        $id = $this->data['orderNumber'] ?? $this->data['OrderNumber'] ?? $this->request->getTransactionId();
        return is_scalar($id) ? (string) $id : null;
    }

    public function getCardReference(): ?string
    {
        $id = $this->data['bindingId'] ?? $this->data['bindingInfo']['bindingId'] ?? null;
        return is_scalar($id) ? (string) $id : null;
    }

    public function getRecurrenceId(): ?string
    {
        $id = $this->data['recurrenceId'] ?? null;
        return is_scalar($id) ? (string) $id : null;
    }

    private function hasApiError(): bool
    {
        return ($this->getCode() !== null && $this->getCode() !== '0') || ($this->data['error'] ?? null) === true;
    }

    private function actionSucceeded(): bool
    {
        return !isset($this->data['actionCode']) || (string) $this->data['actionCode'] === '0';
    }

    private function validateUrl($url): void
    {
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidResponseException('IDBank returned an invalid HTTPS redirect URL.');
        }
    }
}
