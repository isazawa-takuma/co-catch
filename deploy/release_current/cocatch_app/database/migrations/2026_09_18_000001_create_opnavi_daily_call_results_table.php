<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('opnavi_daily_call_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('result_date');
            $table->unsignedInteger('total_count');
            $table->json('status_counts');
            $table->dateTime('confirmed_at');
            $table->timestamps();

            $table->unique(['user_id', 'result_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('opnavi_daily_call_results');
    }
};
