<?php

declare(strict_types=1);

namespace Switch\View;

use Switch\View\Engine\ViewEngine;
use Switch\View\Security\SecurityHelper;

class View
{
    private static ?ViewEngine $engine = null;

    public static function setEngine(ViewEngine $engine): void
    {
        self::$engine = $engine;
    }

    public static function getEngine(): ?ViewEngine
    {
        return self::$engine;
    }

    public static function render(string $view, array $data = []): string
    {
        if (self::$engine === null) {
            throw new \RuntimeException('ViewEngine has not been set in View static container.');
        }

        return self::$engine->render($view, $data);
    }

    public static function share(string|array $key, mixed $value = null): void
    {
        if (self::$engine !== null) {
            self::$engine->share($key, $value);
        }
    }

    public static function component(string $name, callable|string $handler): void
    {
        \Switch\View\Component\ComponentRegistry::register($name, $handler);
    }

    public static function csrfField(): string
    {
        return SecurityHelper::csrfField();
    }

    public static function honeypot(string $name = 'my_name_hp', string $timeName = 'my_time_hp'): string
    {
        return SecurityHelper::honeypot($name, $timeName);
    }

    public static function cspNonce(): string
    {
        return SecurityHelper::getCspNonce();
    }

    public static function cleanHtml(string $html): string
    {
        return SecurityHelper::cleanHtml($html);
    }
}
