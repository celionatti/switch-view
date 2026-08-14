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
    private bool $debug = true;

    /**
     * In-memory cache for resolved view file paths.
     * @var array<string, string>
     */
    private array $resolvedPaths = [];

    /**
     * In-memory cache for compiled file paths.
     * @var array<string, string>
     */
    private array $compiledPaths = [];

    /**
     * @var array<string, string> Section name => content
     */
    private array $sections = [];

    /**
     * @var array<int, string> Active section name stack
     */
    private array $sectionStack = [];

    private ?string $layout = null;

    /**
     * @var array<string, mixed> Shared global data across all views
     */
    private array $sharedData = [];

    public function __construct(string $viewsPath, ?string $cachePath = null, ?TemplateCompiler $compiler = null)
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
        $this->cachePath = $cachePath ? rtrim($cachePath, '/\\') : sys_get_temp_dir() . '/switch_views';
        $this->compiler = $compiler ?? new TemplateCompiler();

        if (!is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0777, true);
        }
    }

    /**
     * Enable or disable debug mode (In production, set to false to bypass filemtime disk stat calls).
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Share global data with all rendered views.
     */
    public function share(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->sharedData = array_merge($this->sharedData, $key);
        } else {
            $this->sharedData[$key] = $value;
        }

        return $this;
    }

    /**
     * Register a custom component renderer.
     */
    public function component(string $name, callable|string $handler): self
    {
        \Switch\View\Component\ComponentRegistry::register($name, $handler);
        return $this;
    }

    public function render(string $view, array $data = []): string
    {
        $mergedData = empty($this->sharedData) ? $data : array_merge($this->sharedData, $data);

        $viewPath = $this->resolveViewPath($view);
        $compiledPath = $this->getCompiledPath($viewPath);

        // Fast-path for production: skip filemtime stat calls if compiled file exists
        $needsCompilation = false;
        if (!file_exists($compiledPath)) {
            $needsCompilation = true;
        } elseif ($this->debug && filemtime($viewPath) > filemtime($compiledPath)) {
            $needsCompilation = true;
        }

        if ($needsCompilation) {
            $contents = file_get_contents($viewPath);
            if ($contents === false) {
                throw new ViewNotFoundException("Unable to read view file '{$viewPath}'");
            }
            $compiled = $this->compiler->compile($contents);
            file_put_contents($compiledPath, $compiled);
        }

        $result = $this->evaluate($compiledPath, $mergedData);

        if ($this->layout !== null) {
            $layoutView = $this->layout;
            $this->layout = null;
            return $this->render($layoutView, $mergedData);
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
     * Ultra-fast universal property accessor supporting arrays, ArrayAccess, and objects.
     */
    public function get(mixed $target, string $key, mixed $default = null): mixed
    {
        if (is_array($target)) {
            return $target[$key] ?? (array_key_exists($key, $target) ? null : $default);
        }

        if (is_object($target)) {
            // Direct property access
            if (isset($target->{$key})) {
                return $target->{$key};
            }

            // ArrayAccess offset
            if ($target instanceof \ArrayAccess && $target->offsetExists($key)) {
                return $target->offsetGet($key);
            }

            // Getter method: getname() or getName()
            $getter = 'get' . $key;
            if (method_exists($target, $getter)) {
                return $target->{$getter}();
            }

            $getterUc = 'get' . ucfirst($key);
            if (method_exists($target, $getterUc)) {
                return $target->{$getterUc}();
            }

            if (property_exists($target, $key)) {
                return $target->{$key};
            }

            return $default;
        }

        return $default;
    }

    /**
     * Resolve template file path with in-memory caching.
     */
    private function resolveViewPath(string $view): string
    {
        if (isset($this->resolvedPaths[$view])) {
            return $this->resolvedPaths[$view];
        }

        $normalized = str_replace('.', '/', $view);
        $extensions = ['.switch.php', '.php', '.html'];

        foreach ($extensions as $ext) {
            $file = $this->viewsPath . '/' . $normalized . $ext;
            if (file_exists($file)) {
                return $this->resolvedPaths[$view] = $file;
            }
        }

        throw new ViewNotFoundException("View '{$view}' not found in path '{$this->viewsPath}'");
    }

    /**
     * Get compiled cache path with in-memory caching.
     */
    private function getCompiledPath(string $viewPath): string
    {
        return $this->compiledPaths[$viewPath] ??= $this->cachePath . '/' . md5($viewPath) . '.php';
    }

    /**
     * Execute compiled view template with extracted scope.
     */
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
