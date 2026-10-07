<?php

declare(strict_types=1);

namespace Atlas\Tests\Unit;

use Atlas\Support\Tree;
use Atlas\Support\Url;
use PHPUnit\Framework\TestCase;

class TreeTest extends TestCase
{
    public function test_it_drops_invalid_nodes_and_fixes_ids(): void
    {
        $tree = Tree::sanitize([
            ['id' => 'a', 'type' => 'section', 'props' => ['x' => 1], 'children' => [
                ['id' => 'a', 'type' => 'text'],           // duplicate id
                ['type' => 'bad type!'],                   // invalid type
                'nonsense',
            ]],
            ['id' => '../evil', 'type' => 'text'],
        ]);

        $this->assertCount(2, $tree);
        $this->assertSame('a', $tree[0]['id']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertNotSame('a', $tree[0]['children'][0]['id']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{1,32}$/', $tree[1]['id']);
    }

    public function test_it_limits_depth(): void
    {
        $node = ['type' => 'section'];
        for ($i = 0; $i < 60; $i++) {
            $node = ['type' => 'section', 'children' => [$node]];
        }
        $json = json_encode(Tree::sanitize([$node]));
        $this->assertLessThanOrEqual(Tree::MAX_DEPTH + 1, substr_count($json, '"type":"section"'));
    }

    public function test_unsafe_url_schemes_are_neutralised(): void
    {
        $this->assertSame('#', Url::safe('javascript:alert(1)'));
        $this->assertSame('#', Url::safe(" java\tscript:alert(1)"));
        $this->assertSame('#', Url::safe('data:text/html,<script>'));
        $this->assertSame('https://example.com', Url::safe('https://example.com'));
        $this->assertSame('/about', Url::safe('/about'));
        $this->assertSame('mailto:a@b.c', Url::safe('mailto:a@b.c'));
    }
}
