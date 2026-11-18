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
        $contact->tags()->sync(array_map(fn (string $n) => $this->tag($contact, $n)->id, $names));
    }

    public function attach(Contact $contact, string $name): void
    {
        $contact->tags()->syncWithoutDetaching([$this->tag($contact, $name)->id]);
    }

    public function detach(Contact $contact, string $name): void
    {
        $tag = $contact->audience->tags()->where('name', trim($name))->first();
        if ($tag !== null) {
            $contact->tags()->detach($tag->id);
        }
    }

    private function tag(Contact $contact, string $name): Tag
    {
        return $contact->audience->tags()->firstOrCreate(['name' => mb_substr(trim($name), 0, 60)]);
    }
}
