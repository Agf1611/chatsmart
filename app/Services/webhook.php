<?php
namespace App\Services;

class Webhook {
  public static function send(array $payload): void {
    $url = trim(env('WEBHOOK', ''));
    if ($url === '') return;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 7,
    ]);
    curl_exec($ch);
    curl_close($ch);
  }
}
