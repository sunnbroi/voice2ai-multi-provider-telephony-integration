<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('calls', function (Blueprint $table) {
        $table->boolean('lead')->default(false);
        $table->boolean('new_client')->default(true);
        $table->string('tags')->nullable();
    });
}

};
