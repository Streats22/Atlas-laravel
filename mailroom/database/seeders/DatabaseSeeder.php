<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CampaignStatus;
use App\Enums\ContactStatus;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\User;
use App\Services\EmailTemplates;
use App\Services\TagSync;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Admin', 'password' => 'password']);

        $audience = Audience::query()->firstOrCreate(['name' => 'Newsletter'], [
            'from_name' => 'Demo Company', 'from_email' => 'news@example.com', 'double_opt_in' => true,
        ]);

        $people = [['Jane', 'Doe', ['vip']], ['John', 'Smith', []], ['Sanne', 'de Vries', ['vip', 'nl']], ['Ali', 'Yilmaz', ['nl']], ['Mia', 'Jansen', []]];
        $tags = app(TagSync::class);
        foreach ($people as [$first, $last, $tagNames]) {
            $contact = $audience->contacts()->firstOrCreate(
                ['email' => strtolower($first).'@example.com'],
                ['first_name' => $first, 'last_name' => $last, 'status' => ContactStatus::Subscribed, 'subscribed_at' => now()->subDays(random_int(1, 60))],
            );
            foreach ($tagNames as $tag) {
                $tags->attach($contact, $tag);
            }
        }

        $audience->segments()->firstOrCreate(['name' => 'VIPs'], ['match' => 'all', 'conditions' => [['field' => 'tag', 'operator' => 'is', 'value' => 'vip']]]);

        Campaign::query()->firstOrCreate(['name' => 'Welcome newsletter'], [
            'audience_id' => $audience->id, 'subject' => 'Hello {{ first_name | there }}!', 'preview_text' => 'Our first newsletter',
            'from_name' => $audience->from_name, 'from_email' => $audience->from_email,
            'html' => app(EmailTemplates::class)->get('newsletter'), 'status' => CampaignStatus::Draft,
        ]);
    }
}
