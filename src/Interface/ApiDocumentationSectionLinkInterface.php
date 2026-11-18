<?php

namespace Wexample\SymfonyApi\Interface;

/**
 * A section of the api's page reached from its toolbar as well: a button
 * leading to it, beside the one trying the calls.
 */
interface ApiDocumentationSectionLinkInterface extends ApiDocumentationSectionInterface
{
    /**
     * The section's anchor on the page — the id its template gives it.
     */
    public function getAnchor(): string;

    /**
     * The button's icon and words: `{ icon, label }`, the label translated.
     *
     * @return array{icon: string, label: string}
     */
    public function getLink(): array;
}
