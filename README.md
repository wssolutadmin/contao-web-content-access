# Web Content Access for Contao

Accessibility for Contao sites (BFSG / European Accessibility Act), by WS SOLUTIONS LLC, https://wcag.tech.
Same product and script as the Shopify app, the Gambio module and the TYPO3 extension: one script tag before
`</body>` loads `https://wcag.tech/static/bfsg-embed.js`, so fixes and new languages reach every site without a
bundle update.

- **Accessibility button**: visitors adjust text size, contrast, spacing, reading mode and animations, in 33 languages.
  Position, colour, size and shape are configurable.
- **9 automatic repairs**, each switchable: page language, zoom, skip link, visible focus, names for icons and
  buttons, labelled form fields, tab order, reduced motion, footer link to the statement.
- **Accessibility statement**: pick the page (or enter a URL); the button and the footer link to it.
- **Privacy**: no cookies, no tracking, no visitor data. Visitor settings stay in their browser.
- **CSP**: if the website root has Contao's Content-Security-Policy switched on, the bundle adds `https://wcag.tech`
  to `script-src` and `'unsafe-inline'` to `style-src` (the widget's styles), the same way Contao's preview toolbar
  extends the policy.

No bundle makes a site fully accessible on its own. Colour contrast in the theme, headings, link texts and image
descriptions remain the editor's job. Implementation help, not legal advice.

## Requirements
Contao 5.3 LTS, 5.7 LTS or 6.0, PHP 8.1+.

## Installation
Contao Manager: search "Web Content Access", install, run the database update. Or:
```bash
composer require wssolutions/contao-web-content-access
vendor/bin/contao-console contao:migrate
```
Then **Site structure > website root page > edit > "Web Content Access (Barrierefreiheit)"**. The button is active on
every root page right after installation; untick "Active on this website" to switch it off for one site. Pick the
statement page, adjust the button, untick repairs you do not want. Settings are per website root (multi-domain safe).

## How it works
- `contao/dca/tl_page.php`: fields on root pages (`wcaEnabled`, `wcaStatementPage`, `wcaStatementUrl`, `wcaToolbar`,
  `wcaPosition`, `wcaColor`, `wcaSize`, `wcaShape`, `wcaDisabledFixes`); labels in `contao/languages/{de,en}`.
- `src/EventListener/ScriptTagListener.php`: `kernel.response` listener for frontend main requests. Reads the root
  page of the current page, builds the `data-bfsg` JSON and inserts the tag before `</body>`. Invalid values fall back
  to defaults; any error leaves the page untouched.

## Tested
Contao 6.0 and 5.3 LTS (PHP 8.5, MariaDB): defaults, custom button, statement page and URL, repairs switched off,
button hidden, disabled (no tag), invalid values (defaults), subpages, 404 pages (no tag), saving the form in the
backend, strict CSP (`default-src 'self'`): button loads without console errors.

## Support
support@wcag.tech · https://wcag.tech/integrationen

## License
LGPL-3.0-or-later. © WS SOLUTIONS LLC.
