<?php

declare(strict_types=1);

namespace Omnipay\IDBank;

trait Parameters
{
    public function getLiveEpgEndpoint(): string
    {
        return (string) $this->getParameter('liveEpgEndpoint');
    }

    public function setLiveEpgEndpoint($value): static
    {
        return $this->setParameter('liveEpgEndpoint', $value);
    }

    public function getTestEpgEndpoint(): string
    {
        return (string) $this->getParameter('testEpgEndpoint');
    }

    public function setTestEpgEndpoint($value): static
    {
        return $this->setParameter('testEpgEndpoint', $value);
    }

    public function getEndpointOverrides(): array
    {
        return $this->getParameter('endpointOverrides') ?? [];
    }

    public function setEndpointOverrides(array $value): static
    {
        foreach ($value as $environment => $operations) {
            if (!in_array($environment, ['live', 'test'], true) || !is_array($operations)) {
                throw new \Omnipay\Common\Exception\InvalidRequestException('endpointOverrides must contain live/test operation maps.');
            }
        }
        return $this->setParameter('endpointOverrides', $value);
    }

    public function getUserName(): string
    {
        return (string) $this->getParameter('userName');
    }

    public function setUserName($value): static
    {
        return $this->setParameter('userName', $value);
    }

    public function getPassword(): string
    {
        return (string) $this->getParameter('password');
    }

    public function setPassword($value): static
    {
        return $this->setParameter('password', $value);
    }

    public function getLanguage(): string
    {
        return (string) $this->getParameter('language');
    }

    public function setLanguage($value): static
    {
        return $this->setParameter('language', $value);
    }

    public function getLiveEndpoint(): string
    {
        return (string) $this->getParameter('liveEndpoint');
    }

    public function setLiveEndpoint($value): static
    {
        return $this->setParameter('liveEndpoint', $value);
    }

    public function getTestEndpoint(): string
    {
        return (string) $this->getParameter('testEndpoint');
    }

    public function setTestEndpoint($value): static
    {
        return $this->setParameter('testEndpoint', $value);
    }
}
