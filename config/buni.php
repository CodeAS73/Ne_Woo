<?php

return [
    'provider' => 'kcb_buni',
    'collection_service' => 'mpesa_stk_push',
    // Live collection remains unavailable until the approved API contract is implemented.
    'mode' => env('BUNI_MODE', 'disabled'),
    'token_url' => env('BUNI_TOKEN_URL', 'https://uat.buni.kcbgroup.com/token'),
    'consumer_key' => env('BUNI_CONSUMER_KEY'),
    'consumer_secret' => env('BUNI_CONSUMER_SECRET'),
    'platform_account' => env('BUNI_PLATFORM_ACCOUNT'),
    'short_code' => env('BUNI_SHORT_CODE'),
    'callback_url' => env('BUNI_CALLBACK_URL'),
];
