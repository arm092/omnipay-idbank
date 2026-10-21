<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Api;

trait GatewayMethods
{
    public function paymentOrder(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('paymentOrder', $parameters);
    }

    public function check3ds2Eligibility(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('check3ds2Eligibility', $parameters);
    }

    public function check3ds2EligibilityPreliminary(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('check3ds2EligibilityPreliminary', $parameters);
    }

    public function finish3dsPayment(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('finish3dsPayment', $parameters);
    }

    public function createBindingNoPayment(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('createBindingNoPayment', $parameters);
    }

    public function createBindingNoPaymentAnonymous(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('createBindingNoPaymentAnonymous', $parameters);
    }

    public function paymentOrderBinding(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('paymentOrderBinding', $parameters);
    }

    public function registerModifyExpirationOrder(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('registerModifyExpirationOrder', $parameters);
    }

    public function extendBinding(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('extendBinding', $parameters);
    }

    public function processModifyExpiration(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('processModifyExpiration', $parameters);
    }

    public function unBindCard(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('unBindCard', $parameters);
    }

    public function bindCard(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('bindCard', $parameters);
    }

    public function isBindingEnabled(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('isBindingEnabled', $parameters);
    }

    public function matchSum(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('matchSum', $parameters);
    }

    public function getBindings(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('getBindings', $parameters);
    }

    public function getBindingsByCardOrId(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('getBindingsByCardOrId', $parameters);
    }

    public function registerBindingOrder(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('registerBindingOrder', $parameters);
    }

    public function processBindingOrder(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('processBindingOrder', $parameters);
    }

    public function recurringPayment(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('recurringPayment', $parameters);
    }

    public function updateRecurrent(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('updateRecurrent', $parameters);
    }

    public function getRecurrentDetails(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('getRecurrentDetails', $parameters);
    }

    public function getRecurrentReport(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('getRecurrentReport', $parameters);
    }

    public function registerRecurringTemplateNoPayment(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('registerRecurringTemplateNoPayment', $parameters);
    }

    public function cancelRecurring(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('cancelRecurring', $parameters);
    }

    public function createRecurringTemplateNoPayment(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('createRecurringTemplateNoPayment', $parameters);
    }

    public function getOrderStatus(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('getOrderStatus', $parameters);
    }

    public function getRefundStatus(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('getRefundStatus', $parameters);
    }

    public function getOrderStatusExtended(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('getOrderStatusExtended', $parameters);
    }

    public function getLastOrdersForMerchants(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('getLastOrdersForMerchants', $parameters);
    }

    public function calculateFee(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('calculateFee', $parameters);
    }

    public function calculateFeePublic(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('calculateFeePublic', $parameters);
    }

    public function p2pRegister(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pRegister', $parameters);
    }

    public function p2pPerform(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pPerform', $parameters);
    }

    public function p2pPublicPerform(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pPublicPerform', $parameters);
    }

    public function p2pGetFinishedPaymentInfo(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pGetFinishedPaymentInfo', $parameters);
    }

    public function p2pVerify(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pVerify', $parameters);
    }

    public function p2pPublicVerify(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pPublicVerify', $parameters);
    }

    public function p2pResolveAlias(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pResolveAlias', $parameters);
    }

    public function p2pVerifyUnregistered(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pVerifyUnregistered', $parameters);
    }

    public function p2pInfo(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pInfo', $parameters);
    }

    public function p2pStatus(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('p2pStatus', $parameters);
    }

    public function createCard(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('createBindingNoPayment', $parameters);
    }

    public function updateCard(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('extendBinding', $parameters);
    }

    public function deleteCard(array $parameters = []): \Omnipay\IDBank\Message\ApiRequest
    {
        return $this->api('unBindCard', $parameters);
    }
}
