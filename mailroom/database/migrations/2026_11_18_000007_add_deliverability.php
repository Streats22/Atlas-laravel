<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_sends', function (Blueprint $table): void {
            $table->timestamp('bounced_at')->nullable()->after('unsubscribed_at');
            $table->string('bounce_type', 10)->nullable()->after('bounced_at');
            $table->timestamp('complained_at')->nullable()->after('bounce_type');
            $table->string('client', 40)->nullable()->after('complained_at');
            $table->string('device', 10)->nullable()->after('client');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('soft_bounces')->default(0)->after('signup_ip');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_sends', fn (Blueprint $t) => $t->dropColumn(['bounced_at', 'bounce_type', 'complained_at', 'client', 'device']));
        Schema::table('contacts', fn (Blueprint $t) => $t->dropColumn('soft_bounces'));
    }
};
