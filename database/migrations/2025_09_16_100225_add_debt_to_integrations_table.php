<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->integer('debt_minutes')->default(0);
            $table->decimal('debt_price', 12, 2)->default(0);
            $table->string('debt_currency')->nullable();
            $table->unsignedBigInteger('debt_tariff_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('debt_minutes');
            $table->dropColumn('debt_price');
            $table->dropColumn('debt_currency');
            $table->dropColumn('debt_tariff_id');
        });
    }
};
