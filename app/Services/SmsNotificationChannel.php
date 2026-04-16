<?php

namespace App\Services;

use Illuminate\Notifications\Notification;

/**
 * SMS notification channel.
 *
 * Supports multiple providers via SMS_PROVIDER env var:
 * - vonage (default)
 * - twilio
 * - log (development)
 *
 * Notifications that want SMS delivery should include 'sms' in
 * their via() array and implement a toSms() method returning
 * the message text string.
 */
class SmsNotificationChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable->routeNotificationFor('sms') ?? $notifiable->phone ?? null;

        if (! $phone) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if (empty($message)) {
            return;
        }

        $provider = config('services.sms.provider', 'log');

        match ($provider) {
            'vonage'  => $this->sendViaVonage($phone, $message),
            'twilio'  => $this->sendViaTwilio($phone, $message),
            default   => $this->sendViaLog($phone, $message),
        };
    }

    private function sendViaVonage(string $to, string $message): void
    {
        $apiKey    = config('services.vonage.key');
        $apiSecret = config('services.vonage.secret');
        $from      = config('services.vonage.sms_from', 'TriLink');

        if (! $apiKey || ! $apiSecret) {
            $this->sendViaLog($to, $message);
            return;
        }

        $ch = curl_init('https://rest.nexmo.com/sms/json');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'api_key'    => $apiKey,
                'api_secret' => $apiSecret,
                'from'       => $from,
                'to'         => preg_replace('/\D/', '', $to),
                'text'       => $message,
                'type'       => 'unicode',
            ]),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        logger()->info('SMS sent via Vonage', ['to' => $to, 'response' => $response]);
    }

    private function sendViaTwilio(string $to, string $message): void
    {
        $sid   = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from  = config('services.twilio.from');

        if (! $sid || ! $token || ! $from) {
            $this->sendViaLog($to, $message);
            return;
        }

        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "{$sid}:{$token}",
            CURLOPT_POSTFIELDS     => http_build_query([
                'From' => $from,
                'To'   => $to,
                'Body' => $message,
            ]),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        logger()->info('SMS sent via Twilio', ['to' => $to, 'response' => $response]);
    }

    private function sendViaLog(string $to, string $message): void
    {
        logger()->info('SMS (log driver)', ['to' => $to, 'message' => $message]);
    }
}
