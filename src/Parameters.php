<?php

declare(strict_types=1);

namespace Omnipay\IDBank;

trait Parameters
{
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
