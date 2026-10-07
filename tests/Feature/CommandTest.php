<?php

declare(strict_types=1);

namespace Atlas\Tests\Feature;

use Atlas\Tests\TestCase;
use Illuminate\Support\Facades\File;

class CommandTest extends TestCase
{
    public function test_make_block_generates_a_discoverable_class_and_view(): void
    {
        $class = app_path('Atlas/Blocks/PricingTable.php');
        $view = resource_path('views/atlas/blocks/pricing-table.blade.php');
        File::delete([$class, $view]);

        $this->artisan('atlas:make-block', ['name' => 'PricingTable'])->assertSuccessful();

        $this->assertFileExists($class);
        $this->assertFileExists($view);
        $this->assertStringContainsString("return 'pricing-table';", file_get_contents($class));
        $this->assertNotFalse(token_get_all(file_get_contents($class)));
        exec('php -l ' . escapeshellarg($class), $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));

        $this->artisan('atlas:make-block', ['name' => 'PricingTable'])->assertFailed();

        File::delete([$class, $view]);
    }

    public function test_install_command_runs_without_touching_the_filesystem_when_asked(): void
    {
        $this->artisan('atlas:install', ['--no-migrate' => true, '--no-storage-link' => true])
            ->expectsOutputToContain('Atlas is installed')
            ->expectsOutputToContain('atlas:demo')
            ->assertSuccessful();
    }
}
