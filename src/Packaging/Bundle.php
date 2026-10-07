<?php

declare(strict_types=1);

namespace Atlas\Packaging;

use InvalidArgumentException;

/** A portable snapshot of a site's Atlas content: pages, builder blocks and the media they use. */
final class Bundle
{
    public const FORMAT = 1;

    /**
     * @param  list<array<string, mixed>>  $pages
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<string>  $media  Disk-relative upload paths
     */
    public function __construct(
        public readonly array $pages = [],
        public readonly array $blocks = [],
        public readonly array $media = [],
        public readonly ?string $exportedAt = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->pages === [] && $this->blocks === [];
    }

    public function toArray(): array
    {
        return [
            'format' => self::FORMAT,
            'generator' => 'streats22/atlas',
            'exported_at' => $this->exportedAt ?? now()->toIso8601String(),
            'pages' => $this->pages,
            'blocks' => $this->blocks,
            'media' => $this->media,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function fromArray(array $data): self
    {
        if (($data['format'] ?? null) !== self::FORMAT) {
            throw new InvalidArgumentException('Unsupported Atlas bundle format: ' . json_encode($data['format'] ?? null));
        }

        return new self(
            (array) ($data['pages'] ?? []),
            (array) ($data['blocks'] ?? []),
            array_values(array_filter((array) ($data['media'] ?? []), 'is_string')),
            $data['exported_at'] ?? null,
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new InvalidArgumentException('The bundle is not valid JSON.');
        }

        return self::fromArray($data);
    }
}
