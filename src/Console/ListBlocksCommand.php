<?php

declare(strict_types=1);

namespace Atlas\Console;

use Atlas\Atlas;
use Atlas\Blocks\DbBlock;
use Illuminate\Console\Command;

class ListBlocksCommand extends Command
{
    protected $signature = 'atlas:blocks';

    protected $description = 'List every block registered with Atlas';

    public function handle(Atlas $atlas): int
    {
        $rows = collect($atlas->blocks()->all())->map(fn ($b) => [
            $b->type(),
            $b->label(),
            $b->category(),
            $b->container() ? 'yes' : '',
            count($b->fields()),
            $b instanceof DbBlock ? 'builder' : (str_starts_with($b::class, 'Atlas\\Blocks\\Builtin') ? 'built-in' : 'custom'),
        ])->values()->all();

        $this->table(['Type', 'Label', 'Group', 'Container', 'Fields', 'Source'], $rows);

        return self::SUCCESS;
    }
}
