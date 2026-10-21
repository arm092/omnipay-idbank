<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Helper;
use Omnipay\IDBank\Api\Operations;

class ApiRequest extends Request
{
    public function setOperation($value): static
    {
        Operations::get((string) $value);
        return $this->setParameter('operation', $value);
    }

    public function getDefinition(): array
    {
        return Operations::get($this->getOperation());
    }

    public function getEndpoint(): string
    {
        $definition = $this->getDefinition();
        $overrides = $this->getEndpointOverrides();
        $mode = $this->getTestMode() ? 'test' : 'live';
        // Overrides are separated by environment to prevent test mode using a live override.
        $override = $overrides[$mode][$this->getOperation()] ?? null;
        if ($override !== null) {
            $endpoint = $override;
        } else {
            $base = ($definition['base'] ?? 'rest') === 'epg'
                ? ($this->getTestMode() ? $this->getTestEpgEndpoint() : $this->getLiveEpgEndpoint())
                : ($this->getTestMode() ? $this->getTestEndpoint() : $this->getLiveEndpoint());
            if ($base === '' || $definition['path'] === null) {
                throw new InvalidRequestException('Configure the ' . $mode . ' endpoint for ' . $this->getOperation() . '.');
            }
            $endpoint = rtrim($base, '/') . '/' . $definition['path'];
        }
        $parts = is_string($endpoint) ? parse_url($endpoint) : false;
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidRequestException('An API endpoint must be an HTTPS URL without credentials, query or fragment.');
        }
        return $endpoint;
    }

    public function getData(): array
    {
        $definition = $this->getDefinition();
        $this->getEndpoint();
        $data = $this->getApiParameters();
        $referenceField = $definition['reference'] ?? 'orderId';
        if ($this->getTransactionReference() !== null && $this->getTransactionReference() !== '') {
            $this->putNormalized($data, $referenceField, (string) $this->getTransactionReference());
        }
        if ($this->getTransactionId() !== null && $this->getTransactionId() !== '') {
            $this->putNormalized($data, 'orderNumber', (string) $this->getTransactionId());
        }
        if ($this->getParameter('amount') !== null) {
            $this->putNormalized($data, $definition['amountField'] ?? 'amount', $this->getMinorAmount());
            if (in_array('currency', $definition['required'] ?? [], true)) {
                $this->putNormalized($data, 'currency', $this->getNumericCurrency());
            }
        }
        if (in_array('currency', $definition['required'] ?? [], true) && !isset($data['currency'])) {
            $data['currency'] = $this->getNumericCurrency();
        }
        if ($this->getLanguage() !== '' && !isset($data['language'])) {
            $data['language'] = $this->getLanguage();
        }
        $this->addCard($data);
        if ($this->getCardReference() !== null && $this->getCardReference() !== '') {
            $this->putNormalized($data, 'bindingId', (string) $this->getCardReference());
        }
        $auth = $definition['auth'] ?? 'body';
        if ($auth !== 'none') {
            $this->validate('userName', 'password');
            if ($auth === 'body') {
                $data['userName'] = $this->getUserName();
                $data['password'] = $this->getPassword();
            }
        }
        foreach ($definition['required'] ?? [] as $field) {
            $this->requireField($data, $field);
        }
        foreach ($definition['oneOf'] ?? [] as $fields) {
            if (!array_filter($fields, fn ($field) => $this->hasField($data, $field))) {
                throw new InvalidRequestException('Supply at least one of: ' . implode(', ', $fields) . '.');
            }
        }
        foreach ($definition['requiredWithout'] ?? [] as $unless => $fields) {
            if (!$this->hasField($data, $unless)) {
                foreach ($fields as $field) {
                    $this->requireField($data, $field);
                }
            }
        }
        foreach (['amount', 'amountInput'] as $field) {
            if (isset($data[$field]) && ((!is_string($data[$field]) && !is_int($data[$field]))
                || !preg_match('/^\d{1,12}$/D', (string) $data[$field]) || ltrim((string) $data[$field], '0') === '')) {
                throw new InvalidRequestException($field . ' must be a positive integer of up to 12 digits in minor units.');
            }
        }
        if ($this->getOperation() === 'getLastOrdersForMerchants'
            && (!is_numeric($data['size']) || (int) $data['size'] < 1 || (int) $data['size'] > 200)) {
            throw new InvalidRequestException('Report size must be between 1 and 200.');
        }
        return $data;
    }

    public function sendData($data): ApiResponse
    {
        $definition = $this->getDefinition();
        $endpoint = $this->getEndpoint();
        if (($definition['auth'] ?? 'body') === 'query') {
            $endpoint .= '?' . http_build_query(['userName' => $this->getUserName(), 'password' => $this->getPassword()], '', '&', PHP_QUERY_RFC3986);
        }
        $json = ($definition['encoding'] ?? 'form') === 'json';
        if (!$json) {
            foreach ($data as $name => $value) {
                if (is_array($value)) {
                    $data[$name] = in_array($name, ['merchants', 'transactionStates'], true)
                        ? implode(',', $value)
                        : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                } elseif (is_bool($value)) {
                    $data[$name] = $value ? 'true' : 'false';
                }
            }
        }
        $body = $json ? json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            : http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        try {
            $httpResponse = $this->httpClient->request('POST', $endpoint, [
                'Content-Type' => $json ? 'application/json' : 'application/x-www-form-urlencoded',
                'Accept' => ($definition['response'] ?? 'api') === 'raw' ? '*/*' : 'application/json',
            ], $body);
            $status = $httpResponse->getStatusCode();
            $responseBody = (string) $httpResponse->getBody();
        } catch (\Exception) {
            throw new InvalidResponseException('IDBank transport failed; the operation outcome is unknown. Reconcile its status before retrying.');
        }
        $kind = $definition['response'] ?? 'api';
        if ($status < 200 || ($status >= 300 && !($kind === 'raw' && $status < 400))) {
            throw new InvalidResponseException('IDBank returned HTTP ' . $status . '. Reconcile the operation before retrying.');
        }
        if ($kind === 'raw') {
            $decoded = ['rawBody' => $responseBody, 'httpStatus' => $status, 'headers' => $httpResponse->getHeaders()];
            if ($status >= 300 && $httpResponse->hasHeader('Location')) {
                $decoded['redirect'] = $httpResponse->getHeaderLine('Location');
            }
        } else {
            try {
                $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            } catch (\JsonException) {
                throw new InvalidResponseException('IDBank returned invalid JSON.');
            }
            if ($kind === 'boolean' && is_bool($decoded)) {
                $decoded = ['result' => $decoded];
            }
            if (!is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
                throw new InvalidResponseException('IDBank returned an invalid API response object.');
            }
        }
        $response = new ApiResponse($this, $decoded);
        $response->validate();
        return $this->response = $response;
    }

    public function redact(string $message): string
    {
        $secrets = [$this->getPassword()];
        if ($this->getCard()) {
            $secrets[] = (string) $this->getCard()->getNumber();
            $secrets[] = (string) $this->getCard()->getCvv();
        }
        $collect = function (array $parameters) use (&$collect, &$secrets): void {
            foreach ($parameters as $key => $value) {
                if (is_array($value)) {
                    $collect($value);
                } elseif (preg_match('/password|pan|cvc|cvv|token|paRes|paReq|cReq|transactionData/i', (string) $key) && is_scalar($value)) {
                    $secrets[] = (string) $value;
                }
            }
        };
        $collect($this->getApiParameters());
        foreach (array_filter($secrets, fn ($value) => $value !== '') as $secret) {
            $message = str_replace(array_unique([$secret, rawurlencode($secret), urlencode($secret)]), '[redacted]', $message);
        }
        return $message;
    }

    public function __debugInfo(): array
    {
        return ['operation' => $this->getOperation(), 'testMode' => $this->getTestMode()];
    }

    private function putNormalized(array &$data, string $field, string $value): void
    {
        if (isset($data[$field]) && (string) $data[$field] !== $value) {
            throw new InvalidRequestException('Conflicting normalized and wire payment parameters for ' . $field . '.');
        }
        $data[$field] = $value;
    }

    private function hasField(array $data, string $field): bool
    {
        return array_key_exists($field, $data) && $data[$field] !== null
            && ($data[$field] !== '' || ($field === 'merchants' && $this->getOperation() === 'getLastOrdersForMerchants'));
    }

    private function requireField(array $data, string $field): void
    {
        if (!$this->hasField($data, $field)) {
            throw new InvalidRequestException('The ' . $field . ' parameter is required for ' . $this->getOperation() . '.');
        }
    }

    private function addCard(array &$data): void
    {
        $card = $this->getCard();
        if (!$card) {
            return;
        }
        $operation = $this->getOperation();
        $panOnly = in_array($operation, ['check3ds2EligibilityPreliminary', 'getBindingsByCardOrId'], true);
        if (!$panOnly && !in_array($operation, ['paymentOrder', 'calculateFee', 'calculateFeePublic', 'createBindingNoPayment', 'createBindingNoPaymentAnonymous', 'processBindingOrder'], true)) {
            return;
        }
        if (!preg_match('/^\d{13,19}$/D', (string) $card->getNumber()) || !Helper::validateLuhn($card->getNumber())) {
            throw new InvalidRequestException('Card number is invalid.');
        }
        if (!$panOnly) {
            $card->validate();
        }
        $map = match ($operation) {
            'paymentOrder' => ['$PAN' => $card->getNumber(), '$CVC' => $card->getCvv(), 'YYYY' => $card->getExpiryDate('Y'), 'MM' => $card->getExpiryDate('m'), 'TEXT' => $card->getName()],
            'calculateFee', 'calculateFeePublic' => ['$PAN' => $card->getNumber(), '$CVC' => $card->getCvv(), 'expiry' => $card->getExpiryDate('Ym')],
            'createBindingNoPayment', 'createBindingNoPaymentAnonymous', 'processBindingOrder' => ['pan' => $card->getNumber(), 'expiryDate' => $card->getExpiryDate('Ym'), 'cardholderName' => $card->getName(), 'cvc' => $card->getCvv()],
            'check3ds2EligibilityPreliminary', 'getBindingsByCardOrId' => ['pan' => $card->getNumber()],
            default => [],
        };
        foreach ($map as $key => $value) {
            if ($value !== null && $value !== '') {
                $this->putNormalized($data, $key, (string) $value);
            }
        }
    }
}
