<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('owner')->after('email');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->boolean('optimize_send_time')->default(false)->after('scheduled_at');
        });

        Schema::create('landing_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('meta_description', 300)->nullable();
            $table->json('design');
            $table->longText('html');
            $table->string('tag')->nullable();
            $table->boolean('published')->default(false);
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('signups')->default(0);
            $table->timestamps();
        });

        // Existing installs: the first user owns the account, everyone else becomes an admin.
        $first = DB::table('users')->min('id');
        if ($first !== null) {
            DB::table('users')->where('id', '!=', $first)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn('optimize_send_time'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
