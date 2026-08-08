<?php

declare(strict_types=1);

namespace Switch\View;

use Switch\View\Engine\ViewEngine;

class View
{
    private static ?ViewEngine $engine = null;

    public static function setEngine(ViewEngine $engine): void
    {
        self::$engine = $engine;
    }

    public static function render(string $view, array $data = []): string
    {
        if (self::$engine === null) {
            throw new \RuntimeException('ViewEngine has not been set in View static container.');
        }

        return self::$engine->render($view, $data);
    }
}
