<?php

use App\Models\Setting;
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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('code');
            $table->string('value');
        });

        $settings = [
            Setting::TELEGRAM_PAYMENT_ADMIN_ID_SETTING_CODE => '',
        ];

        foreach ($settings as $code=>$value){
            $setting = new Setting();
            $setting->code = $code;
            $setting->value = $value;
            $setting->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
