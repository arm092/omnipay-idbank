<?php

namespace Omnipay\IDBank\Api;

final class BindingOperations
{
    public static function all(): array
    {
        return [
            'createBindingNoPayment' => [
                'path' => 'createBindingNoPayment.do',
                'required' => ['pan', 'expiryDate', 'ClientId'],
                'successFields' => ['bindingId'],
                'page' => 167,
            ],
            'createBindingNoPaymentAnonymous' => [
                'path' => 'createBindingNoPaymentAnonymous.do',
                'auth' => 'none',
                'required' => ['merchantLogin', 'pan', 'expiryDate', 'ClientId'],
                'successFields' => ['bindingId'],
                'page' => 172,
            ],
            'paymentOrderBinding' => [
                'path' => 'paymentOrderBinding.do',
                'required' => ['mdOrder', 'bindingId'],
                'reference' => 'mdOrder',
                'jsonFields' => ['clientBrowserInfo'],
                'response' => 'payment',
                'page' => 159,
            ],
            'registerModifyExpirationOrder' => [
                'base' => 'epg',
                'path' => 'rest/public/registerModifyExpirationOrder.do',
                'required' => ['sessionExpiredDate', 'bindingId'],
                'oneOf' => [['email', 'phone']],
                'jsonFields' => ['clientBrowserInfo'],
                'response' => 'registration',
                'page' => 181,
            ],
            'extendBinding' => [
                'path' => 'extendBinding.do',
                'required' => ['newExpiry', 'bindingId'],
                'page' => 188,
            ],
            'processModifyExpiration' => [
                'base' => 'epg',
                'path' => 'rest/internal/processModifyExpiration.do',
                'required' => ['expiryDate', 'bindingId', 'mdOrder'],
                'reference' => 'mdOrder',
                'response' => 'payment',
                'successStatuses' => [8],
                'page' => 191,
            ],
            'unBindCard' => [
                'path' => 'unBindCard.do',
                'required' => ['bindingId'],
                'page' => 198,
            ],
            'bindCard' => [
                'path' => 'bindCard.do',
                'required' => ['bindingId'],
                'page' => 201,
            ],
            'isBindingEnabled' => [
                'path' => 'isBindingEnabled.do',
                'auth' => 'none',
                'required' => ['merchantLogin', 'bindingId'],
                'response' => 'payload',
                'successFields' => ['result'],
                'page' => 204,
            ],
            'matchSum' => [
                'path' => 'matchSum.do',
                'auth' => 'none',
                'required' => ['merchantLogin', 'bindingId', 'mdOrder', 'amount'],
                'reference' => 'mdOrder',
                'response' => 'boolean',
                'page' => 206,
            ],
            'getBindings' => [
                'path' => 'getBindings.do',
                'required' => ['clientId'],
                'page' => 208,
            ],
            'getBindingsByCardOrId' => [
                'path' => 'getBindingsByCardOrId.do',
                'required' => [],
                'oneOf' => [['pan', 'bindingId']],
                'page' => 212,
            ],
            'registerBindingOrder' => [
                'base' => 'epg',
                'path' => 'rest/public/registerBindingOrder.do',
                'required' => ['sessionExpiredDate', 'clientId', 'features'],
                'response' => 'registration',
                'page' => 217,
            ],
            'processBindingOrder' => [
                'base' => 'epg',
                'path' => 'rest/public/processBindingOrder.do',
                'required' => ['pan', 'expiryDate', 'mdOrder'],
                'reference' => 'mdOrder',
                'jsonFields' => ['clientBrowserInfo'],
                'response' => 'payment',
                'successStatuses' => [7],
                'page' => 226,
            ],
        ];
    }
}
