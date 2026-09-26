<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE opnavi_customers ADD FULLTEXT INDEX opnavi_customers_search_fulltext (business_name, address, sales_memo)'
        );
    }

    public function down()
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE opnavi_customers DROP INDEX opnavi_customers_search_fulltext');
    }
};
