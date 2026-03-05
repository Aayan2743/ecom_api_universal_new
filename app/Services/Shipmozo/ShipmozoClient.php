<?php
namespace App\Services\Shipmozo;

use Illuminate\Support\Facades\Http;

class ShipmozoClient
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $secret;

    public function __construct()
    {
        if (! config('services.shipping.shipmozo.enabled')) {
            throw new \Exception('Shipmozo is disabled');
        }

        $this->baseUrl = rtrim(config('services.shipping.shipmozo.base_url'), '/');
        $this->apiKey  = config('services.shipping.shipmozo.api_key');
        $this->secret  = config('services.shipping.shipmozo.secret');

        if (! $this->baseUrl || ! $this->apiKey) {
            throw new \Exception('Shipmozo configuration missing');
        }
    }

    /* ================= GENERIC REQUEST ================= */

    protected function request(string $method, string $uri, array $data = [])
    {
        $response = Http::withHeaders([
            'public-key'  => $this->apiKey,
            'private-key' => $this->secret,
            'Accept'      => 'application/json',
        ])->{$method}($this->baseUrl . $uri, $data);

        if (! $response->successful()) {

            \Log::error('Shipmozo API Error', [
                'url'         => $this->baseUrl . $uri,
                'payload'     => $data,
                'status'      => $response->status(),
                'public_key'  => $this->apiKey,
                'private_key' => $this->secret,

                'response'    => $response->body(),
            ]);

            throw new \Exception($response->body());
        }

        return $response->json();
    }

    /* ================= CREATE ORDER ================= */

    public function createOrder(array $payload)
    {
        return $this->request('post', '/push-order', $payload);
    }

    /* ================= TRACK ORDER ================= */
    public function trackOrder(string $trackingId)
    {
        return $this->request('get', '/track/' . $trackingId);
    }
}
