<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Api;

final class CardOperations
{
    public static function all(): array
    {
        return [
            'paymentOrder' => [
                'path' => 'paymentorder.do',
                'required' => ['MDORDER', '$PAN', 'YYYY', 'MM', 'language'],
                'reference' => 'MDORDER',
                'response' => 'payment',
                'successFields' => ['info'],
                'jsonFields' => ['jsonParams', 'threeDS2Params', 'clientBrowserInfo', 'aReqFieldsOverride'],
                'page' => 39,
            ],
            'check3ds2Eligibility' => [
                'path' => 'api/v1/3ds2/check',
                'base' => 'epg',
                'required' => ['mdOrder'],
                'reference' => 'mdOrder',
                'response' => 'payload',
                'successFields' => ['completed'],
                'page' => 56,
            ],
            'check3ds2EligibilityPreliminary' => [
                'path' => 'api/v1/3ds2/checkPreliminary',
                'base' => 'epg',
                'required' => ['mdOrder', 'pan'],
                'reference' => 'mdOrder',
                'response' => 'payload',
                'successFields' => ['threeDSProtocolVersion'],
                'page' => 59,
            ],
            'finish3dsPayment' => [
                'path' => null,
                'reference' => 'MD',
                'response' => 'raw',
                'page' => 56,
            ],
        ];
    }
}
