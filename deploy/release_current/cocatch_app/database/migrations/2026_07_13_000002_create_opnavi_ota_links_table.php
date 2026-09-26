<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('opnavi_ota_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('opnavi_customers')->cascadeOnDelete();
            $table->string('ota_name');
            $table->text('listing_url');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('opnavi_ota_links');
    }
};
