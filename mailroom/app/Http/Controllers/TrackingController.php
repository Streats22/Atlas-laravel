<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CampaignSend;
use App\Services\UserAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(Request $request, string $token, UserAgent $ua): Response
    {
        $send = CampaignSend::query()->where('token', $token)->first();
        if ($send !== null) {
            $send->fill($ua->classify($request->userAgent()));
            $send->recordOpen();
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /** Only redirects to URLs stored for this campaign, so the endpoint is not an open redirect. */
    public function click(Request $request, string $token, string $hash, UserAgent $ua): RedirectResponse
    {
        $send = CampaignSend::query()->where('token', $token)->firstOrFail();
        $link = $send->campaign->links()->where('hash', $hash)->firstOrFail();

        // Clicks happen in a real browser, so they tell us more about the device than proxied opens.
        $send->fill($ua->classify($request->userAgent()));
        $send->recordClick();
        $link->increment('click_count');

        return redirect()->away($link->url);
    }
}
