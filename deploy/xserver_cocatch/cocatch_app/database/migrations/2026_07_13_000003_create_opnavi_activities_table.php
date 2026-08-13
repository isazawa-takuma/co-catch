<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('opnavi_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('opnavi_customers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('action_at');
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('status');
            $table->text('memo');
            $table->timestamps();

            $table->index(['customer_id', 'action_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('opnavi_activities');
    }
};
