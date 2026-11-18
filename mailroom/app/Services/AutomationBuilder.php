<?php

declare(strict_types=1);

namespace App\Services;

use App\Builder\DesignRenderer;
use App\Builder\EmailDesign;
use App\Enums\CampaignStatus;
use App\Enums\StepType;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Campaign;

/** Creates and reorders automation steps, including the hidden campaign behind each email step. */
final class AutomationBuilder
{
    public const PRESETS = [
        'welcome' => ['label' => 'Welcome series', 'trigger' => 'subscribed', 'steps' => [
            ['email', 'Welcome to {{ audience_name }}!'], ['wait', 2], ['email', 'Getting the most out of {{ audience_name }}'], ['wait', 5], ['email', 'Anything we can help with, {{ first_name | friend }}?'],
        ]],
        'tag' => ['label' => 'Tag follow-up', 'trigger' => 'tag_added', 'steps' => [['email', 'Thanks for your interest'], ['wait', 3], ['email', 'A little extra for you']]],
        'blank' => ['label' => 'Start from scratch', 'trigger' => 'subscribed', 'steps' => []],
    ];

    public function __construct(private readonly EmailDesign $design, private readonly DesignRenderer $renderer) {}

    public function applyPreset(Automation $automation, string $preset): void
    {
        foreach (self::PRESETS[$preset]['steps'] ?? [] as [$type, $value]) {
            $type === 'email'
                ? $this->add($automation, StepType::Email, null, $value)
                : $this->add($automation, StepType::Wait, null, null, ['amount' => $value, 'unit' => 'days']);
        }
    }

    /** @param  array<string, mixed>  $config */
    public function add(Automation $automation, StepType $type, ?int $afterPosition = null, ?string $subject = null, array $config = []): AutomationStep
    {
        $position = $afterPosition === null ? $automation->steps()->count() : $afterPosition + 1;
        $automation->steps()->where('position', '>=', $position)->increment('position');

        $campaignId = null;
        if ($type === StepType::Email) {
            $audience = $automation->audience;
            $design = $this->design->starter();
            $campaignId = Campaign::query()->create([
                'kind' => 'automation',
                'status' => CampaignStatus::Draft,
                'audience_id' => $audience->id,
                'name' => $automation->name.' · email',
                'subject' => $subject ?? 'New email',
                'from_name' => $audience->from_name,
                'from_email' => $audience->from_email,
                'design' => $design,
                'html' => $this->renderer->render($design),
            ])->id;
        }

        $defaults = match ($type) {
            StepType::Wait => ['amount' => 1, 'unit' => 'days'],
            StepType::Tag, StepType::Untag => ['tag' => ''],
            StepType::Email => [],
        };

        return $automation->steps()->create([
            'position' => $position,
            'type' => $type,
            'config' => $config + $defaults,
            'campaign_id' => $campaignId,
        ]);
    }

    public function remove(AutomationStep $step): void
    {
        $automation = $step->automation;
        $step->campaign?->delete();
        $step->delete();
        $this->renumber($automation);
    }

    public function move(AutomationStep $step, int $direction): void
    {
        $steps = $step->automation->steps()->get()->values();
        $index = $steps->search(fn (AutomationStep $s) => $s->id === $step->id);
        $swap = $steps[$index + $direction] ?? null;
        if ($swap === null) {
            return;
        }
        [$a, $b] = [$step->position, $swap->position];
        $step->update(['position' => $b]);
        $swap->update(['position' => $a]);
    }

    private function renumber(Automation $automation): void
    {
        $automation->steps()->get()->values()->each(fn (AutomationStep $s, int $i) => $s->position === $i ?: $s->update(['position' => $i]));
    }
}
