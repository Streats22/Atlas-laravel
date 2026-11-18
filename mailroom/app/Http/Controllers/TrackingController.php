<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CampaignSend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(string $token): Response
    {
        CampaignSend::query()->where('token', $token)->first()?->recordOpen();

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /** Only redirects to URLs stored for this campaign, so the endpoint is not an open redirect. */
    public function click(string $token, string $hash): RedirectResponse
    {
        $send = CampaignSend::query()->where('token', $token)->firstOrFail();
        $link = $send->campaign->links()->where('hash', $hash)->firstOrFail();

        $send->recordClick();
        $link->increment('click_count');

        return redirect()->away($link->url);
    }
}
