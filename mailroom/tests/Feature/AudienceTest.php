<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactStatus;
use Illuminate\Http\UploadedFile;

class AudienceTest extends MailroomTestCase
{
    public function test_csv_import_creates_updates_and_never_resubscribes(): void
    {
        $audience = $this->audience();
        $this->contact($audience, 'old@x.test', ContactStatus::Unsubscribed);
        $csv = "Email Address;First Name;Tags\nnew@x.test;Ann;vip, nl\nOLD@x.test;Bob;\nnot-an-email;X;\n";

        $this->actingAs($this->admin())->post("/audiences/{$audience->id}/import", [
            'file' => UploadedFile::fake()->createWithContent('list.csv', $csv),
            'status' => 'subscribed', 'tag' => 'imported', 'consent' => '1',
        ])->assertSessionHas('status', 'Import finished: 1 added, 1 updated, 1 skipped.');

        $new = $audience->contacts()->where('email', 'new@x.test')->sole();
        $this->assertSame('Ann', $new->first_name);
        $this->assertEqualsCanonicalizing(['vip', 'nl', 'imported'], $new->tags->pluck('name')->all());
        $this->assertSame(ContactStatus::Unsubscribed, $audience->contacts()->where('email', 'old@x.test')->sole()->status);
    }

    public function test_export_neutralises_spreadsheet_formulas(): void
    {
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test', extra: ['first_name' => '=HYPERLINK("http://evil")']);

        $csv = $this->actingAs($this->admin())->get("/audiences/{$audience->id}/export")->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_contacts_are_scoped_to_their_audience(): void
    {
        $a = $this->audience();
        $b = $this->audience(['name' => 'Other']);
        $contact = $this->contact($b, 'z@x.test');

        $this->actingAs($this->admin())->get("/audiences/{$a->id}/contacts/{$contact->id}/edit")->assertNotFound();
    }

    public function test_duplicate_email_in_same_audience_is_rejected(): void
    {
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test');

        $this->actingAs($this->admin())->post("/audiences/{$audience->id}/contacts", ['email' => 'A@x.test', 'status' => 'subscribed'])
            ->assertSessionHasErrors('email');
    }

    public function test_bulk_tagging(): void
    {
        $audience = $this->audience();
        $c = $this->contact($audience, 'a@x.test');
        $this->actingAs($this->admin())->post("/audiences/{$audience->id}/contacts/bulk", ['ids' => [$c->id], 'action' => 'tag', 'tag' => 'vip'])->assertRedirect();
        $this->assertSame(['vip'], $c->tags()->pluck('name')->all());
    }
}
