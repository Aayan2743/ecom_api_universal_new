<?php
namespace App\Services\Shiprocket;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ShiprocketClient
{
    protected string $baseUrl;
    protected string $email;
    protected string $password;
    protected ?string $token = null;

    public function __construct()
    {
        // Check if enabled
        if (! config('services.shipping.shiprocket.enabled')) {
            throw new \Exception('Shiprocket is disabled');
        }

        $this->baseUrl  = config('services.shipping.shiprocket.base_url');
        $this->email    = config('services.shipping.shiprocket.email');
        $this->password = config('services.shipping.shiprocket.password');

        if (! $this->baseUrl || ! $this->email || ! $this->password) {
            throw new \Exception('Shiprocket configuration missing');
        }

        $this->token = $this->getToken();
    }

    /* 🔐 LOGIN (CACHED) */
    protected function getToken(): string
    {
        return Cache::remember('shiprocket_token', 3500, function () {

            $res = Http::post($this->baseUrl . '/auth/login', [
                'email'    => $this->email,
                'password' => $this->password,
            ]);

            if (! $res->successful()) {
                throw new \Exception(
                    $res->json('message') ?? 'Shiprocket authentication failed'
                );
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

    /* 🚚 Example Method */
    public function createOrder(array $payload)
    {
        return $this->request('post', '/orders/create/adhoc', $payload);
    }
}
