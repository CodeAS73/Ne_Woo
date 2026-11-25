<?php

return [
    'supervisor_reporting' => [
        'reports.dashboard' => 'Platform commerce dashboard and product trends',
        'reports.transactions' => 'Platform transaction ledger',
        'reports.settlements' => 'Platform settlement ledger',
    ],
    'roles' => [
        'system_admin' => ['label' => 'System Administrator', 'type' => 'internal', 'permissions' => ['*']],
        'supervisor' => ['label' => 'Supervisor', 'type' => 'internal', 'permissions' => ['internal.access', 'kyc.review', 'orders.view_all', 'settlements.review']],
        'support' => ['label' => 'Support', 'type' => 'internal', 'permissions' => ['internal.access', 'users.view']],
        'merchant' => ['label' => 'Merchant', 'type' => 'merchant', 'permissions' => ['products.create', 'products.manage_own', 'orders.view_own', 'orders.fulfill_own']],
        'buyer' => ['label' => 'Buyer', 'type' => 'buyer', 'permissions' => ['orders.view_own']],
    ],
];
