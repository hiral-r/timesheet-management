<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->date('work_date')->index();
            $table->string('module', 50);
            $table->string('source', 50);
            $table->string('task_type', 50);
            $table->string('task_key', 100)->nullable();      // FLOW-198 / XOSP-727 / person name for verbal tasks
            $table->string('task_link', 500)->nullable();
            $table->text('description');
            $table->unsignedInteger('seconds')->default(0);   // accumulated (paused) time
            $table->dateTime('running_since')->nullable();     // set while the timer runs
            $table->boolean('filled_form')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
