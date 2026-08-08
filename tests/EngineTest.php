<?php

declare(strict_types=1);

namespace Switch\View\Tests;

use PHPUnit\Framework\TestCase;
use Switch\View\Engine\ViewEngine;

class EngineTest extends TestCase
{
    private string $tempViewsDir;
    private string $tempCacheDir;

    protected function setUp(): void
    {
        $this->tempViewsDir = sys_get_temp_dir() . '/switch_test_views_' . uniqid();
        $this->tempCacheDir = sys_get_temp_dir() . '/switch_test_cache_' . uniqid();

        mkdir($this->tempViewsDir, 0777, true);
        mkdir($this->tempCacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempViewsDir);
        $this->removeDir($this->tempCacheDir);
    }

    public function testRenderViewWithVariablesAndConditionals(): void
    {
        $templateContent = '<h1>Hello {{ $name }}</h1><if cond="$isAdmin"><p>Admin Panel</p></if>';
        file_put_contents($this->tempViewsDir . '/home.php', $templateContent);

        $engine = new ViewEngine($this->tempViewsDir, $this->tempCacheDir);
        $html = $engine->render('home', ['name' => 'Alice', 'isAdmin' => true]);

        $this->assertStringContainsString('<h1>Hello Alice</h1>', $html);
        $this->assertStringContainsString('<p>Admin Panel</p>', $html);
    }

    public function testRenderLayoutWithSections(): void
    {
        $layoutContent = '<html><body><yield name="content" /></body></html>';
        file_put_contents($this->tempViewsDir . '/layout.php', $layoutContent);

        $pageContent = '<extends name="layout" /><section name="content"><h1>Subpage Content</h1></section>';
        file_put_contents($this->tempViewsDir . '/subpage.php', $pageContent);

        $engine = new ViewEngine($this->tempViewsDir, $this->tempCacheDir);
        $html = $engine->render('subpage');

        $this->assertEquals('<html><body><h1>Subpage Content</h1></body></html>', $html);
    }

    public function testDotSyntaxWithArrayData(): void
    {
        $template = '<p>{{ $user.name }}</p>';
        file_put_contents($this->tempViewsDir . '/arr.php', $template);

        $engine = new ViewEngine($this->tempViewsDir, $this->tempCacheDir);
        $html = $engine->render('arr', ['user' => ['name' => 'Alice']]);

        $this->assertStringContainsString('<p>Alice</p>', $html);
    }

    public function testDotSyntaxWithObjectData(): void
    {
        $template = '<p>{{ $user.name }}</p>';
        file_put_contents($this->tempViewsDir . '/obj.php', $template);

        $user = new \stdClass();
        $user->name = 'Bob';

        $engine = new ViewEngine($this->tempViewsDir, $this->tempCacheDir);
        $html = $engine->render('obj', ['user' => $user]);

        $this->assertStringContainsString('<p>Bob</p>', $html);
    }

    public function testDotSyntaxWithNestedAccess(): void
    {
        $template = '<p>{{ $user.profile.city }}</p>';
        file_put_contents($this->tempViewsDir . '/nested.php', $template);

        // Nested array
        $engine = new ViewEngine($this->tempViewsDir, $this->tempCacheDir);
        $html = $engine->render('nested', ['user' => ['profile' => ['city' => 'Lagos']]]);
        $this->assertStringContainsString('<p>Lagos</p>', $html);
    }

    public function testDotSyntaxWithMixedArrayAndObject(): void
    {
        $template = '<p>{{ $user.profile.city }}</p>';
        file_put_contents($this->tempViewsDir . '/mixed.php', $template);

        // Array wrapping an object
        $profile = new \stdClass();
        $profile->city = 'Nairobi';

        $engine = new ViewEngine($this->tempViewsDir, $this->tempCacheDir);
        $html = $engine->render('mixed', ['user' => ['profile' => $profile]]);
        $this->assertStringContainsString('<p>Nairobi</p>', $html);
    }

    public function testBracketSyntaxStillWorks(): void
    {
        // Traditional $user['name'] still compiles and works
        $template = "<p>{{ \$user['name'] }}</p>";
        file_put_contents($this->tempViewsDir . '/bracket.php', $template);

        $engine = new ViewEngine($this->tempViewsDir, $this->tempCacheDir);
        $html = $engine->render('bracket', ['user' => ['name' => 'Charlie']]);
        $this->assertStringContainsString('<p>Charlie</p>', $html);
    }

    public function testArrowSyntaxStillWorks(): void
    {
        // Traditional $user->name still compiles and works
        $template = "<p>{{ \$user->name }}</p>";
        file_put_contents($this->tempViewsDir . '/arrow.php', $template);

        $user = new \stdClass();
        $user->name = 'Diana';

        $engine = new ViewEngine($this->tempViewsDir, $this->tempCacheDir);
        $html = $engine->render('arrow', ['user' => $user]);
        $this->assertStringContainsString('<p>Diana</p>', $html);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
