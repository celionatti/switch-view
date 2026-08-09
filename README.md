# Switch View (`switch/view`)

> An expressive HTML tag-based template view engine supporting dot-notation property resolution, layout inheritance, and template caching.

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

## 📝 Syntax Guide

### 1. Variables & Dot-Notation Resolution

Switch View supports bracket, arrow, and dot-notation property resolution seamlessly for both arrays and objects:

```html
{{ $title }}

<!-- Dot-notation works for arrays ($user['name']) and objects ($user->name)! -->
<p>Welcome, {{ $user.name }} (Role: {{ $user.role }})</p>
```

### 2. Conditionals (`<if>`, `<unless>`)

```html
<if cond="$user.role == 'Admin'">
    <span class="badge">Administrator</span>
<elseif cond="$user.role == 'Editor'" />
    <span class="badge">Editor</span>
<else />
    <span class="badge">User</span>
</if>

<unless cond="$user.is_active">
    <p>Please activate your account.</p>
</unless>
```

### 3. Loops (`<foreach>`, `<for>`, `<while>`)

```html
<ul>
<foreach items="$products" as="$product">
    <li>{{ $product.name }} - ${{ $product.price }}</li>
</foreach>
</ul>
```

### 4. Layouts & Sections

`layouts/app.php`:
```html
<!DOCTYPE html>
<html>
<head><title>{{ $title }}</title></head>
<body>
    <yield name="content" />
</body>
</html>
```

`home.php`:
```html
<layout name="layouts.app" />

<section name="content">
    <h1>Welcome Home!</h1>
</section>
```

---

## 📄 License
MIT License.
