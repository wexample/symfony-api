<?php

namespace Wexample\SymfonyApi\Log;

use Monolog\Attribute\AsMonologProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;
use Wexample\SymfonySecurity\Log\AbstractSecretRedactionProcessor;

/**
 * Replaces any machine token a log record holds by its hint, in every channel:
 * the router logs each request URI, an exception message may quote a header.
 * A bearer value or an `access_token` query parameter that is not one of ours
 * is masked whole. The walk of the record, exceptions included, belongs to
 * the parent.
 */
#[AsMonologProcessor]
class MachineTokenRedactionProcessor extends AbstractSecretRedactionProcessor
{
    public function __construct(
        #[Autowire(param: 'api_machine_token_prefix')]
        private readonly string $prefix,
    ) {
    }

    protected function mask(string $value): string
    {
        $value = preg_replace_callback(
            '/' . preg_quote($this->prefix, '/') . '[0-9A-Za-z]{' . MachineTokenHelper::SECRET_LENGTH . '}/',
            fn (array $match) => MachineTokenHelper::buildHint($match[0], $this->prefix),
            $value
        );

        return $this->maskQueryParameters(
            preg_replace(
                '/(Bearer\s+)(?!' . preg_quote($this->prefix, '/') . '[0-9A-Za-z]{' . MachineTokenHelper::HINT_SECRET_LENGTH . '}…)[^\s"\',]+/i',
                '$1' . self::REDACTED,
                $value
            ),
            ['access_token']
        );
    }
}
