<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->json('ab_test')->nullable()->after('design');
        });

        Schema::create('campaign_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('label', 2);
            $table->string('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->longText('html')->nullable();
            $table->json('design')->nullable();
            $table->unsignedSmallInteger('send_delay_hours')->default(0);
            $table->boolean('is_winner')->default(false);
            $table->timestamps();
            $table->unique(['campaign_id', 'label']);
        });

        Schema::table('campaign_sends', function (Blueprint $table): void {
            $table->foreignId('variant_id')->nullable()->after('contact_id')->constrained('campaign_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_sends', fn (Blueprint $table) => $table->dropConstrainedForeignId('variant_id'));
        Schema::dropIfExists('campaign_variants');
        Schema::table('campaigns', fn (Blueprint $table) => $table->dropColumn('ab_test'));
    }
};
