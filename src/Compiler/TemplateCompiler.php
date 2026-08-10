<?php

declare(strict_types=1);

namespace Switch\View\Compiler;

use Switch\View\Component\ComponentRegistry;

class TemplateCompiler
{
    public function compile(string $contents): string
    {
        // 1. Comments {{-- comment --}}
        $contents = preg_replace('/\{\{--(.*?)--\}\}/s', '<?php /*$1*/ ?>', $contents) ?? $contents;

        // 2. Custom Raw PHP blocks <php>...</php>
        $contents = preg_replace('/<php>(.*?)<\/php>/s', '<?php $1 ?>', $contents) ?? $contents;

        // 3. Security Shortcut Directives: @csrf, @honeypot, @nonce
        $contents = preg_replace('/@csrf\b/i', '<?= \Switch\View\Security\SecurityHelper::csrfField(); ?>', $contents) ?? $contents;
        $contents = preg_replace('/@honeypot\b/i', '<?= \Switch\View\Security\SecurityHelper::honeypot(); ?>', $contents) ?? $contents;
        $contents = preg_replace('/@nonce\b/i', 'nonce="<?= \Switch\View\Security\SecurityHelper::getCspNonce(); ?>"', $contents) ?? $contents;

        // 4. Raw Interpolation {!! $expr !!}
        $contents = preg_replace_callback('/\{\!\!\s*(.*?)\s*\!\!\}/s', function ($m) {
            $expr = $this->compileDotSyntax($m[1]);
            return "<?= (string) ({$expr}); ?>";
        }, $contents) ?? $contents;

        // 5. Escaped Interpolation {{ $expr }}
        $contents = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/s', function ($m) {
            $expr = $this->compileDotSyntax($m[1]);
            return "<?= htmlspecialchars((string) ({$expr}), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>";
        }, $contents) ?? $contents;

        // 6. Component Tags: <x-name ...>...</x-name> and self-closing <x-name ... />
        $contents = $this->compileComponentTags($contents);

        // 7. Partials: <partial name="partials.header" ... /> and <include file="partials.header" ... />
        $contents = preg_replace_callback('/<(?:include|partial)\s+(?:file|name)=[\'"]([^\'"]+)[\'"](?:\s+(?:data|with)=[\'"]([^\'"]*)[\'"])?\s*\/?>/i', function ($m) {
            $file = $m[1];
            $data = !empty($m[2]) ? $this->compileDotSyntax($m[2]) : '[]';
            return '<?= $this->render(\'' . $file . '\', ' . $data . '); ?>';
        }, $contents) ?? $contents;

        // 8. Layout Extension: <layout name="layouts.app" /> or <extends name="layouts.app" />
        $contents = preg_replace_callback('/<(?:layout|extends)\s+name=[\'"]([^\'"]+)[\'"]\s*\/?>/i', function ($m) {
            return '<?php $this->extend(\'' . $m[1] . '\'); ?>';
        }, $contents) ?? $contents;

        // 9. Section Start: <section name="content">
        $contents = preg_replace_callback('/<section\s+name=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            return '<?php $this->startSection(\'' . $m[1] . '\'); ?>';
        }, $contents) ?? $contents;

        // Section End: </section>
        $contents = preg_replace('/<\/section>/i', '<?php $this->endSection(); ?>', $contents) ?? $contents;

        // 10. Yield Section: <yield name="content" default="Default Content" />
        $contents = preg_replace_callback('/<yield\s+name=[\'"]([^\'"]+)[\'"](?:\s+default=[\'"]([^\'"]*)[\'"])?\s*\/?>/i', function ($m) {
            $name = $m[1];
            $default = isset($m[2]) ? '\'' . addslashes($m[2]) . '\'' : '\'\'';
            return '<?= $this->yieldSection(\'' . $name . '\', ' . $default . '); ?>';
        }, $contents) ?? $contents;

        // 11. If / Elseif / Else / Endif
        $contents = preg_replace_callback('/<if\s+cond=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            $cond = $this->compileDotSyntax($m[1]);
            return '<?php if (' . $cond . '): ?>';
        }, $contents) ?? $contents;

        $contents = preg_replace_callback('/<elseif\s+cond=[\'"]([^\'"]+)[\'"]\s*\/?>/i', function ($m) {
            $cond = $this->compileDotSyntax($m[1]);
            return '<?php elseif (' . $cond . '): ?>';
        }, $contents) ?? $contents;

        $contents = preg_replace('/<else\s*\/?>/i', '<?php else: ?>', $contents) ?? $contents;
        $contents = preg_replace('/<\/if>/i', '<?php endif; ?>', $contents) ?? $contents;

        // 12. Unless / Endunless
        $contents = preg_replace_callback('/<unless\s+cond=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            $cond = $this->compileDotSyntax($m[1]);
            return '<?php if (!(' . $cond . ')): ?>';
        }, $contents) ?? $contents;

        $contents = preg_replace('/<\/unless>/i', '<?php endif; ?>', $contents) ?? $contents;

        // 13. Foreach / Endforeach
        $contents = preg_replace_callback('/<foreach\s+items=[\'"]([^\'"]+)[\'"]\s+as=[\ me2="]+[\'"]\s*>/i', function ($m) {
            $items = $this->compileDotSyntax($m[1]);
            return '<?php foreach (' . $items . ' as ' . $m[2] . '): ?>';
        }, $contents) ?? $contents;

        $contents = preg_replace_callback('/<foreach\s+items=[\'"]([^\'"]+)[\'"]\s+as=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            $items = $this->compileDotSyntax($m[1]);
            return '<?php foreach (' . $items . ' as ' . $m[2] . '): ?>';
        }, $contents) ?? $contents;

        $contents = preg_replace('/<\/foreach>/i', '<?php endforeach; ?>', $contents) ?? $contents;

        // 14. For / Endfor
        $contents = preg_replace_callback('/<for\s+var=[\'"]([^\'"]+)[\'"]\s+cond=[\'"]([^\'"]+)[\'"]\s+incr=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            return '<?php for (' . $m[1] . '; ' . $m[2] . '; ' . $m[3] . '): ?>';
        }, $contents) ?? $contents;

        $contents = preg_replace('/<\/for>/i', '<?php endfor; ?>', $contents) ?? $contents;

        // 15. While / Endwhile
        $contents = preg_replace_callback('/<while\s+cond=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            $cond = $this->compileDotSyntax($m[1]);
            return '<?php while (' . $cond . '): ?>';
        }, $contents) ?? $contents;

        $contents = preg_replace('/<\/while>/i', '<?php endwhile; ?>', $contents) ?? $contents;

        return $contents;
    }

    /**
     * Compile <x-component-name ...> and <x-component-name ... /> tags.
     */
    private function compileComponentTags(string $contents): string
    {
        // Attribute matcher that respects quoted strings containing > (e.g. :state="['a' => 'b']")
        $attrPattern = '((?:[^"\'>]|"[^"]*"|\'[^\']*\')*)';

        // 1. Pair component tags: <x-card title="...">slot</x-card>
        $patternPair = '/<x-([a-zA-Z0-9_\-\.]+)\s*' . $attrPattern . '\s*>(.*?)<\/x-\1>/s';

        // 2. Self-closing component tags: <x-card title="..." />
        $patternSelfClosing = '/<x-([a-zA-Z0-9_\-\.]+)\s*' . $attrPattern . '\s*\/?>/s';

        // Compile pair tags first (to capture nested slots)
        $contents = preg_replace_callback($patternPair, function ($matches) {
            $name = strtolower($matches[1]);
            $rawAttr = $matches[2];
            $inner = $matches[3];

            $attributesPhp = $this->parseAttributesPhp($rawAttr);
            [$slotPhp, $slotsPhp] = $this->extractSlots($inner);

            return "<?= \\Switch\\View\\Component\\ComponentRegistry::render('{$name}', {$attributesPhp}, {$slotPhp}, {$slotsPhp}); ?>";
        }, $contents) ?? $contents;

        // Compile remaining self-closing tags
        $contents = preg_replace_callback($patternSelfClosing, function ($matches) {
            $name = strtolower($matches[1]);
            $rawAttr = $matches[2];

            if (str_starts_with(trim($rawAttr), '/')) {
                return $matches[0];
            }

            $attributesPhp = $this->parseAttributesPhp($rawAttr);

            return "<?= \\Switch\\View\\Component\\ComponentRegistry::render('{$name}', {$attributesPhp}, '', []); ?>";
        }, $contents) ?? $contents;

        return $contents;
    }

    /**
     * Parse HTML attribute string into a PHP array code string.
     * E.g.: `title="Hello" :user="$user" dismissible` -> `['title' => 'Hello', 'user' => $user, 'dismissible' => true]`
     */
    private function parseAttributesPhp(string $attrString): string
    {
        if (trim($attrString) === '') {
            return '[]';
        }

        $items = [];
        $pattern = '/(?::([a-zA-Z0-9_\-]+)|([a-zA-Z0-9_\-]+))(?:=(?:"([^"]*)"|\'([^\']*)\'))?/';

        preg_match_all($pattern, $attrString, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $isDynamic = !empty($m[1]);
            $key = addslashes($isDynamic ? $m[1] : $m[2]);

            if ($key === '/') {
                continue;
            }

            $value = $m[3] ?? ($m[4] ?? null);

            if ($value !== null) {
                if ($isDynamic) {
                    $expr = $this->compileDotSyntax($value);
                    $items[] = "'{$key}' => ({$expr})";
                } else {
                    $escapedVal = addslashes($value);
                    $items[] = "'{$key}' => '{$escapedVal}'";
                }
            } else {
                $items[] = "'{$key}' => true";
            }
        }

        return '[' . implode(', ', $items) . ']';
    }

    /**
     * Extract default slot and named slots (<x-slot name="title">...</x-slot>).
     */
    private function extractSlots(string $innerContent): array
    {
        $slots = [];

        // Match <x-slot name="header">...</x-slot> or <x-slot:header>...</x-slot:header>
        $pattern = '/<x-slot(?::([a-zA-Z0-9_\-]+)|\s+name=[\'"]([^\'"]+)[\'"])\s*>(.*?)<\/x-slot(?::\1)?\s*>/s';

        $slotBody = preg_replace_callback($pattern, function ($m) use (&$slots) {
            $name = !empty($m[1]) ? $m[1] : $m[2];
            $content = trim($m[3]);
            $slots[$name] = $content;
            return '';
        }, $innerContent) ?? $innerContent;

        $slotBody = trim($slotBody);

        // Prepare slot PHP
        $slotPhp = $slotBody !== '' ? var_export($slotBody, true) : "''";

        $slotsPhpItems = [];
        foreach ($slots as $name => $content) {
            $slotsPhpItems[] = "'" . addslashes($name) . "' => " . var_export($content, true);
        }
        $slotsPhp = '[' . implode(', ', $slotsPhpItems) . ']';

        return [$slotPhp, $slotsPhp];
    }

    private function compileDotSyntax(string $expression): string
    {
        // Replace $user.name or $user.profile.name with $this->get($user, 'name')
        return preg_replace_callback('/(\$[a-zA-Z_][a-zA-Z0-9_]*)(?:\.([a-zA-Z_][a-zA-Z0-9_]*))+/', function ($matches) {
            $parts = explode('.', ltrim($matches[0], '$'));
            $root = '$' . array_shift($parts);
            foreach ($parts as $part) {
                $root = "\$this->get({$root}, '{$part}')";
            }
            return $root;
        }, $expression) ?? $expression;
    }
}
