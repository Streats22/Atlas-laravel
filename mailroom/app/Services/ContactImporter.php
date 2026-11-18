<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactStatus;
use App\Models\Audience;
use App\Models\Contact;

final class ContactImporter
{
    private const HEADER_ALIASES = [
        'email' => ['email', 'email address', 'e-mail', 'mail'],
        'first_name' => ['first_name', 'first name', 'firstname', 'voornaam', 'fname'],
        'last_name' => ['last_name', 'last name', 'lastname', 'achternaam', 'lname'],
        'tags' => ['tags', 'tag'],
    ];

    public function __construct(private readonly TagSync $tags) {}

    /** @return array{created:int, updated:int, skipped:int} */
    public function import(Audience $audience, string $path, string $status, ?string $tag = null): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $result;
        }

        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', trim($first)), $delimiter);
        $map = $this->mapHeader($header);

        if (! isset($map['email'])) {
            fclose($handle);

            return $result;
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $email = strtolower(trim((string) ($row[$map['email']] ?? '')));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['skipped']++;

                continue;
            }

            /** @var Contact|null $contact */
            $contact = $audience->contacts()->where('email', $email)->first();
            $attributes = array_filter([
                'first_name' => isset($map['first_name']) ? mb_substr(trim((string) ($row[$map['first_name']] ?? '')), 0, 100) : null,
                'last_name' => isset($map['last_name']) ? mb_substr(trim((string) ($row[$map['last_name']] ?? '')), 0, 100) : null,
            ], fn ($v) => $v !== null && $v !== '');

            if ($contact === null) {
                $contact = $audience->contacts()->create($attributes + [
                    'email' => $email,
                    'status' => $status,
                    'source' => 'import',
                    'subscribed_at' => $status === ContactStatus::Subscribed->value ? now() : null,
                ]);
                $result['created']++;
            } else {
                // Never resubscribe someone who opted out: only names are refreshed.
                $contact->update($attributes);
                $result['updated']++;
            }

            $tagNames = isset($map['tags']) ? explode(',', (string) ($row[$map['tags']] ?? '')) : [];
            foreach (array_filter([...$tagNames, $tag], fn ($t) => trim((string) $t) !== '') as $name) {
                $this->tags->attach($contact, (string) $name);
            }
        }
        fclose($handle);

        return $result;
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $name) {
            $name = strtolower(trim((string) $name));
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($name, $aliases, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }
}
