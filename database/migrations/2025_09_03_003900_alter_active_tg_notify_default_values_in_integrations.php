<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('integrations')->whereNull('active_tg_notify_client')->update([
            'active_tg_notify_client' => 1
        ]);

        Schema::table('integrations', function (Blueprint $table) {
            $table->boolean('active_tg_notify_client')->default(1)->change();
        });

        DB::table('integrations')->whereNull('active_tg_notify_admin')->update([
            'active_tg_notify_admin' => 1
        ]);

        Schema::table('integrations', function (Blueprint $table) {
            $table->boolean('active_tg_notify_admin')->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->boolean('active_tg_notify_client')->nullable()->default(null)->change();
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->boolean('active_tg_notify_admin')->nullable()->default(null)->change();
        });
    }
};
