<?php

declare(strict_types=1);

namespace Switch\View\Component;

use Switch\View\Security\SecurityHelper;

class ComponentRegistry
{
    /**
     * Custom user-registered components.
     *
     * @var array<string, callable|string>
     */
    private static array $customComponents = [];

    /**
     * Register a custom component renderer.
     */
    public static function register(string $name, callable|string $handler): void
    {
        self::$customComponents[$name] = $handler;
    }

    /**
     * Render a component by tag name with attributes, slot content, and named slots.
     *
     * @param string $name Tag name (e.g. 'card', 'button', 'alert', 'input', 'modal', 'badge', 'spinner', 'skeleton', 'shimmer', 'reactive')
     * @param array<string, mixed> $attributes Attributes passed to the component
     * @param string $slot Default slot inner content
     * @param array<string, string> $slots Named slots (e.g. ['header' => '...', 'footer' => '...'])
     */
    public static function render(string $name, array $attributes = [], string $slot = '', array $slots = []): string
    {
        // 1. Check custom registered component
        if (isset(self::$customComponents[$name])) {
            $handler = self::$customComponents[$name];
            if (is_callable($handler)) {
                return (string) $handler($attributes, $slot, $slots);
            }
        }

        // 2. Built-in futuristic & security UI components
        return match ($name) {
            'card' => self::renderCard($attributes, $slot, $slots),
            'button' => self::renderButton($attributes, $slot),
            'alert' => self::renderAlert($attributes, $slot),
            'input' => self::renderInput($attributes),
            'modal' => self::renderModal($attributes, $slot, $slots),
            'badge' => self::renderBadge($attributes, $slot),
            'avatar' => self::renderAvatar($attributes),
            'spinner' => self::renderSpinner($attributes),
            'skeleton' => self::renderSkeleton($attributes),
            'shimmer' => self::renderShimmer($attributes),
            'reactive' => self::renderReactive($attributes, $slot),
            'json' => self::renderJson($attributes),
            'csrf' => SecurityHelper::csrfField(),
            'honeypot' => SecurityHelper::honeypot(
                (string) ($attributes['name'] ?? 'my_name_hp'),
                (string) ($attributes['time-name'] ?? 'my_time_hp')
            ),
            'nonce' => 'nonce="' . SecurityHelper::getCspNonce() . '"',
            default => self::renderGenericFallback($name, $attributes, $slot, $slots),
        };
    }

    private static function renderCard(array $attr, string $slot, array $slots): string
    {
        $title = $attr['title'] ?? ($slots['title'] ?? null);
        $footer = $attr['footer'] ?? ($slots['footer'] ?? null);
        $class = 'switch-card ' . ($attr['class'] ?? '');
        $style = 'border:1px solid #e2e8f0; border-radius:0.5rem; background:#ffffff; box-shadow:0 1px 3px rgba(0,0,0,0.1); overflow:hidden; margin-bottom:1rem; ' . ($attr['style'] ?? '');

        $html = '<div class="' . htmlspecialchars(trim($class), ENT_QUOTES) . '" style="' . htmlspecialchars($style, ENT_QUOTES) . '">';

        if ($title) {
            $html .= '<div style="padding:1rem 1.25rem; border-bottom:1px solid #f1f5f9; font-weight:600; font-size:1.125rem; background:#f8fafc;">' . $title . '</div>';
        }

        $html .= '<div style="padding:1.25rem;">' . $slot . '</div>';

        if ($footer) {
            $html .= '<div style="padding:0.75rem 1.25rem; border-top:1px solid #f1f5f9; background:#f8fafc; font-size:0.875rem;">' . $footer . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private static function renderButton(array $attr, string $slot): string
    {
        $variant = $attr['variant'] ?? 'primary';
        $type = $attr['type'] ?? 'button';

        $bgColors = [
            'primary' => '#3b82f6',
            'secondary' => '#64748b',
            'success' => '#10b981',
            'danger' => '#ef4444',
            'warning' => '#f59e0b',
            'dark' => '#1e293b',
        ];

        $bg = $bgColors[$variant] ?? '#3b82f6';
        $style = "display:inline-flex; align-items:center; justify-content:center; padding:0.5rem 1rem; border-radius:0.375rem; border:none; background-color:{$bg}; color:#ffffff; font-weight:500; cursor:pointer; font-size:0.875rem; transition:opacity 0.2s; " . ($attr['style'] ?? '');
        $class = 'switch-btn switch-btn-' . htmlspecialchars($variant, ENT_QUOTES) . ' ' . ($attr['class'] ?? '');

        return '<button type="' . htmlspecialchars($type, ENT_QUOTES) . '" class="' . htmlspecialchars(trim($class), ENT_QUOTES) . '" style="' . htmlspecialchars($style, ENT_QUOTES) . '">' . $slot . '</button>';
    }

    private static function renderAlert(array $attr, string $slot): string
    {
        $type = $attr['type'] ?? 'info';
        $dismissible = isset($attr['dismissible']) && $attr['dismissible'] !== 'false';

        $styles = [
            'info' => 'background-color:#eff6ff; color:#1e40af; border:1px solid #bfdbfe;',
            'success' => 'background-color:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;',
            'warning' => 'background-color:#fffbeb; color:#92400e; border:1px solid #fde68a;',
            'danger' => 'background-color:#fef2f2; color:#991b1b; border:1px solid #fecaca;',
        ];

        $style = 'padding:1rem; border-radius:0.375rem; margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between; ' . ($styles[$type] ?? $styles['info']) . ' ' . ($attr['style'] ?? '');

        $html = '<div class="switch-alert switch-alert-' . htmlspecialchars($type, ENT_QUOTES) . '" style="' . htmlspecialchars($style, ENT_QUOTES) . '">';
        $html .= '<div>' . $slot . '</div>';

        if ($dismissible) {
            $html .= '<button onclick="this.parentElement.remove();" style="background:none; border:none; font-size:1.25rem; line-height:1; cursor:pointer; color:inherit;">&times;</button>';
        }

        $html .= '</div>';
        return $html;
    }

    private static function renderInput(array $attr): string
    {
        $name = $attr['name'] ?? '';
        $label = $attr['label'] ?? null;
        $type = $attr['type'] ?? 'text';
        $value = $attr['value'] ?? '';
        $placeholder = $attr['placeholder'] ?? '';
        $error = $attr['error'] ?? null;

        $html = '<div style="margin-bottom:1rem;" class="switch-input-group">';

        if ($label) {
            $html .= '<label style="display:block; margin-bottom:0.375rem; font-weight:500; font-size:0.875rem; color:#374151;">' . htmlspecialchars((string) $label, ENT_QUOTES) . '</label>';
        }

        $borderColor = $error ? '#ef4444' : '#d1d5db';
        $style = "width:100%; padding:0.5rem 0.75rem; border-radius:0.375rem; border:1px solid {$borderColor}; box-sizing:border-box; font-size:0.875rem; " . ($attr['style'] ?? '');

        $html .= '<input type="' . htmlspecialchars($type, ENT_QUOTES) . '" name="' . htmlspecialchars($name, ENT_QUOTES) . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES) . '" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES) . '" style="' . htmlspecialchars($style, ENT_QUOTES) . '">';

        if ($error) {
            $html .= '<div style="color:#ef4444; font-size:0.75rem; margin-top:0.25rem;">' . htmlspecialchars((string) $error, ENT_QUOTES) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private static function renderModal(array $attr, string $slot, array $slots): string
    {
        $id = $attr['id'] ?? ('switch_modal_' . bin2hex(random_bytes(4)));
        $title = $attr['title'] ?? ($slots['title'] ?? 'Modal Title');

        $html = '<div id="' . htmlspecialchars($id, ENT_QUOTES) . '" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">';
        $html .= '<div style="background:#ffffff; border-radius:0.5rem; max-width:500px; width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); overflow:hidden;">';
        $html .= '<div style="padding:1rem 1.25rem; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;">';
        $html .= '<h3 style="margin:0; font-size:1.125rem;">' . htmlspecialchars((string) $title, ENT_QUOTES) . '</h3>';
        $html .= '<button onclick="document.getElementById(\'' . htmlspecialchars($id, ENT_QUOTES) . '\').style.display=\'none\';" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>';
        $html .= '</div>';
        $html .= '<div style="padding:1.25rem;">' . $slot . '</div>';

        if (isset($slots['footer'])) {
            $html .= '<div style="padding:0.75rem 1.25rem; border-top:1px solid #e5e7eb; background:#f9fafb; text-align:right;">' . $slots['footer'] . '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    private static function renderBadge(array $attr, string $slot): string
    {
        $color = $attr['color'] ?? 'primary';
        $colors = [
            'primary' => 'background:#dbeafe; color:#1e40af;',
            'success' => 'background:#d1fae5; color:#065f46;',
            'warning' => 'background:#fef3c7; color:#92400e;',
            'danger' => 'background:#fee2e2; color:#991b1b;',
            'neutral' => 'background:#f3f4f6; color:#374151;',
        ];

        $style = 'display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:600; ' . ($colors[$color] ?? $colors['primary']);
        return '<span style="' . htmlspecialchars($style, ENT_QUOTES) . '">' . $slot . '</span>';
    }

    private static function renderAvatar(array $attr): string
    {
        $src = $attr['src'] ?? '';
        $alt = $attr['alt'] ?? 'Avatar';
        $size = $attr['size'] ?? '40px';

        $style = "width:{$size}; height:{$size}; border-radius:9999px; object-fit:cover; border:2px solid #ffffff; box-shadow:0 1px 2px rgba(0,0,0,0.1);";
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '" style="' . htmlspecialchars($style, ENT_QUOTES) . '">';
    }

    private static function renderSpinner(array $attr): string
    {
        $size = $attr['size'] ?? '24px';
        $color = $attr['color'] ?? '#3b82f6';

        return '<svg style="animation:spin 1s linear infinite; width:' . htmlspecialchars($size, ENT_QUOTES) . '; height:' . htmlspecialchars($size, ENT_QUOTES) . '; color:' . htmlspecialchars($color, ENT_QUOTES) . ';" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">'
            . '<style>@keyframes spin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}</style>'
            . '<circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
            . '<path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>'
            . '</svg>';
    }

    private static function renderSkeleton(array $attr): string
    {
        $type = $attr['type'] ?? 'text';
        $rows = (int) ($attr['rows'] ?? 3);

        $shimmerCss = '<style>@keyframes shimmer{0%{background-position:-200% 0;}100%{background-position:200% 0;}}.switch-shimmer-bg{background:linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%); background-size:200% 100%; animation:shimmer 1.5s infinite;}</style>';

        if ($type === 'card') {
            return $shimmerCss . '<div style="border-radius:0.5rem; padding:1rem; border:1px solid #e2e8f0;">'
                . '<div class="switch-shimmer-bg" style="height:140px; border-radius:0.375rem; margin-bottom:1rem;"></div>'
                . '<div class="switch-shimmer-bg" style="height:20px; width:70%; border-radius:0.25rem; margin-bottom:0.5rem;"></div>'
                . '<div class="switch-shimmer-bg" style="height:16px; width:40%; border-radius:0.25rem;"></div>'
                . '</div>';
        }

        $html = $shimmerCss . '<div>';
        for ($i = 0; $i < $rows; $i++) {
            $width = ($i === $rows - 1) ? '60%' : '100%';
            $html .= '<div class="switch-shimmer-bg" style="height:16px; width:' . $width . '; border-radius:0.25rem; margin-bottom:0.625rem;"></div>';
        }
        $html .= '</div>';
        return $html;
    }

    private static function renderShimmer(array $attr): string
    {
        $width = $attr['width'] ?? '100%';
        $height = $attr['height'] ?? '20px';
        $radius = $attr['radius'] ?? '0.25rem';

        return '<style>@keyframes shimmer{0%{background-position:-200% 0;}100%{background-position:200% 0;}}.switch-shimmer-bg{background:linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%); background-size:200% 100%; animation:shimmer 1.5s infinite;}</style>'
            . '<div class="switch-shimmer-bg" style="width:' . htmlspecialchars($width, ENT_QUOTES) . '; height:' . htmlspecialchars($height, ENT_QUOTES) . '; border-radius:' . htmlspecialchars($radius, ENT_QUOTES) . ';"></div>';
    }

    /**
     * Futuristic PHP-driven Reactive Component micro-state helper.
     * Output a zero-dependency JS client-side state hydration binding.
     */
    private static function renderReactive(array $attr, string $slot): string
    {
        $component = $attr['component'] ?? ('reactive_' . bin2hex(random_bytes(4)));
        $state = isset($attr['state']) && is_array($attr['state']) ? $attr['state'] : [];
        $safeJson = SecurityHelper::safeJson($state);

        return '<div data-switch-reactive="' . htmlspecialchars($component, ENT_QUOTES) . '" data-state=\'' . $safeJson . '\'>'
            . $slot
            . '</div>';
    }

    private static function renderJson(array $attr): string
    {
        $var = $attr['var'] ?? 'window.__SWITCH_DATA__';
        $data = $attr['data'] ?? [];
        $safeJson = SecurityHelper::safeJson($data);

        return '<script ' . SecurityHelper::getCspNonce() . '> ' . $var . ' = ' . $safeJson . '; </script>';
    }

    private static function renderGenericFallback(string $name, array $attr, string $slot, array $slots): string
    {
        $attrStr = '';
        foreach ($attr as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars($k, ENT_QUOTES) . '="' . htmlspecialchars((string) $v, ENT_QUOTES) . '"';
        }

        return "<div class=\"switch-component-{$name}\"{$attrStr}>{$slot}</div>";
    }
}
