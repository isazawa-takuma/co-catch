<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('opnavi_ota_links', function (Blueprint $table) {
            $table->boolean('is_listed')->default(true);
        });
    }

    public function down()
    {
        Schema::table('opnavi_ota_links', function (Blueprint $table) {
            $table->dropColumn('is_listed');
        });
    }
};
