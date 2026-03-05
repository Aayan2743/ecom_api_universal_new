<?php
namespace App\Services\Shiprocket;

class ShiprocketTrackingService extends ShiprocketClient
{
    public function track(string $awb)
    {
        return $this->request(
            'get',
            "/courier/track/awb/$awb"
        );
    }
}
