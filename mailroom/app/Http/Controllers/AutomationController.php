<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AutomationTrigger;
use App\Enums\StepType;
use App\Models\Audience;
use App\Models\AudienceField;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Segment;
use App\Services\AutomationBuilder;
use App\Services\AutomationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(): View
    {
        return view('automations.index', [
            'automations' => Automation::query()->with('audience')->withCount([
                'runs', 'runs as active_runs_count' => fn ($q) => $q->where('status', 'active'), 'steps',
            ])->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('automations.create', [
            'audiences' => Audience::query()->with('fields')->orderBy('name')->get(),
            'presets' => AutomationBuilder::PRESETS,
        ]);
    }

    public function store(Request $request, AutomationBuilder $builder): RedirectResponse
    {
        $data = $this->validated($request) + $request->validate(['preset' => ['required', Rule::in(array_keys(AutomationBuilder::PRESETS))]]);
        $automation = Automation::query()->create($data + ['status' => 'draft']);
        $builder->applyPreset($automation, $data['preset']);

        return redirect()->route('automations.show', $automation)->with('status', 'Automation created. Design each email, then turn it on.');
    }

    public function show(Automation $automation): View
    {
        $automation->load(['audience.segments', 'audience.fields', 'steps.campaign']);
        $runs = $automation->runs();

        return view('automations.show', [
            'automation' => $automation,
            'stepTypes' => StepType::cases(),
            'triggers' => AutomationTrigger::cases(),
            'counts' => [
                'active' => (clone $runs)->where('status', 'active')->count(),
                'completed' => (clone $runs)->where('status', 'completed')->count(),
                'exited' => (clone $runs)->where('status', 'exited')->count(),
            ],
            'waiting' => $automation->runs()->where('status', 'active')->pluck('position')->countBy(),
        ]);
    }

    public function update(Request $request, Automation $automation): RedirectResponse
    {
        $automation->update($this->validated($request, $automation));

        return back()->with('status', 'Automation saved.');
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $automation->steps()->with('campaign')->get()->each(fn (AutomationStep $s) => $s->campaign?->delete());
        $automation->delete();

        return redirect()->route('automations.index')->with('status', 'Automation deleted.');
    }

    public function status(Request $request, Automation $automation): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'paused'])]])['status'];
        if ($status === 'active' && $automation->steps()->where('type', 'email')->doesntExist()) {
            return back()->withErrors(['status' => 'Add at least one email before turning the automation on.']);
        }
        $automation->update(['status' => $status]);

        return back()->with('status', $status === 'active' ? 'Automation is live.' : 'Automation paused. Contacts stay where they are.');
    }

    public function enroll(Request $request, Automation $automation, AutomationEngine $engine): RedirectResponse
    {
        $data = $request->validate(['segment_id' => ['nullable', Rule::exists('segments', 'id')->where('audience_id', $automation->audience_id)]]);
        abort_unless($automation->isActive(), 409, 'Turn the automation on first.');
        $query = $data['segment_id'] ?? null
            ? Segment::query()->findOrFail($data['segment_id'])->contacts()
            : $automation->audience->contacts()->getQuery();

        $count = 0;
        $query->subscribed()->chunkById(200, function ($contacts) use ($engine, $automation, &$count): void {
            foreach ($contacts as $contact) {
                $count += $engine->enroll($automation, $contact)?->wasRecentlyCreated ? 1 : 0;
            }
        }, 'contacts.id', 'id');

        return back()->with('status', "{$count} contacts added to the automation.");
    }

    public function addStep(Request $request, Automation $automation, AutomationBuilder $builder): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(StepType::class)],
            'after' => ['nullable', 'integer', 'min:-1'],
        ]);
        $after = isset($data['after']) ? (int) $data['after'] : null;
        $builder->add($automation, StepType::from($data['type']), $after === -1 ? -1 : $after);

        return back()->with('status', 'Step added.');
    }

    public function updateStep(Request $request, Automation $automation, AutomationStep $step): RedirectResponse
    {
        $config = match ($step->type) {
            StepType::Wait => $request->validate([
                'amount' => ['required', 'integer', 'min:0', 'max:1000'],
                'unit' => ['required', Rule::in(['minutes', 'hours', 'days', 'weeks'])],
            ]),
            StepType::Tag, StepType::Untag => $request->validate(['tag' => ['required', 'string', 'max:60']]),
            StepType::Email => [],
        };
        $step->update(['config' => $config]);

        return back()->with('status', 'Step saved.');
    }

    public function moveStep(Request $request, Automation $automation, AutomationStep $step, AutomationBuilder $builder): RedirectResponse
    {
        $builder->move($step, $request->input('direction') === 'up' ? -1 : 1);

        return back();
    }

    public function deleteStep(Automation $automation, AutomationStep $step, AutomationBuilder $builder): RedirectResponse
    {
        $builder->remove($step);

        return back()->with('status', 'Step removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Automation $automation = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'audience_id' => [$automation ? 'prohibited' : 'required', 'exists:audiences,id'],
            'trigger' => ['required', Rule::enum(AutomationTrigger::class)],
            'trigger_value' => ['nullable', 'required_if:trigger,tag_added', 'string', 'max:60'],
            'trigger_field' => ['nullable', 'required_if:trigger,date', 'string', 'max:40'],
        ]);
        $data['trigger_value'] = match ($data['trigger']) {
            AutomationTrigger::TagAdded->value => $data['trigger_value'],
            AutomationTrigger::Date->value => $data['trigger_field'],
            default => null,
        };
        if ($data['trigger'] === AutomationTrigger::Date->value) {
            $audienceId = $automation?->audience_id ?? (int) $data['audience_id'];
            $isDateField = AudienceField::query()->where('audience_id', $audienceId)->where('key', $data['trigger_field'])
                ->whereIn('type', ['date', 'birthday'])->exists();
            if (! $isDateField) {
                throw ValidationException::withMessages(['trigger_field' => 'Choose a date or birthday field of this audience.']);
            }
        }
        unset($data['trigger_field']);

        return $data;
    }
}
