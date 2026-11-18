<?php

declare(strict_types=1);

namespace App\Services;

use App\Builder\EmailDesign;
use App\Models\EmailTemplate;

final class EmailTemplates
{
    public function __construct(private readonly EmailDesign $design) {}

    /**
     * Starting content for a new campaign: 'builder' (drag & drop starter), a coded template key,
     * or 'saved:{id}' for a template the user saved earlier.
     *
     * @return array{html:string, design:array<string, mixed>|null}
     */
    public function start(string $key): array
    {
        if ($key === 'builder') {
            return ['html' => '', 'design' => $this->design->starter()];
        }
        if (preg_match('/^saved:(\d+)$/', $key, $m) && $saved = EmailTemplate::query()->find((int) $m[1])) {
            return ['html' => $saved->html, 'design' => $saved->design];
        }

        return ['html' => $this->get($key), 'design' => null];
    }

    /** @return array<string, string> key => label */
    public function names(): array
    {
        return [
            'simple' => 'Simple text',
            'newsletter' => 'Newsletter',
            'announcement' => 'Product announcement',
        ];
    }

    public function get(string $key): string
    {
        $key = array_key_exists($key, $this->names()) ? $key : 'simple';

        return (string) file_get_contents(resource_path("email-templates/{$key}.html"));
    }
}
