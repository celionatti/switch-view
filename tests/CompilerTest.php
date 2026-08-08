<?php

declare(strict_types=1);

namespace Switch\View\Tests;

use PHPUnit\Framework\TestCase;
use Switch\View\Compiler\TemplateCompiler;

class CompilerTest extends TestCase
{
    private TemplateCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new TemplateCompiler();
    }

    public function testCompileInterpolation(): void
    {
        $template = '<h1>{{ $title }}</h1><div>{!! $rawHtml !!}</div>';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString("<?= htmlspecialchars((string) (\$title)", $compiled);
        $this->assertStringContainsString("<?= (string) (\$rawHtml); ?>", $compiled);
    }

    public function testCompileIfTagSyntax(): void
    {
        $template = '<if cond="$user"><p>Welcome {{ $user }}</p><elseif cond="$guest"/><p>Hello Guest</p><else/><p>Unknown</p></if>';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString('<?php if ($user): ?>', $compiled);
        $this->assertStringContainsString('<?php elseif ($guest): ?>', $compiled);
        $this->assertStringContainsString('<?php else: ?>', $compiled);
        $this->assertStringContainsString('<?php endif; ?>', $compiled);
    }

    public function testCompileUnlessTagSyntax(): void
    {
        $template = '<unless cond="$isLoggedIn"><a href="/login">Login</a></unless>';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString('<?php if (!($isLoggedIn)): ?>', $compiled);
        $this->assertStringContainsString('<?php endif; ?>', $compiled);
    }

    public function testCompileForeachTagSyntax(): void
    {
        $template = '<ul><foreach items="$items" as="$item"><li>{{ $item }}</li></foreach></ul>';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString('<?php foreach ($items as $item): ?>', $compiled);
        $this->assertStringContainsString('<?php endforeach; ?>', $compiled);
    }

    public function testCompileForAndWhileTagSyntax(): void
    {
        $template = '<for var="$i=0" cond="$i<3" incr="$i++"><span>{{ $i }}</span></for>';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString('<?php for ($i=0; $i<3; $i++): ?>', $compiled);
        $this->assertStringContainsString('<?php endfor; ?>', $compiled);
    }

    public function testCompileLayoutsAndSections(): void
    {
        $template = '<extends name="layouts.app" /><section name="content"><h1>Page Title</h1></section>';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString("<?php \$this->extend('layouts.app'); ?>", $compiled);
        $this->assertStringContainsString("<?php \$this->startSection('content'); ?>", $compiled);
        $this->assertStringContainsString("<?php \$this->endSection(); ?>", $compiled);
    }

    public function testCompileDotSyntaxAccessForInterpolation(): void
    {
        // $user.name => $this->get($user, 'name')
        $template = '{{ $user.name }}';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString("\$this->get(\$user, 'name')", $compiled);
    }

    public function testCompileNestedDotSyntaxAccess(): void
    {
        // $user.profile.avatar => $this->get($this->get($user, 'profile'), 'avatar')
        $template = '{{ $user.profile.avatar }}';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString("\$this->get(\$this->get(\$user, 'profile'), 'avatar')", $compiled);
    }

    public function testDotSyntaxInConditions(): void
    {
        $template = '<if cond="$user.isAdmin"><p>Admin</p></if>';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString("\$this->get(\$user, 'isAdmin')", $compiled);
    }

    public function testPlainVariablesUnchangedByDotSyntax(): void
    {
        // $user without dots should NOT be transformed
        $template = '{{ $user }}';
        $compiled = $this->compiler->compile($template);

        $this->assertStringContainsString('($user)', $compiled);
    }
}
