<?php

declare(strict_types=1);

namespace Atlas\Rendering;

/** Everything the wrapper needs to know about one block being rendered. */
final class RenderNode
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $domId,
        public readonly array $props,
        public readonly bool $container = false,
        public readonly bool $fullBleed = false,
    ) {
    }

    /** Strip anything that is not safe inside an HTML id. */
    public static function cleanId(mixed $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value);
    }
}
