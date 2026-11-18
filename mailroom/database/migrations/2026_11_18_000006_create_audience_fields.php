<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('key', 40);
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('show_on_form')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['audience_id', 'key']);
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->json('fields')->nullable()->after('last_name');
            $table->text('notes')->nullable()->after('fields');
            $table->timestamp('consent_at')->nullable()->after('unsubscribed_at');
            $table->string('consent_text', 500)->nullable()->after('consent_at');
            $table->string('signup_ip', 45)->nullable()->after('consent_text');
        });

        Schema::table('audiences', function (Blueprint $table): void {
            $table->boolean('gdpr_enabled')->default(false)->after('double_opt_in');
            $table->string('gdpr_text', 500)->nullable()->after('gdpr_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('audiences', fn (Blueprint $t) => $t->dropColumn(['gdpr_enabled', 'gdpr_text']));
        Schema::table('contacts', fn (Blueprint $t) => $t->dropColumn(['fields', 'notes', 'consent_at', 'consent_text', 'signup_ip']));
        Schema::dropIfExists('audience_fields');
    }
};
