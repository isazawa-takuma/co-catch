<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('opnavi_activities', function (Blueprint $table) {
            $table->string('rank', 1)->nullable()->after('user_id');
        });
    }

    public function down()
    {
        Schema::table('opnavi_activities', function (Blueprint $table) {
            $table->dropColumn('rank');
        });
    }
};
