<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Services\EmailRenderer;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicCampaignController extends Controller
{
    /** Personal "view in browser" version of an email someone received. */
    public function web(string $token, EmailRenderer $renderer): Response
    {
        $send = CampaignSend::query()->with(['campaign', 'contact.audience', 'variant'])->where('token', $token)->firstOrFail();
        $campaign = $send->variant?->apply($send->campaign) ?? $send->campaign;

        return $this->html($renderer->render($campaign, $send->contact));
    }

    /** Non-personal public version (campaign archive, social sharing). */
    public function show(Campaign $campaign, string $hash, EmailRenderer $renderer): Response
    {
        abort_unless(hash_equals($campaign->publicHash(), $hash) && $this->isPublic($campaign), 404);
        $reader = new Contact(['email' => '', 'first_name' => '', 'last_name' => '']);
        $reader->token = 'archive';
        $reader->setRelation('audience', $campaign->audience);

        return $this->html($renderer->render($campaign, $reader));
    }

    public function archive(string $publicId): View
    {
        $audience = Audience::query()->where('public_id', $publicId)->firstOrFail();

        return view('public.archive', [
            'audience' => $audience,
            'campaigns' => $audience->campaigns()->where('kind', 'regular')->where('status', CampaignStatus::Sent)->latest('sent_at')->limit(100)->get(),
        ]);
    }

    private function isPublic(Campaign $campaign): bool
    {
        return $campaign->kind === 'regular' && in_array($campaign->status, [CampaignStatus::Sent, CampaignStatus::Testing], true);
    }

    private function html(string $html): Response
    {
        return response($html)->header('Content-Security-Policy', "script-src 'none'")->header('X-Robots-Tag', 'noindex');
    }
}
