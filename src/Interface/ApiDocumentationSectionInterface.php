<?php

namespace Wexample\SymfonyApi\Interface;

/**
 * A section another package adds to the api's page (/api/), after the
 * operations: the MCP server's tools, for instance. Autoconfigured.
 */
interface ApiDocumentationSectionInterface
{
    final public const string TAG = 'wexample_symfony_api.documentation_section';

    /**
     * Whether there is anything to show, for the reader the page has — one
     * reading the preview only included.
     */
    public function isShown(bool $preview): bool;

    /**
     * The template drawing it, given `section` (getContext()).
     */
    public function getTemplate(): string;

    public function getContext(bool $preview): array;
}
