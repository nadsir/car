<?php

namespace App\Services\Sms;

use App\Contracts\SmsServiceInterface;
use Illuminate\Support\Facades\Http;

// Generic adapter for an SMS relay accepting {mobile, message} JSON.
class HttpSmsService implements SmsServiceInterface
{
    public function sendOtp(string $mobile, string $code): void
    {
        $url = config('sms.endpoint');
        $token = config('sms.token');
        if (!$url || !str_starts_with($url, 'https://') || !$token) {
            throw new \RuntimeException('HTTPS SMS endpoint and token must be configured.');
        }
        $response = Http::withToken($token)->acceptJson()->connectTimeout(3)->timeout(8)
            ->withOptions(['allow_redirects' => false])
            ->post($url, [
                'mobile' => $mobile,
                'message' => "کد ورود شما: {$code}\nاعتبار: ۲ دقیقه. این کد را در اختیار دیگران قرار ندهید.",
            ])->throw();
        if (!$response->successful()) {
            throw new \RuntimeException('SMS relay rejected delivery.');
        }
    }
}
