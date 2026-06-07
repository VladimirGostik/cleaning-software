<?php

declare(strict_types=1);

use App\Enums\FeatureEnum;
use App\Enums\SubscriptionPlanEnum;

return [
    'plans' => [
        SubscriptionPlanEnum::Free->value => [
            'max_tenants' => 1,
            'features' => [],
            'quotas' => [
                FeatureEnum::MultiUser->value => 1,
            ],
        ],

        SubscriptionPlanEnum::Starter->value => [
            'max_tenants' => 2,
            'features' => [
                FeatureEnum::Clients->value,
                FeatureEnum::Objects->value,
            ],
            'quotas' => [
                FeatureEnum::MultiUser->value => 5,
            ],
        ],

        SubscriptionPlanEnum::Pro->value => [
            'max_tenants' => 3,
            'features' => [
                FeatureEnum::Clients->value,
                FeatureEnum::Objects->value,
                FeatureEnum::Quotes->value,
                FeatureEnum::Contracts->value,
                FeatureEnum::Schedule->value,
                FeatureEnum::Invoices->value,
                FeatureEnum::Employees->value,
                FeatureEnum::Reports->value,
            ],
            'quotas' => [
                FeatureEnum::MultiUser->value => 20,
            ],
        ],

        SubscriptionPlanEnum::Enterprise->value => [
            'max_tenants' => null,
            'features' => [
                FeatureEnum::Clients->value,
                FeatureEnum::Objects->value,
                FeatureEnum::Quotes->value,
                FeatureEnum::Contracts->value,
                FeatureEnum::Schedule->value,
                FeatureEnum::Invoices->value,
                FeatureEnum::Employees->value,
                FeatureEnum::Reports->value,
                FeatureEnum::MobileAccess->value,
                FeatureEnum::MultiUser->value,
            ],
            'quotas' => [
                FeatureEnum::MultiUser->value => null, // unlimited
            ],
        ],
    ],
];
