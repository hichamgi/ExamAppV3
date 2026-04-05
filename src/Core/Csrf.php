<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_tokens';
    private const DEFAULT_TTL = 7200; // 2 heures

    public static function token(string $context = 'default', ?int $ttl = null): string
    {
        self::ensureSessionStarted();
        self::cleanupExpiredTokens();

        $ttl ??= self::DEFAULT_TTL;
        $now = time();

        if (
            isset($_SESSION[self::SESSION_KEY][$context]['value'], $_SESSION[self::SESSION_KEY][$context]['expires_at'])
            && (int) $_SESSION[self::SESSION_KEY][$context]['expires_at'] > $now
        ) {
            return (string) $_SESSION[self::SESSION_KEY][$context]['value'];
        }

        $token = $request->input('_csrf')
            ?? ($request->json()['_csrf'] ?? null)
            ?? $request->header('X-CSRF-Token');

        $_SESSION[self::SESSION_KEY][$context] = [
            'value' => $token,
            'created_at' => $now,
            'expires_at' => $now + max(60, $ttl),
        ];

        return $token;
    }

    public static function refresh(string $context = 'default', ?int $ttl = null): string
    {
        self::ensureSessionStarted();

        unset($_SESSION[self::SESSION_KEY][$context]);

        return self::token($context, $ttl);
    }

    public static function input(string $context = 'default', string $fieldName = '_csrf'): string
    {
        $token = self::token($context);

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    public static function meta(string $context = 'default'): string
    {
        $token = self::token($context);

        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    public static function getTokenFromRequest(Request $request, string $fieldName = '_csrf'): ?string
    {
        $token = $request->input($fieldName);

        if (is_string($token) && $token !== '') {
            return trim($token);
        }

        $headerToken = $request->header('X-CSRF-TOKEN');
        if (is_string($headerToken) && $headerToken !== '') {
            return trim($headerToken);
        }

        $headerToken = $request->header('X-Csrf-Token');
        if (is_string($headerToken) && $headerToken !== '') {
            return trim($headerToken);
        }

        return null;
    }

    public static function validate(
        string $providedToken,
        string $context = 'default',
        bool $rotateAfterSuccess = false
    ): bool {
        self::ensureSessionStarted();
        self::cleanupExpiredTokens();

        $stored = $_SESSION[self::SESSION_KEY][$context] ?? null;

        if (!is_array($stored)) {
            return false;
        }

        $expected = (string) ($stored['value'] ?? '');
        $expiresAt = (int) ($stored['expires_at'] ?? 0);

        if ($expected === '' || $expiresAt < time()) {
            unset($_SESSION[self::SESSION_KEY][$context]);
            return false;
        }

        $valid = hash_equals($expected, $providedToken);

        if ($valid && $rotateAfterSuccess) {
            self::refresh($context);
        }

        return $valid;
    }

    public static function validateRequest(
        Request $request,
        string $context = 'default',
        string $fieldName = '_csrf',
        bool $rotateAfterSuccess = false
    ): bool {
        $providedToken = self::getTokenFromRequest($request, $fieldName);

        if (!is_string($providedToken) || $providedToken === '') {
            return false;
        }

        return self::validate($providedToken, $context, $rotateAfterSuccess);
    }

    public static function assertRequest(
        Request $request,
        string $context = 'default',
        string $fieldName = '_csrf',
        bool $rotateAfterSuccess = false
    ): void {
        if (!self::validateRequest($request, $context, $fieldName, $rotateAfterSuccess)) {
            $response = new Response();
            $response->abort(419, 'Jeton CSRF invalide ou expiré.');
            exit;
        }
    }

    public static function destroy(string $context = 'default'): void
    {
        self::ensureSessionStarted();

        unset($_SESSION[self::SESSION_KEY][$context]);
    }

    public static function destroyAll(): void
    {
        self::ensureSessionStarted();

        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function cleanupExpiredTokens(): void
    {
        self::ensureSessionStarted();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
            return;
        }

        $now = time();

        foreach ($_SESSION[self::SESSION_KEY] as $context => $data) {
            if (!is_array($data)) {
                unset($_SESSION[self::SESSION_KEY][$context]);
                continue;
            }

            $expiresAt = (int) ($data['expires_at'] ?? 0);

            if ($expiresAt <= $now) {
                unset($_SESSION[self::SESSION_KEY][$context]);
            }
        }
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}