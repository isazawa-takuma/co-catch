<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('opnavi_activities', function (Blueprint $table) {
            $table->index(['customer_id', 'action_at', 'id'], 'opnavi_activities_latest_lookup_idx');
            $table->index(['customer_id', 'rank', 'id'], 'opnavi_activities_customer_rank_idx');
        });
    }

    public function down()
    {
        Schema::table('opnavi_activities', function (Blueprint $table) {
            $table->dropIndex('opnavi_activities_customer_rank_idx');
            $table->dropIndex('opnavi_activities_latest_lookup_idx');
        });
    }
};
