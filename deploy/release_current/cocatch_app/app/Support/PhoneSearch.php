<?php

namespace App\Support;

class PhoneSearch
{
    public const MIN_TOKEN_LENGTH = 3;

    public static function normalize(?string $value): string
    {
        return preg_replace('/\D+/u', '', (string) $value) ?? '';
    }

    public static function tokens(?string $value): array
    {
        $normalized = self::normalize($value);
        $length = strlen($normalized);

        if ($length < self::MIN_TOKEN_LENGTH) {
            return [];
        }

        $tokens = [];
        for ($start = 0; $start < $length; $start++) {
            for ($tokenLength = self::MIN_TOKEN_LENGTH; $tokenLength <= $length - $start; $tokenLength++) {
                $tokens[] = substr($normalized, $start, $tokenLength);
            }
        }

        return array_values(array_unique($tokens));
    }
}
