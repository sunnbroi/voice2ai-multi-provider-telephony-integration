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
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->decimal('price_ru', 12,2)->default(0);
            $table->decimal('price_ua', 12, 2)->default(0);
            $table->decimal('price_kz', 12, 2)->default(0);
        });

        $tariffNames = [
            1 => 'Расшифровка',
            2 => 'Расшифровка +',
            3 => 'Продвинутый',
            4 => 'Премиум',
        ];

        foreach ($tariffNames as $tariffName){
            $tariff = new \App\Models\Tariff();
            $tariff->name = $tariffName;
            $tariff->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariffs');
    }
};
