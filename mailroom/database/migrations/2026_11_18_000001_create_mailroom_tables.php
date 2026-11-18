<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audiences', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('from_name');
            $table->string('from_email');
            $table->boolean('double_opt_in')->default(true);
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('status', 20)->default('subscribed')->index();
            $table->string('token', 64)->unique();
            $table->string('source', 20)->default('manual');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['audience_id', 'email']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['audience_id', 'name']);
        });

        Schema::create('contact_tag', function (Blueprint $table): void {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'tag_id']);
        });

        Schema::create('segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('match', 3)->default('all');
            $table->json('conditions');
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tag_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->string('from_name');
            $table->string('from_email');
            $table->longText('html');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });

        Schema::create('campaign_sends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'contact_id']);
        });

        Schema::create('campaign_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('hash', 16);
            $table->text('url');
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'hash']);
        });
    }

    public function down(): void
    {
        foreach (['campaign_links', 'campaign_sends', 'campaigns', 'segments', 'contact_tag', 'tags', 'contacts', 'audiences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
