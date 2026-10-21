<?php

declare(strict_types=1);

namespace Omnipay\IDBank;

use Omnipay\Common\AbstractGateway;
use Omnipay\IDBank\Message\Request;

class Gateway extends AbstractGateway
{
    use Parameters;

    public function getName(): string
    {
        return 'IDBank';
    }

    public function getDefaultParameters(): array
    {
        return [
            'userName' => '',
            'password' => '',
            'currency' => 'AMD',
            'language' => 'en',
            'testMode' => false,
            'liveEndpoint' => 'https://ipayproxy.idp.am/payment/rest/',
            'testEndpoint' => '',
        ];
    }

    public function purchase(array $parameters = [])
    {
        return $this->operation('purchase', $parameters);
    }

    public function authorize(array $parameters = [])
    {
        return $this->operation('authorize', $parameters);
    }

    public function completePurchase(array $parameters = [])
    {
        return $this->operation('completePurchase', $parameters);
    }

    public function completeAuthorize(array $parameters = [])
    {
        return $this->operation('completeAuthorize', $parameters);
    }

    public function capture(array $parameters = [])
    {
        return $this->operation('capture', $parameters);
    }

    public function refund(array $parameters = [])
    {
        return $this->operation('refund', $parameters);
    }

    public function void(array $parameters = [])
    {
        return $this->operation('void', $parameters);
    }

    public function fetchTransaction(array $parameters = [])
    {
        return $this->operation('fetchTransaction', $parameters);
    }

    private function operation(string $operation, array $parameters): Request
    {
        return $this->createRequest(Request::class, array_merge($parameters, ['operation' => $operation]));
    }
}
