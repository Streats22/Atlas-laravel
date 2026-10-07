<?php

declare(strict_types=1);

namespace Atlas\Templates;

final class BlankTemplate extends PageTemplate
{
    public function key(): string
    {
        return 'blank';
    }

    public function tree(NodeFactory $nodes): array
    {
        return [];
    }
}
