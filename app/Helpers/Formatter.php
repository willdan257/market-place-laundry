<?php

namespace App\Helpers;

class Formatter
{
    /**
     * Normalize Indonesian phone numbers to standard 628xxxxxxxx format.
     * Examples:
     * - "0812-3456-7890"  -> "6281234567890"
     * - "+62 812 3456"    -> "628123456"
     * - "628123456"       -> "628123456"
     * - "81234567890"     -> "6281234567890"
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        // Keep digits and leading +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        if (str_starts_with($cleaned, '+62')) {
            $cleaned = '62' . substr($cleaned, 3);
        } elseif (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62' . $cleaned;
        }

        // Strip any remaining non-digit characters
        $normalized = preg_replace('/\D/', '', $cleaned);

        return $normalized;
    }

    /**
     * Validate Indonesian phone number (10 to 15 digits starting with 628).
     */
    public static function isValidPhone(?string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }
        $normalized = self::normalizePhone($phone);
        // Valid Indonesian mobile numbers start with 628 and have 10-15 digits total
        return (bool) preg_match('/^628[0-9]{7,12}$/', $normalized ?? '');
    }

    /**
     * Normalize email string (trim & lowercase).
     * Jika email dummy tanpa domain (contoh: 'budi123'), otomatis ditambahkan '@example.com' agar tetap valid.
     */
    public static function normalizeEmail(?string $email): string
    {
        $normalized = strtolower(trim((string) $email));
        if ($normalized !== '' && !str_contains($normalized, '@')) {
            $normalized .= '@example.com';
        }
        return $normalized;
    }

    /**
     * Validate email (mendukung email resmi maupun email dummy/palsu untuk testing).
     */
    public static function isValidEmail(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }
        $normalized = self::normalizeEmail($email);
        return (bool) preg_match('/^[^\s@]+@[^\s@]+$/', $normalized);
    }

    /**
     * Validate password: bebas berapa karakter (minimal 1 karakter / tidak kosong).
     */
    public static function isValidPassword(string $password): bool
    {
        return strlen($password) >= 1;
    }
}
