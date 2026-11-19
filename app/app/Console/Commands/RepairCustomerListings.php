<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Opnavi\CsvOtaListings;
use App\Services\Opnavi\CustomerImportService;
use App\Support\WebUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairCustomerListings extends Command
{
    protected $signature = 'opnavi:repair-listings {csv : 取り込みに使用したCSV} {--apply : 差分をDBに反映する}';
    protected $description = '既存顧客の掲載OTA・無効なWeb URLの差分を確認（既定は書き込みなし）';

    public function handle(CustomerImportService $import): int
    {
        $path = $this->argument('csv');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('CSVを読み取れません。');
            return self::FAILURE;
        }
        $source = $import->readImportRows($path);
        if ($source['errors']) {
            foreach ($source['errors'] as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $groups = [];
        foreach ($source['rows'] as $row) {
            $groups[serialize([trim($row['事業者名']), trim($row['住所'])])][] = $row;
        }
        $apply = (bool) $this->option('apply');
        $counts = ['差分あり' => 0, '変更なし' => 0, '未登録' => 0, '要確認' => 0];
        foreach ($groups as $rows) {
            if (count($rows) !== 1) {
                $this->warn($rows[0]['事業者名'].'：同じ事業者名・住所がCSVに複数あるため対象外');
                $counts['要確認']++;
                continue;
            }
            $result = $apply ? DB::transaction(fn () => $this->compare($rows[0], true)) : $this->compare($rows[0], false);
            $counts[$result]++;
        }
        $this->line($apply ? '反映結果' : '差分確認のみ（DB変更なし）');
        foreach ($counts as $label => $count) {
            $this->line("$label: $count");
        }

        return self::SUCCESS;
    }

    private function compare(array $row, bool $apply): string
    {
        $name = trim($row['事業者名']);
        $address = trim($row['住所']);
        $matches = Customer::where('business_name', $name)->where('address', $address)
            ->when($apply, fn ($q) => $q->lockForUpdate())->limit(2)->get();
        if ($matches->isEmpty()) {
            return '未登録';
        }
        $customer = $matches->first();
        if ($matches->count() !== 1 || $customer->business_name !== $name || $customer->address !== $address
            || $customer->domestic_otas !== trim($row['国内掲載OTA'])) {
            $this->warn("{$name}：既存顧客または掲載媒体を一意に照合できないため対象外");
            return '要確認';
        }
        $listings = CsvOtaListings::fromRow($row);
        $before = $customer->otaLinks()->orderBy('id')->get(['ota_name', 'listing_url', 'is_listed'])->toArray();
        $after = $listings['links'];
        $targetUrls = array_column($after, 'listing_url');
        foreach ($before as $link) {
            if ($link['listing_url'] !== '' && ! in_array(CsvOtaListings::cleanListingUrl($link['listing_url']), $targetUrls, true)) {
                $this->warn("{$name}：CSVと一致しない既存リンクがあるため対象外");
                return '要確認';
            }
        }
        $csvWebsite = trim($row['HP_URL'] ?? $row['Web_URL'] ?? $row['web_URL'] ?? '');
        $clearWebsite = $csvWebsite !== '' && $customer->website_url === $csvWebsite && WebUrl::optional($csvWebsite) === null;
        $linksChanged = $before !== $after;
        if (! $linksChanged && (int) $customer->ota_count === $listings['count'] && ! $clearWebsite) {
            return '変更なし';
        }
        $this->line("#{$customer->id} {$name}：掲載OTA数 {$customer->ota_count} → {$listings['count']}");
        if ($linksChanged) {
            foreach (['変更前' => $before, '変更後' => $after] as $label => $links) {
                $this->line('  '.$label.': '.json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
        if ($clearWebsite) {
            $this->line("  Web URL: $csvWebsite → 未登録");
        }
        if ($apply) {
            if ($linksChanged) {
                $customer->otaLinks()->delete();
                $customer->otaLinks()->createMany($after);
            }
            $customer->ota_count = $listings['count'];
            if ($clearWebsite) {
                $customer->website_url = null;
            }
            $customer->save();
        }

        return '差分あり';
    }
}
