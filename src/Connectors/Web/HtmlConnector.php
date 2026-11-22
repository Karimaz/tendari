<?php
declare(strict_types=1);

namespace Tendari\Connectors\Web;

use Tendari\Connectors\AbstractConnector;
use Tendari\Services\Normalizer;

/**
 * Base for HTML scrapers: XPath helpers over fetched pages.
 * Politeness handled by HttpClient (1 req / 2s per host); raw payloads cached.
 */
abstract class HtmlConnector extends AbstractConnector
{
    /** @return \DOMNodeList|null */
    protected function xpath(string $html, string $query): ?\DOMNodeList
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        if (!$ok) {
            return null;
        }
        return (new \DOMXPath($doc))->query($query);
    }

    protected function first(\DOMNodeList $list): string
    {
        $node = $list->item(0);
        return $node !== null ? trim($node->textContent) : '';
    }
}
