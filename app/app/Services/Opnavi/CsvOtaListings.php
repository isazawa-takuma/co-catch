<?php

namespace App\Services\Opnavi;

use App\Support\WebUrl;

class CsvOtaListings
{
    private const HOSTS = [
        'jalan.net' => 'じゃらん',
        'asoview.com' => 'アソビュー！',
        'activityjapan.com' => 'Activity Japan',
        'rakuten.co.jp' => '楽天トラベル',
        'travel.rakuten.com' => '楽天トラベル',
    ];

    public static function fromRow(array $row): array
    {
        $others = self::names($row['その他OTA名'] ?? '');
        $slots = [];
        $otherIndex = 0;
        foreach (self::names($row['国内掲載OTA']) as $name) {
            $key = self::key($name);
            if (preg_match('/^その他\s*(\d*)$/u', mb_convert_kana($name, 'n'), $match)) {
                $number = $match[1] === '' ? $otherIndex + 1 : (int) $match[1];
                $otherIndex = max($otherIndex, $number);
                $key = 'その他'.$number;
                $name = $others[$number - 1] ?? $key;
            }
            $slots[$key] = ['name' => $name, 'urls' => []];
        }

        $references = [];
        $parts = preg_split('/\R|[;；]\s*(?=(?:[^;；\r\n:=：＝]{1,64}[:=：＝]\s*)?https?:\/\/)/iu', $row['OTA_URL（メイン4社）'] ?? '');
        foreach ($parts as $part) {
            if (! preg_match('~^\s*(?:(?!https?://)([^:=：＝]+?)\s*[:=：＝]\s*)?(https?://[^\s<>"【（。]+)~iu', $part, $match)) {
                continue;
            }
            $url = self::cleanListingUrl($match[2]);
            if ($url === null) {
                continue;
            }
            $provider = self::hostProvider($url) ?? trim($match[1]);
            $key = self::key($provider);
            if (! isset($slots[$key])) {
                foreach ($slots as $slotKey => $slot) {
                    if (self::key($slot['name']) === $key) {
                        $key = $slotKey;
                        break;
                    }
                }
            }
            if (isset($slots[$key])) {
                $slots[$key]['urls'][$url] = $url;
            } else {
                $references[$url] = [
                    'ota_name' => $provider !== '' ? $provider : parse_url($url, PHP_URL_HOST),
                    'listing_url' => $url,
                    'is_listed' => false,
                ];
            }
        }

        $links = [];
        foreach ($slots as $slot) {
            // An OTA can have several listings or be declared without a URL.
            foreach ($slot['urls'] ?: [''] as $url) {
                $links[] = ['ota_name' => $slot['name'], 'listing_url' => $url, 'is_listed' => true];
            }
        }

        return ['count' => count($slots), 'links' => array_merge($links, array_values($references))];
    }

    private static function names(string $value): array
    {
        $parts = preg_split('/[,，、;；\r\n]+/u', $value);

        return array_values(array_filter(array_map(fn ($name) => preg_replace('/^\s+|\s+$/u', '', $name), $parts), 'strlen'));
    }

    private static function key(string $name): string
    {
        return mb_strtolower(preg_replace('/[!！]+$/u', '', trim($name)));
    }

    private static function hostProvider(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::HOSTS as $suffix => $name) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return $name;
            }
        }

        return null;
    }

    public static function cleanListingUrl(string $value): ?string
    {
        $url = preg_replace('/[;；、。]+$/u', '', trim($value));
        if (WebUrl::optional($url) === null) {
            return null;
        }
        [$beforeFragment, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        [$base, $query] = array_pad(explode('?', $beforeFragment, 2), 2, null);
        if ($query === null) {
            return $url;
        }
        // Keep the original encoding, repeated keys, order and fragment of the actual listing.
        $params = array_filter(explode('&', $query), fn ($p) => strcasecmp(urldecode($p), 'utm_source=chatgpt.com') !== 0);

        return $base.($params ? '?'.implode('&', $params) : '').($fragment !== null ? '#'.$fragment : '');
    }
}
