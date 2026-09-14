<?php
declare(strict_types=1);

namespace App\Auth;

use JsonException;
use RuntimeException;

/**
 * The two HTTP calls the OAuth flow needs. Kept apart from the providers
 * so they read as OAuth rather than as cURL.
 */
final class Http
{
    private const TIMEOUT = 10;

    /** @param array<string, string> $fields */
    public static function postForm(string $url, array $fields, array $headers = []): array
    {
        return self::send($url, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
        ], array_merge(['Accept: application/json'], $headers));
    }

    public static function getJson(string $url, array $headers = []): array
    {
        return self::send($url, [], array_merge(['Accept: application/json'], $headers));
    }

    private static function send(string $url, array $options, array $headers): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
            // GitHub's API rejects requests with no User-Agent outright.
            CURLOPT_USERAGENT      => 'Routeler',
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("Request to {$url} failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Request to {$url} returned HTTP {$status}");
        }

        try {
            $decoded = json_decode((string) $body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Response from {$url} was not JSON");
        }

        if (!is_array($decoded)) {
            throw new RuntimeException("Response from {$url} was not a JSON object");
        }

        return $decoded;
    }
}
