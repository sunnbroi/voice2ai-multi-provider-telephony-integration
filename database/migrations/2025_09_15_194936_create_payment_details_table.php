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
        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('country');
            $table->text('description')->nullable();
        });

        $countryNames = [
            1 => 'Россия',
            2 => 'Украина',
            3 => 'Казахстан',
        ];

        foreach ($countryNames as $countryName){
            $paymentDetail = new \App\Models\PaymentDetail();
            $paymentDetail->country = $countryName;
            $paymentDetail->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_details');
    }
};
