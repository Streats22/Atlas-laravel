<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\Audience;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $rows = $this->rows($request);
        $sent = max(1, $rows->sum('sent'));

        return view('reports.index', [
            'rows' => $rows,
            'audiences' => Audience::query()->orderBy('name')->get(),
            'audienceId' => $request->integer('audience') ?: null,
            'totals' => [
                'campaigns' => $rows->count(),
                'sent' => $rows->sum('sent'),
                'open_rate' => round($rows->sum('opens') / $sent * 100, 1),
                'click_rate' => round($rows->sum('clicks') / $sent * 100, 1),
                'unsub_rate' => round($rows->sum('unsubscribes') / $sent * 100, 2),
                'bounce_rate' => round($rows->sum('bounces') / $sent * 100, 2),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->rows($request);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['campaign', 'subject', 'audience', 'sent_at', 'sent', 'open_rate', 'click_rate', 'unsubscribes', 'bounces', 'complaints']);
            foreach ($rows as $r) {
                fputcsv($out, array_map([ContactImportController::class, 'csvSafe'], [
                    $r['name'], $r['subject'], $r['audience'], $r['sent_at'], (string) $r['sent'], (string) $r['open_rate'],
                    (string) $r['click_rate'], (string) $r['unsubscribes'], (string) $r['bounces'], (string) $r['complaints'],
                ]));
            }
            fclose($out);
        }, 'campaign-report.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function rows(Request $request): Collection
    {
        return Campaign::query()->with('audience')
            ->where('kind', 'regular')
            ->whereIn('status', [CampaignStatus::Sent, CampaignStatus::Testing])
            ->when($request->integer('audience'), fn (Builder $q, int $id) => $q->where('audience_id', $id))
            ->latest('sent_at')->limit(200)->get()
            ->map(fn (Campaign $c) => ['id' => $c->id, 'name' => $c->name, 'subject' => $c->subject, 'audience' => $c->audience->name, 'sent_at' => $c->sent_at?->format('Y-m-d H:i')] + $c->stats());
    }
}
