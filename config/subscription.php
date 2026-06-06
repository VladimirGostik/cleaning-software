<?php

declare(strict_types=1);

use App\Enums\FeatureEnum;
use App\Enums\SubscriptionPlanEnum;

return [
    'plans' => [
        SubscriptionPlanEnum::Free->value => [
            'features' => [],
            'quotas' => [
                FeatureEnum::MultiUser->value => 1,
            ],
        ],

        SubscriptionPlanEnum::Starter->value => [
            'features' => [
                FeatureEnum::Clients->value,
                FeatureEnum::Objects->value,
            ],
            'quotas' => [
                FeatureEnum::MultiUser->value => 5,
            ],
        ],

        SubscriptionPlanEnum::Pro->value => [
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
