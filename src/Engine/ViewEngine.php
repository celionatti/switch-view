<?php

declare(strict_types=1);

namespace Switch\View\Engine;

use Switch\View\Compiler\TemplateCompiler;
use Switch\View\Exception\ViewNotFoundException;

class ViewEngine
{
    private TemplateCompiler $compiler;
    private string $viewsPath;
    private string $cachePath;

    /**
     * @var array<string, string> Section name => content
     */
    private array $sections = [];

    /**
     * @var array<int, string> Active section name stack
     */
    private array $sectionStack = [];

    private ?string $layout = null;

    public function __construct(string $viewsPath, ?string $cachePath = null, ?TemplateCompiler $compiler = null)
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
        $this->cachePath = $cachePath ? rtrim($cachePath, '/\\') : sys_get_temp_dir() . '/switch_views';
        $this->compiler = $compiler ?? new TemplateCompiler();

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }
    }

    public function render(string $view, array $data = []): string
    {
        $viewPath = $this->resolveViewPath($view);
        $compiledPath = $this->getCompiledPath($viewPath);

        if (!file_exists($compiledPath) || filemtime($viewPath) > filemtime($compiledPath)) {
            $contents = file_get_contents($viewPath);
            if ($contents === false) {
                throw new ViewNotFoundException("Unable to read view file '{$viewPath}'");
            }
            $compiled = $this->compiler->compile($contents);
            file_put_contents($compiledPath, $compiled);
        }

        $result = $this->evaluate($compiledPath, $data);

        if ($this->layout !== null) {
            $layoutView = $this->layout;
            $this->layout = null;
            return $this->render($layoutView, $data);
        }

        return $result;
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        if (empty($this->sectionStack)) {
            return;
        }

        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ob_get_clean() ?: '';
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Universal property accessor supporting arrays, ArrayAccess, and objects.
     *
     * Used by compiled dot-syntax expressions (e.g. $user.name compiles to $this->get($user, 'name')).
     * Works with:
     *   - Arrays: $user['name']
     *   - Objects: $user->name
     *   - ArrayAccess: $user['name'] via offsetGet
     */
    public function get(mixed $target, string $key, mixed $default = null): mixed
    {
        if (is_array($target)) {
            return array_key_exists($key, $target) ? $target[$key] : $default;
        }

        if (is_object($target)) {
            // ArrayAccess objects: try offset first
            if ($target instanceof \ArrayAccess && $target->offsetExists($key)) {
                return $target->offsetGet($key);
            }

            // Public property
            if (isset($target->{$key})) {
                return $target->{$key};
            }

            // Getter method: getName()
            $getter = 'get' . ucfirst($key);
            if (method_exists($target, $getter)) {
                return $target->{$getter}();
            }

            // isset returns false for null values; check property_exists too
            if (property_exists($target, $key)) {
                return $target->{$key};
            }

            return $default;
        }

        return $default;
    }

    private function resolveViewPath(string $view): string
    {
        $normalized = str_replace('.', '/', $view);
        $file = $this->viewsPath . '/' . $normalized . '.php';

        if (!file_exists($file)) {
            $fileHtml = $this->viewsPath . '/' . $normalized . '.html';
            if (file_exists($fileHtml)) {
                return $fileHtml;
            }
            throw new ViewNotFoundException("View '{$view}' not found at path '{$file}'");
        }

        return $file;
    }

    private function getCompiledPath(string $viewPath): string
    {
        return $this->cachePath . '/' . md5($viewPath) . '.php';
    }

    private function evaluate(string $__compiledPath, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();

        try {
            include $__compiledPath;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean() ?: '';
    }
}
