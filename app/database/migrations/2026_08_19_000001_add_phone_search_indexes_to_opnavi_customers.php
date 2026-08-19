<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PHONE_COLUMNS = [
        'head_office_phone',
        'public_phone',
        'contact_phone',
    ];

    private const MIN_TOKEN_LENGTH = 3;

    public function up()
    {
        Schema::table('opnavi_customers', function (Blueprint $table) {
            $table->string('head_office_phone_normalized', 32)->nullable()->after('head_office_phone');
            $table->string('public_phone_normalized', 32)->nullable()->after('public_phone');
            $table->string('contact_phone_normalized', 32)->nullable()->after('contact_phone');

            $table->index('head_office_phone_normalized', 'opnavi_customers_head_phone_normalized_idx');
            $table->index('public_phone_normalized', 'opnavi_customers_public_phone_normalized_idx');
            $table->index('contact_phone_normalized', 'opnavi_customers_contact_phone_normalized_idx');
        });

        Schema::create('opnavi_customer_phone_indexes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('opnavi_customers')->cascadeOnDelete();
            $table->string('phone_column', 32);
            $table->string('phone_token', 32);

            $table->index('phone_token', 'opnavi_customer_phone_indexes_token_idx');
            $table->index('customer_id', 'opnavi_customer_phone_indexes_customer_idx');
            $table->unique(['customer_id', 'phone_column', 'phone_token'], 'opnavi_customer_phone_indexes_unique');
        });

        $this->backfillPhoneSearchData();
    }

    public function down()
    {
        Schema::dropIfExists('opnavi_customer_phone_indexes');

        Schema::table('opnavi_customers', function (Blueprint $table) {
            $table->dropIndex('opnavi_customers_head_phone_normalized_idx');
            $table->dropIndex('opnavi_customers_public_phone_normalized_idx');
            $table->dropIndex('opnavi_customers_contact_phone_normalized_idx');
            $table->dropColumn([
                'head_office_phone_normalized',
                'public_phone_normalized',
                'contact_phone_normalized',
            ]);
        });
    }

    private function backfillPhoneSearchData(): void
    {
        DB::table('opnavi_customers')
            ->select(array_merge(['id'], self::PHONE_COLUMNS))
            ->orderBy('id')
            ->chunkById(500, function ($customers) {
                foreach ($customers as $customer) {
                    $updates = [];
                    $indexRows = [];

                    foreach (self::PHONE_COLUMNS as $column) {
                        $normalized = $this->normalizePhone($customer->{$column});
                        $updates[$column.'_normalized'] = $normalized === '' ? null : $normalized;

                        foreach ($this->phoneTokens($normalized) as $token) {
                            $indexRows[] = [
                                'customer_id' => $customer->id,
                                'phone_column' => $column,
                                'phone_token' => $token,
                            ];
                        }
                    }

                    DB::table('opnavi_customers')
                        ->where('id', $customer->id)
                        ->update($updates);

                    if ($indexRows !== []) {
                        DB::table('opnavi_customer_phone_indexes')->insertOrIgnore($indexRows);
                    }
                }
            });
    }

    private function normalizePhone(?string $value): string
    {
        return preg_replace('/\D+/u', '', (string) $value) ?? '';
    }

    private function phoneTokens(string $normalized): array
    {
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
};
