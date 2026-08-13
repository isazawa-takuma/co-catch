<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('opnavi_customers', function (Blueprint $table) {
            $table->id();
            $table->date('registered_at');
            $table->string('business_name');
            $table->string('prefecture')->nullable();
            $table->string('region');
            $table->string('area_name');
            $table->string('address');
            $table->string('website_url')->nullable();
            $table->string('head_office_phone')->nullable();
            $table->string('public_phone')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('experience_title');
            $table->string('domestic_otas')->nullable();
            $table->unsignedInteger('ota_count')->default(0);
            $table->string('other_ota_names')->nullable();
            $table->string('business_scale')->nullable();
            $table->unsignedInteger('store_count')->nullable();
            $table->unsignedInteger('monthly_open_days')->nullable();
            $table->string('request_booking_status')->nullable();
            $table->text('research_notes')->nullable();
            $table->string('status')->default('未対応');
            $table->string('priority')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('last_action_at')->nullable();
            $table->string('last_action_summary')->nullable();
            $table->date('next_action_at')->nullable();
            $table->string('next_action_summary')->nullable();
            $table->text('sales_memo')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['business_name', 'address']);
            $table->index(['region', 'owner_id']);
            $table->index(['status', 'next_action_at']);
            $table->index('registered_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('opnavi_customers');
    }
};
