<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Api;

final class ReportingOperations
{
    public static function all(): array
    {
        return [
            'getOrderStatus' => [
                'path' => 'getOrderStatus.do',
                'required' => ['orderId'],
                'response' => 'status',
                'statusField' => 'OrderStatus',
                'page' => 89,
            ],
            'getRefundStatus' => [
                'path' => 'rest/public/getRefundStatus.do',
                'base' => 'epg',
                'reference' => 'mdOrder',
                'response' => 'api',
                'successFields' => ['refunds'],
                'page' => 101,
            ],
            'getOrderStatusExtended' => [
                'path' => 'getOrderStatusExtended.do',
                'oneOf' => [['orderId', 'orderNumber']],
                'response' => 'status',
                'statusField' => 'orderStatus',
                'page' => 106,
            ],
            'getLastOrdersForMerchants' => [
                'path' => 'getLastOrdersForMerchants.do',
                'required' => ['size', 'from', 'to', 'transactionStates', 'merchants'],
                'response' => 'payload',
                'successFields' => ['orderStatuses', 'totalCount', 'page', 'pageSize'],
                'page' => 137,
            ],
            'calculateFee' => [
                'path' => 'fee/calculate.do',
                'base' => 'epg',
                'encoding' => 'json',
                'auth' => 'none',
                'oneOf' => [['$PAN', 'bindingId']],
                'requiredWithout' => ['bindingId' => ['$PAN', 'expiry']],
                'reference' => 'mdOrder',
                'response' => 'fee',
                'successFields' => ['status', 'feeAmountFormatted', 'totalAmountFormatted'],
                'page' => 154,
            ],
            'calculateFeePublic' => [
                'path' => 'fee/public/calculate.do',
                'base' => 'epg',
                'encoding' => 'json',
                'oneOf' => [['$PAN', 'bindingId']],
                'requiredWithout' => ['bindingId' => ['$PAN', 'expiry']],
                'reference' => 'mdOrder',
                'response' => 'fee',
                'successFields' => ['status', 'feeAmountFormatted', 'totalAmountFormatted'],
                'page' => 154,
            ],
        ];
    }
}
