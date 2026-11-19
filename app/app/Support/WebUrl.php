<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;

class WebUrl
{
    public static function optional(?string $value): ?string
    {
        $value = trim((string) $value);
        if (! preg_match('~^https?://~i', $value) || str_contains($value, '\\')) {
            return null;
        }

        return Validator::make(['url' => $value], ['url' => 'url|max:1000'])->passes() ? $value : null;
    }
}
