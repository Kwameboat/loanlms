<?php

namespace App\Services\Notification;

interface SmsProviderInterface
{
    public function send(string $phone, string $message): bool;
}

// ─── Log Provider (default / testing) ────────────────────────────────────────
class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        \Illuminate\Support\Facades\Log::channel('daily')->info('SMS [LOG]', [
            'to'      => $phone,
            'message' => $message,
        ]);
        return true;
    }
}

// ─── Arkesel (popular in Ghana) ───────────────────────────────────────────────
class ArkeselSmsProvider implements SmsProviderInterface
{
    protected string $apiKey;
    protected string $senderId;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey   = config('bigcash.sms.api_key', '');
        $this->senderId = config('bigcash.sms.sender_id', 'Big Cash');
        $this->apiUrl   = config('bigcash.sms.api_url', 'https://sms.arkesel.com/sms/api');
    }

    public function send(string $phone, string $message): bool
    {
        $phone = $this->formatGhanaPhone($phone);
        try {
            $response = \Illuminate\Support\Facades\Http::get($this->apiUrl, [
                'action'  => 'send-sms',
                'api_key' => $this->apiKey,
                'to'      => $phone,
                'from'    => $this->senderId,
                'sms'     => $message,
            ]);
            return $response->successful() && ($response->json('code') === 'ok');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Arkesel SMS error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function formatGhanaPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '233' . substr($phone, 1);
        }
        if (!str_starts_with($phone, '233')) {
            $phone = '233' . $phone;
        }
        return $phone;
    }
}

// ─── Hubtel ───────────────────────────────────────────────────────────────────
class HubtelSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        $clientId     = config('bigcash.sms.api_key', '');
        $clientSecret = config('bigcash.sms.api_secret', '');
        $senderId     = config('bigcash.sms.sender_id', 'Big Cash');

        $phone = $this->formatGhanaPhone($phone);

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($clientId, $clientSecret)
                ->post('https://smsc.hubtel.com/v1/messages/send', [
                    'From'    => $senderId,
                    'To'      => $phone,
                    'Content' => $message,
                ]);
            return $response->successful();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Hubtel SMS error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function formatGhanaPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) $phone = '233' . substr($phone, 1);
        if (!str_starts_with($phone, '233')) $phone = '233' . $phone;
        return '+' . $phone;
    }
}

// ─── mNotify (Ghana-based) ────────────────────────────────────────────────────
class MnotifySmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        $apiKey   = config('bigcash.sms.api_key', '');
        $senderId = config('bigcash.sms.sender_id', 'Big Cash');

        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) $phone = '233' . substr($phone, 1);

        try {
            $response = \Illuminate\Support\Facades\Http::post('https://apps.mnotify.net/smsapi', [
                'key'         => $apiKey,
                'to'          => $phone,
                'msg'         => $message,
                'sender_id'   => $senderId,
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('mNotify SMS error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
