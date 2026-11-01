<?php

declare(strict_types=1);

/*
 * Web Content Access for Contao, WS SOLUTIONS LLC, https://wcag.tech
 * Released under the GNU Lesser General Public License, version 3 or later.
 *
 * Adds the Web Content Access script before </body> of every frontend page whose website root has it enabled:
 * - accessibility button ("Anzeige anpassen"), loaded after the page is idle
 * - small, deterministic accessibility repairs (page language, zoom, skip link, focus, names, labels, tab order, motion)
 * - a footer link to the site's accessibility statement
 * The scripts are served from https://wcag.tech/static/, so fixes and new languages reach every site without a
 * bundle update. Visitor settings stay in the visitor's browser; no tracking, no cookies.
 * Settings live on the website root page (Site structure > root page > "Web Content Access").
 */

namespace WSSolutions\ContaoWebContentAccess\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ResponseContext\Csp\CspHandlerFactory;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener(priority: -64)]
class ScriptTagListener
{
    public const ORIGIN = 'https://wcag.tech';
    public const SCRIPT_URL = self::ORIGIN . '/static/bfsg-embed.js';
    public const VERSION = '1.0.0';

    /** checkbox value in tl_page.wcaDisabledFixes => flag name the script understands */
    public const FIXES = ['lang', 'zoom', 'skip', 'focus', 'names', 'labels', 'tabindex', 'motion', 'statement'];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly ContentUrlGenerator $urlGenerator,
        #[Autowire(service: 'contao.routing.response_context.csp_handler_factory')]
        private readonly CspHandlerFactory $cspHandlerFactory,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$this->scopeMatcher->isFrontendMainRequest($event)) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        if (!\is_string($content) || !str_contains((string)$response->headers->get('Content-Type'), 'text/html')
            || str_contains($content, self::SCRIPT_URL) || ($pos = strripos($content, '</body>')) === false) {
            return;
        }

        try {
            $page = $event->getRequest()->attributes->get('pageModel');
            if (!$page instanceof PageModel) {
                return;
            }
            $page->loadDetails();
            $root = $this->framework->getAdapter(PageModel::class)->findByPk($page->rootId);
            if (!$root instanceof PageModel || !$root->wcaEnabled) {
                return;
            }
            $tag = $this->scriptTag($root->row(), $this->locale((string)$page->language), $this->statementUrl($root));
        } catch (\Throwable) {
            return; // never break the page because of this bundle
        }

        $response->setContent(substr($content, 0, $pos) . $tag . "\n" . substr($content, $pos));
        $this->allowInCsp($event);
    }

    /** Sites with Contao's CSP switched on: allow the wcag.tech scripts and the widget's styles, as the preview toolbar does. */
    private function allowInCsp(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $reportOnly = $response->headers->has('Content-Security-Policy-Report-Only');
        $header = $reportOnly ? $response->headers->get('Content-Security-Policy-Report-Only') : $response->headers->get('Content-Security-Policy');
        if (!$header) {
            return;
        }
        try {
            $csp = $this->cspHandlerFactory->create($header);
            $csp->getDirectives()->setLevel1Fallback(false);
            $csp->setReportOnly($reportOnly);
            $csp->addSource('script-src', self::ORIGIN);
            $csp->addSource('style-src', "'unsafe-inline'");
            $csp->applyHeaders($response, $event->getRequest());
        } catch (\Throwable) {
            // leave the site's policy untouched
        }
    }

    /** @param array<string, mixed> $row tl_page row of the website root */
    public function scriptTag(array $row, string $locale, string $statementUrl): string
    {
        $disabled = array_map('strval', (array)StringUtil::deserialize($row['wcaDisabledFixes'] ?? '', true));

        $cfg = [];
        foreach (self::FIXES as $flag) {
            $cfg[$flag] = !\in_array($flag, $disabled, true);
        }
        $cfg['toolbar'] = (bool)($row['wcaToolbar'] ?? true);

        if ($statementUrl !== '') {
            $cfg['statementUrl'] = $statementUrl;
        } else {
            $cfg['statement'] = false; // nothing to link to yet
        }

        $cfg['widget'] = [
            'position' => self::choice($row['wcaPosition'] ?? null,
                ['bottom-right', 'bottom-left', 'middle-right', 'middle-left', 'top-right', 'top-left'], 'bottom-right'),
            'color' => self::color($row['wcaColor'] ?? null, '#1a56db'),
            'size' => self::choice($row['wcaSize'] ?? null, ['s', 'm', 'l'], 'm'),
            'shape' => self::choice($row['wcaShape'] ?? null, ['round', 'square'], 'round'),
            'lang' => 'auto',
        ];
        $cfg['locale'] = $locale;

        $json = json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return \sprintf('<script src="%s?v=%s&amp;p=contao" data-bfsg="%s" data-locale="%s" defer></script>',
            self::SCRIPT_URL,
            self::VERSION,
            htmlspecialchars($json, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'));
    }

    /** Statement page (page picker) wins over the URL field; anything else than https://… or /path is ignored. */
    private function statementUrl(PageModel $root): string
    {
        if ((int)$root->wcaStatementPage > 0) {
            $target = $this->framework->getAdapter(PageModel::class)->findPublishedById((int)$root->wcaStatementPage);
            if ($target instanceof PageModel) {
                try {
                    return $this->urlGenerator->generate($target);
                } catch (\Throwable) {
                    // fall through to the URL field
                }
            }
        }
        $url = trim((string)$root->wcaStatementUrl);

        return preg_match('~^(https?://|/)~i', $url) ? $url : '';
    }

    /** Language of the current page, e.g. "de" or "en" (Contao 5 stores "de" or "de-CH"). */
    private function locale(string $language): string
    {
        $code = strtolower(substr(str_replace('_', '-', $language), 0, 2));

        return preg_match('/^[a-z]{2}$/', $code) ? $code : 'de';
    }

    private static function choice(mixed $value, array $allowed, string $default): string
    {
        return \in_array((string)$value, $allowed, true) ? (string)$value : $default;
    }

    private static function color(mixed $value, string $default): string
    {
        $value = (string)$value;
        if ($value !== '' && $value[0] !== '#') {
            $value = '#' . $value; // Contao's colour picker stores "1a56db"
        }

        return preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $value) ? $value : $default;
    }
}
