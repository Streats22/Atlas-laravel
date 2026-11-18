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
            $table->string('kind', 20)->default('regular')->index()->after('id');
        });

        Schema::create('automations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger', 30);
            $table->string('trigger_value')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('automation_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('type', 20);
            $table->json('config')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['automation_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_steps');
        Schema::dropIfExists('automations');
        Schema::table('campaigns', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
