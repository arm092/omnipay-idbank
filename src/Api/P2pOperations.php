<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Api;

/**
 * EPG Merchant Integration Guide, section 4.17, PDF pages 249-330.
 *
 * These operations use the explicitly configured EPG root, not payment/rest.
 */
final class P2pOperations
{
    public static function all(): array
    {
        return [
            'p2pRegister' => [
                'path' => 'rest/p2p/register.do',
                'base' => 'epg',
                'encoding' => 'json',
                'auth' => 'body',
                'required' => ['amount', 'currency'],
                'response' => 'registration',
                'page' => '249-258',
            ],
            'p2pPerform' => [
                'amountField' => 'amountInput',
                'path' => 'rest/p2p/perform',
                'base' => 'epg',
                'encoding' => 'json',
                'auth' => 'none',
                'required' => ['orderId'],
                'response' => 'payment',
                'page' => '258-284',
            ],
            'p2pPublicPerform' => [
                'amountField' => 'amountInput',
                'path' => 'rest/p2p/public/perform',
                'base' => 'epg',
                'encoding' => 'json',
                'auth' => 'body',
                'required' => ['orderId'],
                'response' => 'payment',
                'page' => '258-284',
            ],
            'p2pGetFinishedPaymentInfo' => [
                'path' => 'rest/p2p/getFinishedPaymentInfo.do',
                'base' => 'epg',
                'encoding' => 'form',
                'auth' => 'body',
                'required' => ['orderId'],
                'response' => 'p2pFinished',
                'successFields' => ['status'],
                'page' => '284-290',
            ],
            'p2pVerify' => [
                'path' => 'rest/p2p/verify',
                'base' => 'epg',
                'encoding' => 'json',
                'auth' => 'none',
                'required' => ['orderId'],
                'response' => 'api',
                'page' => '290-300',
            ],
            'p2pPublicVerify' => [
                'path' => 'rest/p2p/public/verify',
                'base' => 'epg',
                'encoding' => 'json',
                'auth' => 'body',
                'required' => ['orderId'],
                'response' => 'api',
                'page' => '290-300',
            ],
            'p2pResolveAlias' => [
                'path' => 'rest/p2p/alias/resolve',
                'base' => 'epg',
                'encoding' => 'json',
                'auth' => 'none',
                'required' => ['toAlias'],
                'response' => 'payload',
                'successFields' => ['bankName', 'recipientName'],
                'page' => '300-302',
            ],
            'p2pVerifyUnregistered' => [
                'path' => 'rest/p2p/unregistered/verify',
                'base' => 'epg',
                'encoding' => 'json',
                'auth' => 'query',
                'required' => ['amount', 'currency'],
                'response' => 'api',
                'page' => '302-309',
            ],
            'p2pInfo' => [
                'path' => 'rest/p2p/info',
                'base' => 'epg',
                'encoding' => 'form',
                'auth' => 'none',
                'required' => ['orderId'],
                'response' => 'payload',
                'successFields' => ['status'],
                'page' => '309-319',
            ],
            'p2pStatus' => [
                'path' => 'rest/p2p/status',
                'base' => 'epg',
                'encoding' => 'json',
                'auth' => 'query',
                'required' => [],
                'oneOf' => [['orderId', 'orderNumber']],
                'response' => 'status',
                'page' => '320-330',
            ],
        ];
    }
}
