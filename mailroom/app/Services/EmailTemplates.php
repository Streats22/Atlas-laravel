<?php

declare(strict_types=1);

namespace App\Services;

final class EmailTemplates
{
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
