<?php
namespace App\Services;

use Exception;

class Messenger360Service
{
    protected string $url;
    protected string $token;

    public function __construct()
    {
        $this->url   = config('services.whatsapp.base_url');
        $this->token = config('services.whatsapp.api_key');
    }

    /**
     * Send WhatsApp message
     *
     * @param string      $phone   (example: 447488888888)
     * @param string      $text
     * @param string|null $mediaUrl
     * @param string|null $delay   (MM-DD-YYYY HH:MM in GMT)
     */
    // public function send(
    //     string $phone,
    //     string $text,
    //     ?string $mediaUrl = null,
    //     ?string $delay = null
    // ): array {
    //     $postFields = [
    //         'phonenumber' => $phone,
    //         'text'        => $text,
    //     ];

    //     if ($mediaUrl) {
    //         $postFields['url'] = $mediaUrl;
    //     }

    //     if ($delay) {
    //         $postFields['delay'] = $delay;
    //     }

    //     $ch = curl_init();

    //     curl_setopt_array($ch, [
    //         CURLOPT_URL            => $this->url,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_CUSTOMREQUEST  => 'POST',
    //         CURLOPT_POSTFIELDS     => $postFields,
    //         CURLOPT_HTTPHEADER     => [
    //             'Authorization: Bearer ' . $this->token,
    //         ],
    //         CURLOPT_TIMEOUT        => 30,
    //     ]);

    //     $response = curl_exec($ch);

    //     if (curl_errno($ch)) {
    //         throw new Exception(curl_error($ch));
    //     }

    //     curl_close($ch);

    //     return json_decode($response, true) ?? ['success' => false, 'raw' => $response];
    // }

    public function send(
        string $phone,
        string $text,
        ?string $mediaUrl = null,
        ?string $delay = null
    ): array {

        // 🔥 CHECK ENABLE FIRST
        if (! config('services.whatsapp.enabled')) {
            return [
                'success' => false,
                'message' => 'WhatsApp service is disabled',
            ];
        }

        // 🔥 CHECK REQUIRED CONFIG
        if (! $this->url || ! $this->token) {
            return [
                'success' => false,
                'message' => 'WhatsApp configuration missing',
            ];
        }

        $postFields = [
            'phonenumber' => $phone,
            'text'        => $text,
        ];

        if ($mediaUrl) {
            $postFields['url'] = $mediaUrl;
        }

        if ($delay) {
            $postFields['delay'] = $delay;
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true) ?? [
            'success' => false,
            'raw'     => $response,
        ];
    }
}