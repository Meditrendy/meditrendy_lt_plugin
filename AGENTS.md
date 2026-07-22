# Agent Instructions: Meditrendy Core Plugin

This repository contains the Meditrendy custom plugin. Treat it as the business logic and reusable WooCommerce behavior layer of the site.

## Responsibility

- Put all changes intended to work across Meditrendy sites here, including reusable WooCommerce behavior, checkout/cart UI, filters, subcategory shortcodes, waitlist logic, AJAX product loading, admin settings, and shared site features.
- Plugin features must ship the core CSS needed for their markup to work correctly across sites: layout, positioning, visibility states, responsive structure, loading/disabled states, accessibility states, and basic component integrity.
- Do not put website-specific visual changes here. Brand-specific styling such as colors, typography refinements, decorative spacing, and site-specific polish belongs in the active child theme unless it is necessary for a reusable plugin feature to function.
- Do not edit WordPress core, WooCommerce core, the Pro parent theme, or third-party plugin files unless the user explicitly asks.
- If a requested change can be made cleanly in Cornerstone, Pro, WordPress admin, WooCommerce settings, or another editor UI, prefer giving the user exact editor instructions instead of changing code.
- Use plugin code for editor-controlled areas only when the editor cannot reasonably express the behavior, the change must be reusable/systematic, or the user explicitly asks for a code-level implementation.

## Project Conventions

- Storefront/customer-facing strings must be translation-ready for Loco Translate or a similar WordPress translation plugin. Wrap PHP strings with the appropriate WordPress i18n helper (`__()`, `_e()`, `esc_html__()`, `esc_attr__()`, etc.) and use the plugin text domain. For JavaScript-facing strings, localize them from PHP or use the established WordPress i18n flow rather than hardcoding untranslatable text.
- Prefer storefront labels and links to support Lithuanian, Polish, and English. Lithuanian remains the default storefront language unless the active multilingual/translation setup supplies Polish or English, but new frontend URLs, link text, slugs, and navigation-facing strings should be designed so Lithuanian, Polish, and English translations can be managed in Loco Translate or the active translation plugin.
- Admin/editor-facing labels may be English or Polish.
- Keep business logic independent from Cornerstone wherever possible.
- Before adding plugin logic for a Cornerstone/Pro element, check whether the same result can be achieved by adding a class, changing element settings, or editing content in the editor.
- Avoid adding third-party dependencies unless the user explicitly approves them.
- Prefer shortcode-based customization for Cornerstone output over JavaScript DOM rewriting when the behavior can be expressed server-side.
- Prefer WooCommerce APIs and WordPress APIs over direct SQL, except where direct SQL is already part of the local feature and is carefully scoped.
- Keep changes focused and avoid unrelated refactors.
- Always consider the user experience when making changes.
- Always consider website performance when making changes.
- Changes that apply to products usually apply to product sets as well.
- Do not introduce hardcoded frontend strings in any language. Use translatable strings with Lithuanian source text by default, unless the user explicitly asks for a different source language or the existing feature already uses another approved source language.
- For user-facing strings, keep Lithuanian wording consistent with WooCommerce context and make Polish and English translations possible through the translation workflow.
- Always prepare storefront text and links for Lithuanian, Polish, and English translations.
- For translation-only changes to WordPress, WooCommerce, theme, or plugin strings, prefer advising the user to change the string in Loco Translate (or the active translation plugin) instead of adding translation overrides in code. Add code-level translation filters only when the string cannot be translated reliably through the admin translation workflow, when the behavior must be dynamic, or when the user explicitly asks for a code fix.

## File Layout

- Plugin bootstrap: `meditrendy-core.php`
- PHP feature modules: `includes/`
- JavaScript assets: `assets/js/`
- CSS assets: `assets/css/`

Use existing feature files when possible:

- Product filters: `includes/product-filters.php`, `includes/filter-settings.php`, `assets/js/product-filters.js`, `assets/css/product-filters.css`
- Subcategories shortcode: `includes/product-subcategories.php`; shared component styling lives with the plugin, while website-specific polish lives in the child theme.
- Product waitlist: `includes/product-waitlist.php`, `assets/js/product-waitlist.js`, `assets/css/product-waitlist.css`

## JavaScript And CSS

- Use `const` and `let` in new JavaScript.
- Keep frontend JavaScript scoped to plugin-owned markup.
- Use JavaScript for Cornerstone customizations only when a shortcode, template setting, or CSS cannot reasonably solve the problem.
- Do not depend on generated Cornerstone classes when a plugin-owned class can be used.
- Prefer CSS custom properties for reusable values.
- Prefer CSS over JavaScript for styling.
- Keep plugin CSS self-contained for plugin-owned components so installing `meditrendy-core` on another site does not require copying theme CSS just to make a feature usable.
- Use theme CSS only for site-specific visual treatment layered over a working plugin baseline.
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

- If a change is intended to apply across sites, it belongs here, including its required PHP, JavaScript, CSS, responsive behavior, and translation-ready strings.
- If a change is specific to one website's theme, branding, layout, or content, it belongs in that website's child theme.
