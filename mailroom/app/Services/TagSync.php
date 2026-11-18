<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use App\Models\Tag;

final class TagSync
{
    /** @param  list<string>  $names */
    public function sync(Contact $contact, array $names): void
    {
        $changes = $contact->tags()->sync(array_map(fn (string $n) => $this->tag($contact, $n)->id, $names));
        $this->triggerAttached($contact, $changes['attached']);
    }

    public function attach(Contact $contact, string $name): void
    {
        if (trim($name) === '') {
            return;
        }
        $changes = $contact->tags()->syncWithoutDetaching([$this->tag($contact, $name)->id]);
        $this->triggerAttached($contact, $changes['attached']);
    }

    public function detach(Contact $contact, string $name): void
    {
        $tag = $contact->audience->tags()->where('name', trim($name))->first();
        if ($tag !== null) {
            $contact->tags()->detach($tag->id);
        }
    }

    /** @param  array<int, int>  $tagIds */
    private function triggerAttached(Contact $contact, array $tagIds): void
    {
        if ($tagIds === [] || $contact->source === 'import') {
            return;
        }
        foreach (Tag::query()->whereKey($tagIds)->pluck('name') as $name) {
            app(AutomationEngine::class)->onTagAdded($contact, $name);
        }
    }

    private function tag(Contact $contact, string $name): Tag
    {
        return $contact->audience->tags()->firstOrCreate(['name' => mb_substr(trim($name), 0, 60)]);
    }
}
