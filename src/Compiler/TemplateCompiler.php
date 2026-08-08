<?php

declare(strict_types=1);

namespace Switch\View\Compiler;

class TemplateCompiler
{
    public function compile(string $contents): string
    {
        // 1. Comments {{-- comment --}}
        $contents = preg_replace('/\{\{--(.*?)--\}\}/s', '<?php /*$1*/ ?>', $contents) ?? $contents;

        // 2. Custom Raw PHP blocks <php>...</php>
        $contents = preg_replace('/<php>(.*?)<\/php>/s', '<?php $1 ?>', $contents) ?? $contents;

        // 3. Raw Interpolation {!! $expr !!}
        $contents = preg_replace_callback('/\{\!\!\s*(.*?)\s*\!\!\}/s', function ($m) {
            $expr = $this->compileDotSyntax($m[1]);
            return "<?= (string) ({$expr}); ?>";
        }, $contents) ?? $contents;

        // 4. Escaped Interpolation {{ $expr }}
        $contents = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/s', function ($m) {
            $expr = $this->compileDotSyntax($m[1]);
            return "<?= htmlspecialchars((string) ({$expr}), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>";
        }, $contents) ?? $contents;

        // 5. Layout Extension: <layout name="layouts.app" /> or <extends name="layouts.app" />
        $contents = preg_replace_callback('/<(?:layout|extends)\s+name=[\'"]([^\'"]+)[\'"]\s*\/?>/i', function ($m) {
            return '<?php $this->extend(\'' . $m[1] . '\'); ?>';
        }, $contents) ?? $contents;

        // 6. Section Start: <section name="content">
        $contents = preg_replace_callback('/<section\s+name=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            return '<?php $this->startSection(\'' . $m[1] . '\'); ?>';
        }, $contents) ?? $contents;

        // Section End: </section>
        $contents = preg_replace('/<\/section>/i', '<?php $this->endSection(); ?>', $contents) ?? $contents;

        // 7. Yield Section: <yield name="content" default="Default Content" />
        $contents = preg_replace_callback('/<yield\s+name=[\'"]([^\'"]+)[\'"](?:\s+default=[\'"]([^\'"]*)[\'"])?\s*\/?>/i', function ($m) {
            $name = $m[1];
            $default = isset($m[2]) ? '\'' . addslashes($m[2]) . '\'' : '\'\'';
            return '<?= $this->yieldSection(\'' . $name . '\', ' . $default . '); ?>';
        }, $contents) ?? $contents;

        // 8. Includes: <include file="partials.header" data="..." />
        $contents = preg_replace_callback('/<include\s+file=[\'"]([^\'"]+)[\'"](?:\s+data=[\'"]([^\'"]*)[\'"])?\s*\/?>/i', function ($m) {
            $file = $m[1];
            $data = !empty($m[2]) ? $m[2] : '[]';
            return '<?= $this->render(\'' . $file . '\', ' . $data . '); ?>';
        }, $contents) ?? $contents;

        // 9. If / Elseif / Else / Endif
        // <if cond="...">
        $contents = preg_replace_callback('/<if\s+cond=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            $cond = $this->compileDotSyntax($m[1]);
            return '<?php if (' . $cond . '): ?>';
        }, $contents) ?? $contents;

        // <elseif cond="..." /> or <elseif cond="...">
        $contents = preg_replace_callback('/<elseif\s+cond=[\'"]([^\'"]+)[\'"]\s*\/?>/i', function ($m) {
            $cond = $this->compileDotSyntax($m[1]);
            return '<?php elseif (' . $cond . '): ?>';
        }, $contents) ?? $contents;

        // <else /> or <else>
        $contents = preg_replace('/<else\s*\/?>/i', '<?php else: ?>', $contents) ?? $contents;

        // </if>
        $contents = preg_replace('/<\/if>/i', '<?php endif; ?>', $contents) ?? $contents;

        // 10. Unless / Endunless
        // <unless cond="...">
        $contents = preg_replace_callback('/<unless\s+cond=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            $cond = $this->compileDotSyntax($m[1]);
            return '<?php if (!(' . $cond . ')): ?>';
        }, $contents) ?? $contents;

        // </unless>
        $contents = preg_replace('/<\/unless>/i', '<?php endif; ?>', $contents) ?? $contents;

        // 11. Foreach / Endforeach
        // <foreach items="..." as="...">
        $contents = preg_replace_callback('/<foreach\s+items=[\'"]([^\'"]+)[\'"]\s+as=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            $items = $this->compileDotSyntax($m[1]);
            return '<?php foreach (' . $items . ' as ' . $m[2] . '): ?>';
        }, $contents) ?? $contents;

        // </foreach>
        $contents = preg_replace('/<\/foreach>/i', '<?php endforeach; ?>', $contents) ?? $contents;

        // 12. For / Endfor
        // <for var="$i = 0" cond="$i < 10" incr="$i++">
        $contents = preg_replace_callback('/<for\s+var=[\'"]([^\'"]+)[\'"]\s+cond=[\'"]([^\'"]+)[\'"]\s+incr=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            return '<?php for (' . $m[1] . '; ' . $m[2] . '; ' . $m[3] . '): ?>';
        }, $contents) ?? $contents;

        // </for>
        $contents = preg_replace('/<\/for>/i', '<?php endfor; ?>', $contents) ?? $contents;

        // 13. While / Endwhile
        // <while cond="...">
        $contents = preg_replace_callback('/<while\s+cond=[\'"]([^\'"]+)[\'"]\s*>/i', function ($m) {
            $cond = $this->compileDotSyntax($m[1]);
            return '<?php while (' . $cond . '): ?>';
        }, $contents) ?? $contents;

        // </while>
        $contents = preg_replace('/<\/while>/i', '<?php endwhile; ?>', $contents) ?? $contents;

        return $contents;
    }

    private function compileDotSyntax(string $expression): string
    {
        // Replace $user.name or $user.profile.name with $this->get($user, 'name') or $this->get($this->get($user, 'profile'), 'name')
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
