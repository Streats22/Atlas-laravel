<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Turns stored segment conditions into a query-builder chain.
 *
 * Only whitelisted fields and operators are accepted, and every value is a bound parameter,
 * so a segment definition can never smuggle SQL into the query.
 */
final class SegmentQuery
{
    public const FIELDS = [
        'email' => 'Email',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'status' => 'Status',
        'source' => 'Signup source',
        'subscribed_at' => 'Subscribed date',
        'tag' => 'Tag',
        'opened_any' => 'Opened any campaign',
        'clicked_any' => 'Clicked any campaign',
    ];

    public const OPERATORS = [
        'is' => 'is',
        'is_not' => 'is not',
        'contains' => 'contains',
        'starts_with' => 'starts with',
        'ends_with' => 'ends with',
        'before' => 'is before',
        'after' => 'is after',
        'within_days' => 'within the last N days',
    ];

    private const DATE_FIELDS = ['subscribed_at'];

    private const BOOLEAN_FIELDS = ['opened_any', 'clicked_any'];

    /**
     * @param  list<array{field:string, operator:string, value:mixed}>  $conditions
     */
    public function apply(Builder $query, array $conditions, string $match = 'all'): Builder
    {
        $any = $match === 'any';

        return $query->where(function (Builder $outer) use ($conditions, $any): void {
            foreach ($conditions as $condition) {
                $method = $any ? 'orWhere' : 'where';
                $outer->{$method}(fn (Builder $q) => $this->condition($q, $condition));
            }
        });
    }

    /** @param  array{field?:string, operator?:string, value?:mixed}  $condition */
    public function isValid(array $condition): bool
    {
        try {
            $this->assertValid($condition['field'] ?? '', $condition['operator'] ?? '');
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /** @param  array{field:string, operator:string, value:mixed}  $c */
    private function condition(Builder $q, array $c): void
    {
        $field = $c['field'];
        $op = $c['operator'];
        $value = (string) ($c['value'] ?? '');
        $this->assertValid($field, $op);

        if (str_starts_with($field, 'field:')) {
            // Custom field values live in the contacts.fields JSON column; the key is regex-validated.
            $column = 'fields->'.substr($field, 6);
            match ($op) {
                'is' => $q->where($column, $value),
                'is_not' => $q->where(fn (Builder $w) => $w->where($column, '!=', $value)->orWhereNull($column)),
                'contains' => $q->whereLike($column, "%{$value}%"),
                'starts_with' => $q->whereLike($column, "{$value}%"),
                'ends_with' => $q->whereLike($column, "%{$value}"),
                'before' => $q->where($column, '<', $value),
                'after' => $q->where($column, '>', $value),
                'within_days' => $q->where($column, '>=', now()->subDays(max(0, (int) $value))->toDateString()),
            };

            return;
        }

        if ($field === 'tag') {
            $has = fn (Builder $t) => $t->where('name', $value);
            $op === 'is_not' ? $q->whereDoesntHave('tags', $has) : $q->whereHas('tags', $has);

            return;
        }

        if (in_array($field, self::BOOLEAN_FIELDS, true)) {
            $column = $field === 'opened_any' ? 'opened_at' : 'clicked_at';
            $has = fn (Builder $s) => $s->whereNotNull($column);
            $op === 'is_not' ? $q->whereDoesntHave('sends', $has) : $q->whereHas('sends', $has);

            return;
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            match ($op) {
                'before' => $q->where($field, '<', $value),
                'after' => $q->where($field, '>', $value),
                'within_days' => $q->where($field, '>=', now()->subDays(max(0, (int) $value))),
                default => throw new InvalidArgumentException("Operator {$op} is not valid for dates."),
            };

            return;
        }

        $like = $value;

        match ($op) {
            'is' => $q->where($field, $value),
            'is_not' => $q->where(fn (Builder $w) => $w->where($field, '!=', $value)->orWhereNull($field)),
            'contains' => $q->whereLike($field, "%{$like}%"),
            'starts_with' => $q->whereLike($field, "{$like}%"),
            'ends_with' => $q->whereLike($field, "%{$like}"),
            default => throw new InvalidArgumentException("Operator {$op} is not valid for text."),
        };
    }

    private function assertValid(string $field, string $op): void
    {
        if (str_starts_with($field, 'field:')) {
            if (! preg_match('/^field:[a-z][a-z0-9_]{0,39}$/', $field) || ! array_key_exists($op, self::OPERATORS)) {
                throw new InvalidArgumentException('Unknown custom field.');
            }

            return;
        }
        if (! array_key_exists($field, self::FIELDS) || ! array_key_exists($op, self::OPERATORS)) {
            throw new InvalidArgumentException('Unknown segment field or operator.');
        }
        if ($field === 'status' && ! in_array($op, ['is', 'is_not'], true)) {
            throw new InvalidArgumentException('Status only supports is / is not.');
        }
        if (in_array($field, [...self::BOOLEAN_FIELDS, 'tag'], true) && ! in_array($op, ['is', 'is_not'], true)) {
            throw new InvalidArgumentException('Only is / is not are supported for this field.');
        }
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return array_map(fn (ContactStatus $s) => $s->value, ContactStatus::cases());
    }
}
