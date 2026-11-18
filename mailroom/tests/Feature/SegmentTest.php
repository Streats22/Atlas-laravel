<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Segment;
use App\Services\TagSync;

class SegmentTest extends MailroomTestCase
{
    public function test_segment_conditions_filter_contacts(): void
    {
        $audience = $this->audience();
        $ann = $this->contact($audience, 'ann@gmail.test', extra: ['first_name' => 'Ann']);
        $this->contact($audience, 'bob@work.test', extra: ['first_name' => 'Bob']);
        app(TagSync::class)->attach($ann, 'vip');

        $all = $audience->segments()->create(['name' => 'x', 'match' => 'all', 'conditions' => [
            ['field' => 'email', 'operator' => 'ends_with', 'value' => 'gmail.test'],
            ['field' => 'tag', 'operator' => 'is', 'value' => 'vip'],
        ]]);
        $any = $audience->segments()->create(['name' => 'y', 'match' => 'any', 'conditions' => [
            ['field' => 'first_name', 'operator' => 'is', 'value' => 'Bob'],
            ['field' => 'tag', 'operator' => 'is', 'value' => 'vip'],
        ]]);

        $this->assertSame(['ann@gmail.test'], $all->contacts()->pluck('email')->all());
        $this->assertCount(2, $any->contacts()->get());
    }

    public function test_engagement_condition(): void
    {
        $audience = $this->audience();
        $opener = $this->contact($audience, 'o@x.test');
        $this->contact($audience, 'n@x.test');
        $this->campaign($audience)->sends()->create(['contact_id' => $opener->id, 'opened_at' => now()]);

        $segment = $audience->segments()->create(['name' => 'z', 'match' => 'all', 'conditions' => [['field' => 'opened_any', 'operator' => 'is_not', 'value' => '']]]);
        $this->assertSame(['n@x.test'], $segment->contacts()->pluck('email')->all());
    }

    public function test_hostile_segment_fields_are_rejected(): void
    {
        $audience = $this->audience();

        $this->actingAs($this->admin())->post("/audiences/{$audience->id}/segments", [
            'name' => 'evil', 'match' => 'all',
            'conditions' => [['field' => 'email) or 1=1 --', 'operator' => 'is', 'value' => 'x']],
        ])->assertSessionHasErrors('conditions.0');

        $this->assertSame(0, Segment::query()->count());
    }

    public function test_sql_in_values_is_treated_as_data(): void
    {
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test');
        $segment = $audience->segments()->create(['name' => 's', 'match' => 'all', 'conditions' => [
            ['field' => 'email', 'operator' => 'is', 'value' => "' OR '1'='1"],
        ]]);
        $this->assertSame(0, $segment->contacts()->count());
    }
}
