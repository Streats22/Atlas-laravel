<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Builder\DesignRenderer;
use App\Builder\EmailDesign;
use App\Mail\CampaignMail;
use App\Models\EmailTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BuilderTest extends MailroomTestCase
{
    public function test_design_is_normalised_and_hostile_values_dropped(): void
    {
        $design = app(EmailDesign::class)->normalize([
            'settings' => ['background' => 'red;background:url(x)', 'font' => 'Comic"; x', 'width' => 99999],
            'blocks' => [
                ['type' => 'evil', 'props' => []],
                ['type' => 'text', 'props' => ['html' => '<p onclick="x()">Hi<script>alert(1)</script><a href="javascript:alert(1)">bad</a><a href="https://ok.test" style="x">ok</a><img src=x onerror=y></p>']],
                ['type' => 'button', 'props' => ['label' => '<b>Go</b>', 'url' => 'javascript:alert(1)', 'color' => 'expression()']],
                ['type' => 'image', 'props' => ['src' => 'data:image/png;base64,xx', 'width' => 500]],
            ],
        ]);

        $this->assertSame('#f4f4f5', $design['settings']['background']);
        $this->assertSame(800, $design['settings']['width']);
        $this->assertStringStartsWith('Helvetica', $design['settings']['font']);
        $this->assertCount(3, $design['blocks']);
        $this->assertSame('<p>Hibad<a href="https://ok.test">ok</a></p>', $design['blocks'][0]['props']['html']);
        $this->assertSame('', $design['blocks'][1]['props']['url']);
        $this->assertSame('', $design['blocks'][1]['props']['color']);
        $this->assertSame('', $design['blocks'][2]['props']['src']);
        $this->assertSame(100, $design['blocks'][2]['props']['width']);

        $html = app(DesignRenderer::class)->render($design);
        $this->assertStringContainsString('&lt;b&gt;Go&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_saving_a_builder_campaign_renders_html_server_side(): void
    {
        $audience = $this->audience();
        $design = app(EmailDesign::class)->starter();
        $design['blocks'][1]['props']['text'] = 'Big news, {{ first_name }}';

        $this->actingAs($this->admin())->post('/campaigns', [
            'name' => 'Builder', 'audience_id' => $audience->id, 'subject' => 'S', 'from_name' => 'A', 'from_email' => 'a@b.test',
            'design' => json_encode($design), 'html' => '<script>ignored</script>',
        ])->assertRedirect();

        $campaign = $audience->campaigns()->sole();
        $this->assertStringContainsString('Big news, {{ first_name }}', $campaign->html);
        $this->assertStringNotContainsString('ignored', $campaign->html);
        $this->assertStringNotContainsString('data-block', $campaign->html);
        $this->assertCount(6, $campaign->design['blocks']);
    }

    public function test_builder_preview_endpoint_marks_blocks_for_the_editor(): void
    {
        $this->actingAs($this->admin())
            ->post('/builder/preview', ['design' => json_encode(app(EmailDesign::class)->starter())])
            ->assertOk()->assertSee('data-block=', false)->assertHeader('Content-Security-Policy', "script-src 'none'");
    }

    public function test_image_upload_accepts_images_only(): void
    {
        Storage::fake('public');
        $user = $this->admin();

        $this->actingAs($user)->postJson('/media', ['file' => UploadedFile::fake()->image('hero.png', 600, 300)])
            ->assertCreated()->assertJsonPath('name', 'hero.png');
        $this->actingAs($user)->postJson('/media', ['file' => UploadedFile::fake()->create('evil.php', 1, 'application/x-php')])
            ->assertUnprocessable();
        $this->actingAs($user)->getJson('/media')->assertJsonCount(1);
    }

    public function test_save_and_reuse_template(): void
    {
        $audience = $this->audience();
        $campaign = $this->campaign($audience);
        $user = $this->admin();

        $this->actingAs($user)->post('/templates', ['name' => 'Mine', 'campaign_id' => $campaign->id])->assertRedirect();
        $template = EmailTemplate::query()->sole();
        $this->actingAs($user)->get('/campaigns/create?template=saved:'.$template->id)->assertOk()->assertSee('Hello {{ first_name }}', false);
        $this->actingAs($user)->get('/templates')->assertOk()->assertSee('Mine');
    }

    public function test_review_page_shows_checklist_and_plain_text(): void
    {
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test', extra: ['first_name' => 'Ann']);
        $campaign = $this->campaign($audience, ['html' => '<p>Hi {{ first_name }} {{ nope }}</p><img src="https://x.test/a.png">']);

        $this->actingAs($this->admin())->get("/campaigns/{$campaign->id}/review")
            ->assertOk()
            ->assertSee('Unknown merge tags: nope')
            ->assertSee('1 image(s) have no alt text')
            ->assertSee('Hi Ann');
    }

    public function test_checklist_errors_block_sending(): void
    {
        $campaign = $this->campaign($this->audience()); // no contacts → error
        $this->actingAs($this->admin())->post("/campaigns/{$campaign->id}/send")->assertRedirect("/campaigns/{$campaign->id}/review");
        $this->assertTrue($campaign->fresh()->status->isEditable());
    }

    public function test_mail_has_plain_text_part_and_dark_preview(): void
    {
        $audience = $this->audience();
        $contact = $this->contact($audience, 'a@x.test', extra: ['first_name' => 'Ann']);
        $campaign = $this->campaign($audience);

        $mail = new CampaignMail($campaign, $contact);
        $mail->assertSeeInText('Hello Ann');
        $mail->assertSeeInText('Offer (https://acme.test/offer)');

        $this->actingAs($this->admin())->get("/campaigns/{$campaign->id}/preview?dark=1")->assertSee('invert(1)', false);
    }
}
