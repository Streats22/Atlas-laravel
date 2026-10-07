<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atlas_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->unique();
            $table->string('label');
            $table->string('category', 64)->default('Custom');
            $table->string('icon', 16)->nullable();
            $table->boolean('container')->default(false);
            $table->json('fields')->nullable();
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();
            $table->longText('js')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_blocks');
    }
};
