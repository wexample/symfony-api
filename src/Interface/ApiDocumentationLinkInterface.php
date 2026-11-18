<?php

namespace Wexample\SymfonyApi\Interface;

/**
 * A page beside the api's (/api/) that another package adds — the MCP
 * server's, for instance —, reached from the api page's toolbar.
 * Autoconfigured.
 */
interface ApiDocumentationLinkInterface
{
    final public const string TAG = 'wexample_symfony_api.documentation_link';

    /**
     * The button leading to the page, for the reader the api page has: null
     * when that reader may not open it — one reading the preview only.
     *
     * @return array{icon: string, label: string, href: string}|null
     */
    public function getLink(bool $preview): ?array;
}
