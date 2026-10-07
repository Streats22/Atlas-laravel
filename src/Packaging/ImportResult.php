<?php

declare(strict_types=1);

namespace Atlas\Packaging;

final class ImportResult
{
    public int $pagesCreated = 0;

    public int $pagesUpdated = 0;

    /** @var list<string> */
    public array $pagesSkipped = [];

    public int $blocksCreated = 0;

    public int $blocksUpdated = 0;

    /** @var list<string> */
    public array $blocksSkipped = [];

    public int $mediaCopied = 0;
}
