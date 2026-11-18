<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Builder\DesignRenderer;
use App\Builder\EmailDesign;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignVariant;
use App\Services\AbTest;
use App\Services\EmailRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AbTestController extends Controller
{
    public function update(Request $request, Campaign $campaign, AbTest $abTest): RedirectResponse
    {
        abort_unless($campaign->status->isEditable() && ! $campaign->isAutomation(), 409);
        $data = $request->validate([
            'enabled' => ['boolean'],
            'variable' => ['required_if:enabled,1', 'nullable', Rule::in(array_keys(AbTest::VARIABLES))],
            'variants' => ['required_if:enabled,1', 'nullable', 'integer', 'between:2,3'],
            'test_percent' => ['required_if:enabled,1', 'nullable', 'integer', 'between:10,100'],
            'winner_by' => ['required_if:enabled,1', 'nullable', Rule::in(['opens', 'clicks', 'manual'])],
            'wait_hours' => ['required_if:enabled,1', 'nullable', 'integer', 'between:1,168'],
            'subject.*' => ['nullable', 'string', 'max:190'],
            'from_name.*' => ['nullable', 'string', 'max:120'],
            'send_delay.*' => ['nullable', 'integer', 'between:0,72'],
        ]);
        $data['enabled'] = $request->boolean('enabled');
        if ($data['enabled'] && in_array($data['variable'], ['subject', 'from_name'], true)) {
            foreach (array_slice(['B', 'C'], 0, (int) $data['variants'] - 1) as $label) {
                if (trim((string) ($data[$data['variable']][$label] ?? '')) === '') {
                    return back()->withErrors(['ab' => "Fill in variant {$label}."])->withInput();
                }
            }
        }
        $abTest->configure($campaign, $data);

        return redirect()->to(route('campaigns.edit', $campaign).'#ab-test')->with('status', $data['enabled'] ? 'A/B test saved.' : 'A/B test turned off.');
    }

    public function editVariant(Campaign $campaign, CampaignVariant $variant): View
    {
        abort_unless($campaign->status->isEditable() && $variant->label !== 'A', 404);

        return view('campaigns.variant', [
            'campaign' => $campaign,
            'variant' => $variant,
            'mergeTags' => EmailRenderer::MERGE_TAGS,
            'blockTypes' => EmailDesign::BLOCKS,
            'blockDefaults' => collect(EmailDesign::BLOCKS)->keys()->mapWithKeys(fn ($t) => [$t => app(EmailDesign::class)->defaults($t)]),
            'socialNetworks' => EmailDesign::SOCIAL,
        ]);
    }

    public function updateVariant(Request $request, Campaign $campaign, CampaignVariant $variant, EmailDesign $design, DesignRenderer $renderer): RedirectResponse
    {
        abort_unless($campaign->status->isEditable() && $variant->label !== 'A', 404);
        $data = $request->validate([
            'design' => ['nullable', 'json', 'max:1000000'],
            'html' => ['required_without:design', 'nullable', 'string', 'max:500000'],
        ]);
        if (! empty($data['design'])) {
            $normalized = $design->normalize(json_decode($data['design'], true));
            $variant->update(['design' => $normalized, 'html' => $renderer->render($normalized)]);
        } else {
            $variant->update(['design' => null, 'html' => $data['html']]);
        }

        return back()->with('status', "Variant {$variant->label} saved.");
    }

    public function pickWinner(Campaign $campaign, CampaignVariant $variant, AbTest $abTest): RedirectResponse
    {
        abort_unless($campaign->status === CampaignStatus::Testing, 409);
        $count = $abTest->finish($campaign, $variant);

        return back()->with('status', "Variant {$variant->label} is sent to the remaining {$count} recipients.");
    }
}
