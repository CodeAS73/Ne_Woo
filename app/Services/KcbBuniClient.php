<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KcbBuniClient
{
    /** OAuth contract from KCB's official Getting Started guide. Never called by local simulation. */
    public function accessToken(): string
    {
        $url = config('buni.token_url');
        if (config('buni.mode') !== 'sandbox' || $url !== 'https://uat.buni.kcbgroup.com/token'
            || ! config('buni.consumer_key') || ! config('buni.consumer_secret')) {
            throw new RuntimeException('KCB BUNI sandbox authentication is not configured.');
        }
        $response = Http::asForm()->acceptJson()->withBasicAuth(config('buni.consumer_key'), config('buni.consumer_secret'))
            ->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false])
            ->post($url, ['grant_type' => 'client_credentials']);
        if (! $response->successful() || ! is_string($response->json('access_token')) || $response->json('access_token') === '') {
            // Do not expose provider response bodies or secrets to a buyer or logs.
            throw new RuntimeException('KCB BUNI authentication could not be completed.');
        }

        return $response->json('access_token');
    }
}
