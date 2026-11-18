<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Http\Requests\CampaignRequest;
use App\Mail\CampaignMail;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\Contact;
use App\Services\CampaignDispatcher;
use App\Services\EmailRenderer;
use App\Services\EmailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $status = CampaignStatus::tryFrom((string) $request->query('status'));

        return view('campaigns.index', [
            'campaigns' => Campaign::query()->with('audience')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function create(Request $request, EmailTemplates $templates): View|RedirectResponse
    {
        $audience = Audience::query()->find($request->integer('audience')) ?? Audience::query()->first();
        if ($audience === null) {
            return redirect()->route('audiences.create')->with('status', 'Create an audience first.');
        }

        return view('campaigns.edit', $this->formData(new Campaign([
            'audience_id' => $audience->id,
            'from_name' => $audience->from_name,
            'from_email' => $audience->from_email,
            'html' => $templates->get((string) $request->query('template', 'simple')),
        ]), $templates));
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        $campaign = Campaign::query()->create($request->validated() + ['status' => CampaignStatus::Draft]);

        return redirect()->route('campaigns.edit', $campaign)->with('status', 'Draft saved.');
    }

    public function show(Campaign $campaign): View|RedirectResponse
    {
        if ($campaign->status->isEditable()) {
            return redirect()->route('campaigns.edit', $campaign);
        }

        $links = $campaign->links()->orderByDesc('click_count')->limit(20)->get();
        $opensByHour = $campaign->sends()->whereNotNull('opened_at')->pluck('opened_at')
            ->groupBy(fn ($at) => $at->format('Y-m-d H:00'))->map->count()->sortKeys()->take(48);

        return view('campaigns.report', [
            'campaign' => $campaign->load(['audience', 'segment', 'tag']),
            'stats' => $campaign->stats(),
            'links' => $links,
            'opensByHour' => $opensByHour,
            'recent' => $campaign->sends()->with('contact')->whereNotNull('opened_at')->latest('opened_at')->limit(15)->get(),
        ]);
    }

    public function edit(Campaign $campaign, EmailTemplates $templates): View|RedirectResponse
    {
        if (! $campaign->status->isEditable()) {
            return redirect()->route('campaigns.show', $campaign);
        }

        return view('campaigns.edit', $this->formData($campaign, $templates) + [
            'recipientCount' => $campaign->recipients()->count(),
        ]);
    }

    public function update(CampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status->isEditable(), 409, 'This campaign has already been sent.');
        $campaign->update($request->validated());

        return redirect()->route('campaigns.edit', $campaign)->with('status', 'Campaign saved.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        abort_if($campaign->status === CampaignStatus::Sending, 409);
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('status', 'Campaign deleted.');
    }

    /** Raw rendered email, shown inside a sandboxed iframe in the editor. */
    public function preview(Campaign $campaign, EmailRenderer $renderer): Response
    {
        $contact = $campaign->audience->contacts()->subscribed()->first()
            ?? new Contact(['email' => 'jane@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe']);
        $contact->token ??= 'preview';
        $contact->setRelation('audience', $campaign->audience);

        return response($renderer->render($campaign, $contact))
            ->header('Content-Security-Policy', "script-src 'none'; frame-ancestors 'self'")
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function test(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validate(['emails' => ['required', 'string', 'max:500']]);
        $emails = array_slice(array_filter(array_map('trim', explode(',', $data['emails'])), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)), 0, 5);
        if ($emails === []) {
            return back()->withErrors(['emails' => 'Enter at least one valid email address.']);
        }

        foreach ($emails as $email) {
            $contact = $campaign->audience->contacts()->where('email', strtolower($email))->first()
                ?? new Contact(['email' => $email, 'first_name' => 'Test']);
            $contact->setRelation('audience', $campaign->audience);
            $contact->token ??= 'preview';
            Mail::to($email)->send(new CampaignMail($campaign, $contact));
        }

        return back()->with('status', 'Test email sent to '.implode(', ', $emails).'.');
    }

    public function send(Campaign $campaign, CampaignDispatcher $dispatcher): RedirectResponse
    {
        $count = $dispatcher->dispatch($campaign);

        return redirect()->route('campaigns.show', $campaign)->with('status', "Campaign queued for {$count} recipients.");
    }

    public function schedule(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status->isEditable(), 409);
        $data = $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);
        $campaign->update(['status' => CampaignStatus::Scheduled, 'scheduled_at' => $data['scheduled_at']]);

        return redirect()->route('campaigns.index')->with('status', 'Campaign scheduled.');
    }

    public function unschedule(Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status === CampaignStatus::Scheduled, 409);
        $campaign->update(['status' => CampaignStatus::Draft, 'scheduled_at' => null]);

        return redirect()->route('campaigns.edit', $campaign)->with('status', 'Campaign moved back to drafts.');
    }

    public function duplicate(Campaign $campaign): RedirectResponse
    {
        $copy = $campaign->replicate(['status', 'scheduled_at', 'sent_at', 'recipients_count']);
        $copy->name = $campaign->name.' (copy)';
        $copy->status = CampaignStatus::Draft;
        $copy->save();

        return redirect()->route('campaigns.edit', $copy)->with('status', 'Campaign duplicated.');
    }

    /** @return array<string, mixed> */
    private function formData(Campaign $campaign, EmailTemplates $templates): array
    {
        return [
            'campaign' => $campaign,
            'audiences' => Audience::query()->with(['segments', 'tags'])->orderBy('name')->get(),
            'templates' => $templates->names(),
            'mergeTags' => EmailRenderer::MERGE_TAGS,
        ];
    }
}
