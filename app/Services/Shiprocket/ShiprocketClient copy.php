<?php
namespace App\Services\Shiprocket;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ShiprocketClient
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.shiprocket.base_url');
        $this->token   = $this->getToken();
    }

    /* 🔐 LOGIN (CACHED) */
    protected function getToken(): string
    {
        return Cache::remember('shiprocket_token', 3500, function () {
            $res = Http::post($this->baseUrl . '/auth/login', [
                'email'    => config('services.shiprocket.email'),
                'password' => config('services.shiprocket.password'),
            ]);

            if (! $res->successful()) {
                throw new \Exception('Shiprocket authentication failed');
            }

            return $res->json('token');
        });
    }

    /* 🌐 GENERIC REQUEST */
    protected function request(string $method, string $uri, array $data = [])
    {
        $response = Http::withToken($this->token)
            ->{$method}($this->baseUrl . $uri, $data);

        if (! $response->successful()) {
            throw new \Exception(
                $response->json('message') ?? 'Shiprocket API error'
            );
        }

        return $response->json();
    }
}
