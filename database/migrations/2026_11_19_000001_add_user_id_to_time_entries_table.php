<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            // Employee code of the owner (TIMESHEET_USER in .env), e.g. 01
            $table->unsignedInteger('user_id')->nullable()->after('id')->index();
        });

        // Existing rows belong to whoever runs this migration
        if ($user = config('timesheet.user_id')) {
            DB::table('time_entries')->whereNull('user_id')->update(['user_id' => $user]);
        }
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
