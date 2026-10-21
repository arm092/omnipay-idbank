<?php

namespace Omnipay\IDBank\Api;

final class RecurringOperations
{
    public static function all(): array
    {
        return [
            'recurringPayment' => [
                'path' => 'recurringPayment.do',
                'required' => ['recurrenceId'],
                'page' => 62,
            ],
            'updateRecurrent' => [
                'path' => 'updateRecurrent.do',
                'required' => ['recurrenceId', 'recurrenceType'],
                'page' => 67,
            ],
            'getRecurrentDetails' => [
                'path' => 'getRecurrentDetails.do',
                'required' => ['recurrenceId'],
                'successFields' => ['recurrenceId'],
                'page' => 70,
            ],
            'getRecurrentReport' => [
                'path' => 'getRecurrentReport.do',
                'required' => ['recurrenceId'],
                'successFields' => ['report'],
                'page' => 77,
            ],
            'registerRecurringTemplateNoPayment' => [
                'base' => 'epg',
                'path' => 'rest/public/registerRecurringTemplateNoPayment.do',
                'required' => [],
                'successFields' => ['orderId'],
                'page' => 80,
            ],
            'cancelRecurring' => [
                'base' => 'epg',
                'path' => 'rest/public/cancelRecurring.do',
                'required' => ['recurrenceId'],
                'successFields' => ['recurrenceId'],
                'page' => 83,
            ],
            'createRecurringTemplateNoPayment' => [
                'base' => 'epg',
                'path' => null,
                'required' => ['mdOrder'],
                'reference' => 'mdOrder',
                'response' => 'payment',
                'successStatuses' => [10],
                'page' => 81,
            ],
        ];
    }
}
