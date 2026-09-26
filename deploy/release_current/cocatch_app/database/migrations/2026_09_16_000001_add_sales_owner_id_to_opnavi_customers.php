<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('opnavi_customers', function (Blueprint $table) {
            $table->foreignId('sales_owner_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('opnavi_customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_owner_id');
        });
    }
};
