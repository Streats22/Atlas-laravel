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
            $table->foreignId('resend_of_id')->nullable()->after('tag_id')->constrained('campaigns')->nullOnDelete();
            $table->json('rss')->nullable()->after('ab_test');
            $table->foreignId('parent_id')->nullable()->after('resend_of_id')->constrained('campaigns')->nullOnDelete();
        });

        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix', 12);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('resend_of_id');
            $table->dropColumn('rss');
        });
    }
};
