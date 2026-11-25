<?php

return [
    'currency' => 'KES',
    'business_timezone' => 'Africa/Nairobi',
    'products' => [
        'max_photos' => 5,
    ],
    // Policy caps only; charging and payout workflows are not implemented yet.
    'fees' => [
        'buyer_default_basis_points' => 50,
        'buyer_min_minor_units' => 3000,
        'merchant_basis_points' => (int) env('NEWOO_MERCHANT_FEE_BPS', 0),
        'policy_version' => env('NEWOO_FEE_POLICY_VERSION', 'development-v1'),
        'merchant_max_basis_points' => 500,
        'buyer_max_minor_units' => 250000,
    ],
    'settlements' => [
        'enabled' => false,
        'approval_rule' => 'end_of_next_hour',
        'timezone' => 'Africa/Nairobi',
        'first_hour' => 8,
        'last_hour' => 19,
        'minute' => 59,
    ],
];
