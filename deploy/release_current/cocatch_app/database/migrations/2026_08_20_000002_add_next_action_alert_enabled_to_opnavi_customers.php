<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('opnavi_customers', function (Blueprint $table) {
            $table->boolean('next_action_alert_enabled')->default(false)->after('next_action_at');
            $table->index(['next_action_alert_enabled', 'next_action_at'], 'opnavi_customers_next_action_alert_idx');
        });
    }

    public function down()
    {
        Schema::table('opnavi_customers', function (Blueprint $table) {
            $table->dropIndex('opnavi_customers_next_action_alert_idx');
            $table->dropColumn('next_action_alert_enabled');
        });
    }
};
