# Switch View (`switch/view`)

> An expressive HTML tag-based template view engine with built-in UI components (`<x-card>`, `<x-button>`, `<x-alert>`, `<x-modal>`, `<x-skeleton>`, `<x-reactive>`), security-by-default (XSS sanitizer, CSRF token, Honeypot bot defense, CSP Nonces), dot-notation property resolution, layout inheritance, and template caching.

---

## 📦 Installation

```bash
composer require switch/view
```

---

## 🚀 Quick Start

```php
use Switch\View\Engine\ViewEngine;
use Switch\View\View;

$engine = new ViewEngine(
    viewsPath: __DIR__ . '/views',
    cachePath: __DIR__ . '/storage/views'
);

View::setEngine($engine);

echo View::render('home', [
    'title' => 'Dashboard',
    'user' => ['name' => 'Alice', 'role' => 'Admin']
]);
```

---

## 🎨 HTML Tag Components

Switch View provides expressive Blade-style HTML Tag Components (`<x-component-name>`) out of the box:

### Built-in UI Components

#### `<x-card>`
```html
<x-card title="User Statistics">
    <p>Total Sales: $12,450</p>
    <x-slot name="footer">
        Updated 5 mins ago
    </x-slot>
</x-card>
```

#### `<x-button>`
```html
<x-button variant="primary" type="submit">Save Changes</x-button>
<x-button variant="danger">Delete</x-button>
```

#### `<x-alert>`
```html
<x-alert type="warning" dismissible>
    Your subscription expires in 3 days.
</x-alert>
```

#### `<x-input>`
```html
<x-input name="email" label="Email Address" type="email" value="user@example.com" error="Invalid email address" />
```

#### `<x-modal>`
```html
<x-modal id="deleteModal" title="Confirm Delete">
    Are you sure you want to delete this item?
    <x-slot name="footer">
        <x-button variant="secondary">Cancel</x-button>
        <x-button variant="danger">Delete</x-button>
    </x-slot>
</x-modal>
```

#### `<x-badge>`, `<x-avatar>`, `<x-spinner>`
```html
<x-badge color="success">Active</x-badge>
<x-avatar src="/img/user.jpg" alt="Alice" size="40px" />
<x-spinner size="24px" color="#3b82f6" />
```

#### Shimmer & Skeleton Loaders
```html
<!-- Card Skeleton -->
<x-skeleton type="card" />

<!-- Text Skeleton -->
<x-skeleton type="text" rows="4" />

<!-- Custom Shimmer Placeholder -->
<x-shimmer width="100%" height="40px" radius="0.5rem" />
```

#### Futuristic PHP-Driven Reactive Component (`<x-reactive>`)
Automatically hydratable micro-state binding:
```html
<x-reactive component="counter" :state="['count' => 0]">
    <button onclick="this.closest('[data-state]').dataset.state...">Increment</button>
</x-reactive>
```

#### Safe JSON Script Embedding (`<x-json>`)
Prevents HTML/Script tag breakout attacks when embedding server data in JS:
```html
<x-json var="window.__APP_DATA__" :data="$userProfile" />
```

---

## 🔒 Security by Default

Switch View is designed to be secure out of the box against XSS, CSRF, and bot attacks.

### 1. Auto-Escaping Interpolation (`{{ $expr }}`)
All standard interpolations are XSS-escaped by default using `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

Use `{!! $expr !!}` only when explicitly outputting trusted raw HTML.

### 2. CSRF Token Defense
```html
<!-- Tag Directive -->
@csrf

<!-- Component Tag -->
<x-csrf />

<!-- Generated Output -->
<input type="hidden" name="_token" value="4f8a9b...">
```

### 3. Honeypot Spam & Bot Protection
Renders invisible inputs that trap automated spam bots without frustrating human users:
```html
<!-- Directive -->
@honeypot

<!-- Component Tag -->
<x-honeypot name="website_url_hp" />
```

### 4. Content Security Policy (CSP) Nonce
```html
<script @nonce>
    console.log("Inline script with CSP Nonce!");
</script>
```

### 5. HTML XSS Sanitizer (`SecurityHelper::cleanHtml()`)
Strips dangerous tags (`<script>`, `<iframe>`, `<object>`), inline `on*` event attributes (`onload=`, `onclick=`), and `javascript:` URIs.

```php
use Switch\View\Security\SecurityHelper;

$safeHtml = SecurityHelper::cleanHtml($userSubmittedHtml);
```

---

## 🧩 Custom Component Registration

Register custom HTML tag components easily:

```php
use Switch\View\View;

View::component('custom-card', function (array $attr, string $slot, array $slots) {
    return '<div class="my-card"><h3>' . ($attr['title'] ?? '') . '</h3>' . $slot . '</div>';
});
```

Usage in template:
```html
<x-custom-card title="Welcome">
    This is my custom component body.
</x-custom-card>
```

---

## 📄 Partials & Layouts

```html
<!-- Partial Tag -->
<partial name="partials.header" />

<!-- Layout Extension -->
<layout name="layouts.app" />

<section name="content">
    <h1>Page Content</h1>
</section>
```

---

## 📄 License
MIT License.
