<?php
namespace App\Services\Shiprocket;

class ShiprocketOrderService extends ShiprocketClient
{
    /* 📦 CREATE ORDER */
    public function create(array $payload)
    {
        return $this->request(
            'post',
            '/orders/create/adhoc',
            $payload
        );
    }

    /* ❌ CANCEL ORDER */
    public function cancel(int $orderId)
    {
        return $this->request(
            'post',
            '/orders/cancel',
            ['ids' => [$orderId]]
        );
    }
}
