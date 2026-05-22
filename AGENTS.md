# Agent Instructions: Meditrendy Core Plugin

This repository contains the Meditrendy custom plugin. Treat it as the business logic and reusable WooCommerce behavior layer of the site.

## Responsibility

- Put custom WooCommerce behavior, filters, subcategory shortcodes, waitlist logic, AJAX product loading, admin settings, and reusable site features here.
- Do not put theme-only visual changes here, limit the feature markup/CSS owned by the plugin to minimum.
- Do not edit WordPress core, WooCommerce core, the Pro parent theme, or third-party plugin files unless the user explicitly asks.

## Project Conventions

- Frontend/customer-facing labels should stay Lithuanian by default.
- Admin/editor-facing labels may be English or Polish.
- Keep business logic independent from Cornerstone wherever possible.
- Avoid adding third-party dependencies unless the user explicitly approves them.
- Prefer WooCommerce APIs and WordPress APIs over direct SQL, except where direct SQL is already part of the local feature and is carefully scoped.
- Keep changes focused and avoid unrelated refactors.
- Always consider the user experience when making changes.
- Always consider website performance when making changes.

## File Layout

- Plugin bootstrap: `meditrendy-core.php`
- PHP feature modules: `includes/`
- JavaScript assets: `assets/js/`
- CSS assets: `assets/css/`

Use existing feature files when possible:

- Product filters: `includes/product-filters.php`, `includes/filter-settings.php`, `assets/js/product-filters.js`, `assets/css/product-filters.css`
- Subcategories shortcode: `includes/product-subcategories.php`, `assets/css/product-subcategories.css`
- Product waitlist: `includes/product-waitlist.php`, `assets/js/product-waitlist.js`, `assets/css/product-waitlist.css`

## JavaScript And CSS

- Use `const` and `let` in new JavaScript.
- Keep frontend JavaScript scoped to plugin-owned markup.
- Do not depend on generated Cornerstone classes when a plugin-owned class can be used.
- Prefer CSS variables over custom properties.
- Prefer CSS over JavaScript for styling.
- When writing JavaScript, use ES6 syntax and modern browser features.
- When writing JavaScript, avoid jQuery and other third-party libraries.
- When writing JavaScript take performance into account.

## Data And Migrations

- Be careful with database table changes. Use WordPress/WooCommerce migration patterns already present in the plugin.
- Preserve existing user data.
- If a feature relies on another existing plugin for source data, degrade cleanly when that plugin is inactive.

## Testing

- For changed PHP files, run:
  `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l path/to/file.php`
- For AJAX and WooCommerce behavior, verify both the request payload and the rendered frontend behavior when practical.
- Check both desktop and mobile where the feature is visible in both contexts.

## Boundaries

- If the request is about product data, filtering logic, waitlist emails, product availability, shortcode output, or plugin settings, it probably belongs here.
- If the request is only about header layout, mobile navigation presentation, cart/checkout styling, or theme shell visuals, it probably belongs in `pro-child`.
