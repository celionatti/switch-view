<?php

declare(strict_types=1);

namespace Switch\View\Security;

class SecurityHelper
{
    private static ?string $csrfToken = null;
    private static ?string $cspNonce = null;

    /**
     * Get or generate a CSRF token stored in session if available.
     */
    public static function getCsrfToken(): string
    {
        if (self::$csrfToken !== null) {
            return self::$csrfToken;
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['_csrf_token'])) {
            self::$csrfToken = (string) $_SESSION['_csrf_token'];
            return self::$csrfToken;
        }

        $token = bin2hex(random_bytes(32));

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_csrf_token'] = $token;
        }

        self::$csrfToken = $token;
        return self::$csrfToken;
    }

    /**
     * Explicitly set the CSRF token (e.g. from session middleware).
     */
    public static function setCsrfToken(string $token): void
    {
        self::$csrfToken = $token;
    }

    /**
     * Generate HTML hidden input field for CSRF.
     */
    public static function csrfField(): string
    {
        $token = self::getCsrfToken();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Generate Honeypot hidden fields for bot/brute force protection.
     */
    public static function honeypot(string $name = 'my_name_hp', string $timeName = 'my_time_hp'): string
    {
        $timestamp = time();
        $encryptedTime = base64_encode((string) $timestamp);

        return '<div style="display:none !important; visibility:hidden !important; opacity:0 !important; position:absolute !important; left:-9999px !important;" aria-hidden="true">'
            . '<input type="text" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="" tabindex="-1" autocomplete="off">'
            . '<input type="hidden" name="' . htmlspecialchars($timeName, ENT_QUOTES, 'UTF-8') . '" value="' . $encryptedTime . '">'
            . '</div>';
    }

    /**
     * Get or generate a CSP Nonce for inline scripts and styles.
     */
    public static function getCspNonce(): string
    {
        if (self::$cspNonce === null) {
            self::$cspNonce = base64_encode(random_bytes(16));
        }

        return self::$cspNonce;
    }

    /**
     * Sanitize untrusted HTML to prevent XSS attacks.
     */
    public static function cleanHtml(string $html): string
    {
        // Strip dangerous tags: <script>, <iframe>, <object>, <embed>, <applet>, <meta>, <style>, <link>, <form>
        $clean = preg_replace('/<(script|iframe|object|embed|applet|meta|style|link|form)[^>]*?>.*?<\/\\1>/si', '', $html) ?? $html;
        $clean = preg_replace('/<(script|iframe|object|embed|applet|meta|style|link|form)[^>]*?\/?>/si', '', $clean) ?? $clean;

        // Strip inline JavaScript event handlers (e.g. onload=, onclick=, onerror=)
        $clean = preg_replace('/on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $clean) ?? $clean;

        // Strip javascript: protocols in href, src, etc.
        $clean = preg_replace('/(href|src|data)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '$1="#"', $clean) ?? $clean;

        return $clean;
    }

    /**
     * Safe JSON encoder preventing script tag breakout and XSS in script tags.
     */
    public static function safeJson(mixed $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
