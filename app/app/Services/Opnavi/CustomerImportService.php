<?php

namespace App\Services\Opnavi;

use App\Models\Customer;
use App\Models\User;
use App\Support\WebUrl;
use Illuminate\Support\Facades\DB;

class CustomerImportService
{
    private const REQUIRED_COLUMNS = [
        '登録日',
        '事業者名',
        '地域',
        '店舗',
        '住所',
        '電話番号（OTA公開）',
        '体験内容',
        '国内掲載OTA',
        '営業日数(1ヶ月)',
        'リクエスト予約',
        'OTA_URL（メイン4社）',
        'ステータス',
        '担当者',
    ];

    private const REQUIRED_VALUES = [
        '登録日',
        '事業者名',
        '地域',
        '店舗',
        '住所',
        '体験内容',
        '国内掲載OTA',
        '営業日数(1ヶ月)',
        'リクエスト予約',
    ];

    private const IMPORT_COLUMNS = [
        '登録日',
        '事業者名',
        '地域',
        '店舗',
        '住所',
        'HP_URL',
        'Web_URL',
        'web_URL',
        '電話番号（本社）',
        '電話番号（OTA公開）',
        '体験内容',
        '国内掲載OTA',
        'OTA_URL（メイン4社）',
        'その他OTA名',
        '事業者規模',
        '店舗数',
        '営業日数(1ヶ月)',
        'リクエスト予約',
        '備考',
        'ステータス',
        '担当者',
    ];

    public function import(string $path, string $originalName, bool $confirmDuplicates): array
    {
        $source = $this->readImportRows($path);
        if ($source['errors']) {
            return ['errors' => $source['errors']];
        }

        $duplicates = [];
        $prepared = [];

        foreach ($source['rows'] as $line => $row) {
            $listings = CsvOtaListings::fromRow($row);
            $customerData = $this->mapCsvRowToCustomer($row, $listings['count']);
            if ($this->hasDuplicateCustomer($customerData)) {
                $duplicates[] = $line.'行目: '.$customerData['business_name'].' / '.$customerData['address'];
            }

            $prepared[] = [$customerData, $listings['links']];
        }

        if ($duplicates && ! $confirmDuplicates) {
            return [
                'errors' => array_merge(['重複候補があります。更新して取り込む場合はチェックを入れて再実行してください。'], $duplicates),
            ];
        }

        $this->savePreparedRows($prepared);

        $message = $originalName.'のインポートに成功しました';
        if ($source['warnings']) {
            $message .= '（営業日数12日未満のため'.count($source['warnings']).'件をスキップしました）';
        }

        return [
            'message' => $message,
            'warnings' => $source['warnings'],
        ];
    }

    public function readImportRows(string $path): array
    {
        [$headers, $rows] = $this->readCsv($path);
        $missing = array_diff(self::REQUIRED_COLUMNS, $headers);
        $errors = $missing ? ['CSVの必須列が不足しています: '.implode(', ', $missing)] : [];
        $warnings = [];
        $eligible = [];
        if (! $missing) {
            foreach ($rows as $line => $row) {
                if ($this->isBlankImportRow($row) || $this->shouldSkipByOpenDays($row, $line, $warnings)) {
                    continue;
                }
                $empty = $this->emptyRequiredColumns($row);
                if ($empty) {
                    $errors[] = $line.'行目: '.implode(', ', $empty).' が空です';
                } else {
                    $eligible[$line] = $row;
                }
            }
        }

        return ['rows' => $eligible, 'warnings' => $warnings, 'errors' => $errors];
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $headers = array_map(fn ($header) => preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header)), $headers ?: []);
        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($values, fn ($value) => ! $this->isBlank($value))) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '' || array_key_exists($header, $row)) {
                    continue;
                }

                $row[$header] = $values[$index] ?? '';
            }
            $rows[$line] = $row;
        }

        fclose($handle);

        return [$headers, $rows];
    }

    private function shouldSkipByOpenDays(array $row, int $line, array &$warnings): bool
    {
        $monthlyOpenDaysRaw = $row['営業日数(1ヶ月)'] ?? '';
        if ($this->isBlank($monthlyOpenDaysRaw)) {
            return false;
        }

        $monthlyOpenDays = $this->toInteger($monthlyOpenDaysRaw);
        if ($monthlyOpenDays >= 12) {
            return false;
        }

        $businessName = $this->isBlank($row['事業者名'] ?? '') ? '事業者名未入力' : trim($row['事業者名']);
        $warnings[] = $line.'行目: '.$businessName.'（営業日数: '.$monthlyOpenDays.'日）';

        return true;
    }

    private function emptyRequiredColumns(array $row): array
    {
        $empty = [];
        foreach (self::REQUIRED_VALUES as $column) {
            if ($this->isBlank($row[$column] ?? '')) {
                $empty[] = $column;
            }
        }

        return $empty;
    }

    private function mapCsvRowToCustomer(array $row, int $otaCount): array
    {
        $owner = null;
        if (trim((string) ($row['担当者'] ?? '')) !== '') {
            $ownerName = trim($row['担当者']);
            $owner = User::firstOrCreate(
                ['name' => $ownerName],
                ['email' => 'user-'.substr(md5($ownerName), 0, 10).'@example.local', 'password' => bcrypt('password'), 'is_active' => true]
            );
        }

        $domesticOtas = trim((string) ($row['国内掲載OTA'] ?? ''));

        return [
            'registered_at' => $this->normalizeDate($row['登録日']),
            'business_name' => trim($row['事業者名']),
            'prefecture' => $this->guessPrefecture($row['地域'] ?? $row['住所']),
            'region' => trim($row['地域']),
            'area_name' => trim($row['店舗']),
            'address' => trim($row['住所']),
            'website_url' => WebUrl::optional($row['HP_URL'] ?? $row['Web_URL'] ?? $row['web_URL'] ?? null),
            'head_office_phone' => $this->nullable($row['電話番号（本社）'] ?? ''),
            'public_phone' => $this->nullable($row['電話番号（OTA公開）'] ?? ''),
            'experience_title' => trim($row['体験内容']),
            'domestic_otas' => $domesticOtas,
            'ota_count' => $otaCount,
            'store_count' => $this->nullableInteger($row['店舗数'] ?? ''),
            'monthly_open_days' => $this->toInteger($row['営業日数(1ヶ月)']),
            'request_booking_status' => trim($row['リクエスト予約']),
            'status' => in_array(trim((string) ($row['ステータス'] ?? '')), Customer::STATUSES, true) ? trim($row['ステータス']) : '未対応',
            'owner_id' => $owner?->id,
        ];
    }

    private function hasDuplicateCustomer(array $customerData): bool
    {
        return Customer::where('business_name', $customerData['business_name'])
            ->where('address', $customerData['address'])
            ->exists();
    }

    private function savePreparedRows(array $prepared): void
    {
        DB::transaction(function () use ($prepared) {
            foreach ($prepared as [$customerData, $otaLinks]) {
                $customer = Customer::firstOrNew([
                    'business_name' => $customerData['business_name'],
                    'address' => $customerData['address'],
                ]);

                if ($customer->exists) {
                    unset($customerData['status']);
                }

                $customer->fill($customerData);
                $customer->save();

                $customer->otaLinks()->delete();
                foreach ($otaLinks as $link) {
                    $customer->otaLinks()->create($link);
                }
            }
        });
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        foreach (['Y/m/d', 'Y-m-d', 'Y.n.j', 'Y年n月j日'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        return date('Y-m-d', strtotime($value));
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function guessPrefecture(string $value): ?string
    {
        if (preg_match('/(北海道|東京都|京都府|大阪府|.{2,3}県)/u', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function toInteger(string $value): int
    {
        $number = preg_replace('/[^0-9]/', '', $value);

        return $number === '' ? 0 : (int) $number;
    }

    private function nullableInteger(string $value): ?int
    {
        if ($this->isBlank($value)) {
            return null;
        }

        return $this->toInteger($value);
    }

    private function isBlankImportRow(array $row): bool
    {
        foreach (self::IMPORT_COLUMNS as $column) {
            if (! $this->isBlank($row[$column] ?? '')) {
                return false;
            }
        }

        return true;
    }

    private function isBlank(mixed $value): bool
    {
        return preg_replace('/[\s　]+/u', '', (string) $value) === '';
    }
}
