<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $sent = CampaignSend::query()->whereNotNull('sent_at')->count();

        return view('dashboard', [
            'audiences' => Audience::query()->count(),
            'subscribers' => Contact::query()->where('status', ContactStatus::Subscribed)->count(),
            'newThisMonth' => Contact::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'sent' => $sent,
            'openRate' => $sent ? round(CampaignSend::query()->whereNotNull('opened_at')->count() / $sent * 100, 1) : 0,
            'clickRate' => $sent ? round(CampaignSend::query()->whereNotNull('clicked_at')->count() / $sent * 100, 1) : 0,
            'recent' => Campaign::query()->with('audience')->where('kind', 'regular')->latest()->limit(5)->get(),
        ]);
    }
}
