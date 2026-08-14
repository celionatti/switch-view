<?php

declare(strict_types=1);

namespace Switch\View\Tests;

use PHPUnit\Framework\TestCase;
use Switch\View\Engine\ViewEngine;

class ViewEnginePerformanceTest extends TestCase
{
    private string $tempViewsDir;
    private string $tempCacheDir;

    protected function setUp(): void
    {
        $this->tempViewsDir = sys_get_temp_dir() . '/switch_view_perf_' . uniqid();
        $this->tempCacheDir = sys_get_temp_dir() . '/switch_cache_perf_' . uniqid();

        mkdir($this->tempViewsDir, 0777, true);
        mkdir($this->tempCacheDir, 0777, true);

        // Create sample view file
        $template = <<<'HTML'
<div class="user-card">
    <h2>{{ $user.name }}</h2>
    <p>Email: {{ $user.email }}</p>
    <if cond="$user.isAdmin">
        <span class="badge">Admin</span>
    </if>
</div>
HTML;
        file_put_contents($this->tempViewsDir . '/card.switch.php', $template);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempViewsDir . '/*.*') ?: []);
        array_map('unlink', glob($this->tempCacheDir . '/*.*') ?: []);
        @rmdir($this->tempViewsDir);
        @rmdir($this->tempCacheDir);
    }

    public function testProductionModeBypassesFilemtimeAndExecutesBlazinglyFast(): void
    {
        $engine = new ViewEngine($this->tempViewsDir, $this->tempCacheDir);
        $engine->setDebug(false); // Production mode

        $data = [
            'user' => [
                'name' => 'Alex Turner',
                'email' => 'alex@example.com',
                'isAdmin' => true,
            ],
        ];

        // Warmup / Compile once
        $output1 = $engine->render('card', $data);
        $this->assertStringContainsString('Alex Turner', $output1);
        $this->assertStringContainsString('<span class="badge">Admin</span>', $output1);

        // Benchmark 1,000 renders
        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $engine->render('card', $data);
        }
        $elapsed = microtime(true) - $start;

        // 1,000 renders should execute in under 2.0 seconds in CLI test runner
        $this->assertTrue($elapsed < 2.0, "1,000 view renders took {$elapsed}s (expected < 2.0s)");
    }
}
