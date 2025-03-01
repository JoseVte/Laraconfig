<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserSettingsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_settings', static function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('metadata_id');

            // You can change this morph column to suit your needs, like using `uuidMorphs()`.
            // $table->uuidMorphs('settable');
            $table->numericMorphs('settable');

            $table->string('value')->nullable();
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
}
