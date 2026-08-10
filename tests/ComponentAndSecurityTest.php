<?php

declare(strict_types=1);

namespace Switch\View\Tests;

use PHPUnit\Framework\TestCase;
use Switch\View\Engine\ViewEngine;
use Switch\View\Compiler\TemplateCompiler;
use Switch\View\Security\SecurityHelper;

class ComponentAndSecurityTest extends TestCase
{
    private ViewEngine $engine;
    private string $viewsDir;

    protected function setUp(): void
    {
        $this->viewsDir = sys_get_temp_dir() . '/switch_view_comp_test_' . uniqid();
        mkdir($this->viewsDir, 0777, true);
        $this->engine = new ViewEngine($this->viewsDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->viewsDir . '/*');
        if ($files) {
            foreach ($files as $f) {
                if (is_file($f)) unlink($f);
            }
        }
        if (is_dir($this->viewsDir)) {
            rmdir($this->viewsDir);
        }
    }

    public function testCardComponentRendering(): void
    {
        file_put_contents($this->viewsDir . '/card_test.php', '<x-card title="Dashboard">Welcome Back</x-card>');

        $rendered = $this->engine->render('card_test');
        $this->assertStringContainsString('switch-card', $rendered);
        $this->assertStringContainsString('Dashboard', $rendered);
        $this->assertStringContainsString('Welcome Back', $rendered);
    }

    public function testCardComponentWithNamedSlots(): void
    {
        file_put_contents($this->viewsDir . '/card_slots.php', '<x-card><x-slot name="title">My Title</x-slot>Main Body<x-slot name="footer">Footer Info</x-slot></x-card>');

        $rendered = $this->engine->render('card_slots');
        $this->assertStringContainsString('My Title', $rendered);
        $this->assertStringContainsString('Main Body', $rendered);
        $this->assertStringContainsString('Footer Info', $rendered);
    }

    public function testButtonComponent(): void
    {
        file_put_contents($this->viewsDir . '/button.php', '<x-button variant="success" type="submit">Submit Form</x-button>');

        $rendered = $this->engine->render('button');
        $this->assertStringContainsString('switch-btn-success', $rendered);
        $this->assertStringContainsString('type="submit"', $rendered);
        $this->assertStringContainsString('Submit Form', $rendered);
    }

    public function testAlertComponent(): void
    {
        file_put_contents($this->viewsDir . '/alert.php', '<x-alert type="danger" dismissible>Error occurred!</x-alert>');

        $rendered = $this->engine->render('alert');
        $this->assertStringContainsString('switch-alert-danger', $rendered);
        $this->assertStringContainsString('Error occurred!', $rendered);
        $this->assertStringContainsString('&times;', $rendered);
    }

    public function testInputComponent(): void
    {
        file_put_contents($this->viewsDir . '/input.php', '<x-input name="email" label="Email Address" value="user@test.com" error="Invalid email" />');

        $rendered = $this->engine->render('input');
        $this->assertStringContainsString('name="email"', $rendered);
        $this->assertStringContainsString('Email Address', $rendered);
        $this->assertStringContainsString('value="user@test.com"', $rendered);
        $this->assertStringContainsString('Invalid email', $rendered);
    }

    public function testModalComponent(): void
    {
        file_put_contents($this->viewsDir . '/modal.php', '<x-modal id="termsModal" title="Terms & Conditions">Please read terms</x-modal>');

        $rendered = $this->engine->render('modal');
        $this->assertStringContainsString('id="termsModal"', $rendered);
        $this->assertStringContainsString('Terms &amp; Conditions', $rendered);
        $this->assertStringContainsString('Please read terms', $rendered);
    }

    public function testBadgeComponent(): void
    {
        file_put_contents($this->viewsDir . '/badge.php', '<x-badge color="success">Active</x-badge>');

        $rendered = $this->engine->render('badge');
        $this->assertStringContainsString('Active', $rendered);
    }

    public function testAvatarComponent(): void
    {
        file_put_contents($this->viewsDir . '/avatar.php', '<x-avatar src="/img/user.jpg" alt="User Avatar" size="48px" />');

        $rendered = $this->engine->render('avatar');
        $this->assertStringContainsString('src="/img/user.jpg"', $rendered);
        $this->assertStringContainsString('alt="User Avatar"', $rendered);
    }

    public function testSpinnerComponent(): void
    {
        file_put_contents($this->viewsDir . '/spinner.php', '<x-spinner size="32px" color="#10b981" />');

        $rendered = $this->engine->render('spinner');
        $this->assertStringContainsString('<svg', $rendered);
        $this->assertStringContainsString('animation:spin', $rendered);
    }

    public function testSkeletonAndShimmerComponents(): void
    {
        file_put_contents($this->viewsDir . '/skeleton.php', '<x-skeleton type="card" /><x-shimmer width="50%" height="10px" />');

        $rendered = $this->engine->render('skeleton');
        $this->assertStringContainsString('switch-shimmer-bg', $rendered);
        $this->assertStringContainsString('width:50%', $rendered);
    }

    public function testReactiveMicroStateComponent(): void
    {
        file_put_contents($this->viewsDir . '/reactive.php', '<x-reactive component="counter" :state="[\'count\' => 5]"><span>Count</span></x-reactive>');

        $rendered = $this->engine->render('reactive');
        $this->assertStringContainsString('data-switch-reactive="counter"', $rendered);
        $this->assertStringContainsString('{"count":5}', $rendered);
    }

    public function testJsonComponent(): void
    {
        file_put_contents($this->viewsDir . '/json.php', '<x-json var="window.config" :data="[\'env\' => \'production\']" />');

        $rendered = $this->engine->render('json');
        $this->assertStringContainsString('window.config = {"env":"production"}', $rendered);
    }

    public function testCsrfHoneypotAndNonceDirectives(): void
    {
        file_put_contents($this->viewsDir . '/security.php', '<div>@csrf</div><div>@honeypot</div><script @nonce></script>');

        $rendered = $this->engine->render('security');
        $this->assertStringContainsString('<input type="hidden" name="_token"', $rendered);
        $this->assertStringContainsString('name="my_name_hp"', $rendered);
        $this->assertStringContainsString('nonce="', $rendered);
    }

    public function testCustomComponentRegistration(): void
    {
        $this->engine->component('custom-alert', function (array $attr, string $slot) {
            return '<div class="custom-alert">' . strtoupper($slot) . '</div>';
        });

        file_put_contents($this->viewsDir . '/custom_comp.php', '<x-custom-alert>Custom Hello</x-custom-alert>');

        $rendered = $this->engine->render('custom_comp');
        $this->assertEquals('<div class="custom-alert">CUSTOM HELLO</div>', $rendered);
    }

    public function testPartialTag(): void
    {
        file_put_contents($this->viewsDir . '/header.php', '<h1>Header Title</h1>');
        file_put_contents($this->viewsDir . '/page.php', '<partial name="header" /><main>Content</main>');

        $rendered = $this->engine->render('page');
        $this->assertStringContainsString('<h1>Header Title</h1>', $rendered);
        $this->assertStringContainsString('<main>Content</main>', $rendered);
    }

    public function testXssCleanHtmlSanitizer(): void
    {
        $dirty = '<p>Safe Text <script>alert("XSS")</script><img src="x" onerror="alert(1)"></p>';
        $clean = SecurityHelper::cleanHtml($dirty);

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('onerror=', $clean);
        $this->assertStringContainsString('<p>Safe Text', $clean);
    }

    public function testSafeJsonEncoderPreventsScriptBreakout(): void
    {
        $data = ['malicious' => '</script><script>alert(1)</script>'];
        $json = SecurityHelper::safeJson($data);

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringContainsString('\u003C/script\u003E', $json);
    }
}
