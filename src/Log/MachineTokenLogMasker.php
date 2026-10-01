<?php

namespace Wexample\SymfonyApi\Log;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;
use Wexample\SymfonySecurity\Interface\LogMaskerInterface;
use Wexample\SymfonySecurity\Log\Masker\QueryParameterLogMasker;

/**
 * Replaces any machine token a log record holds by its hint, in every channel:
 * the router logs each request URI, an exception message may quote a header.
 * A bearer value or an `access_token` query parameter that is not one of ours
 * is masked whole. symfony-security's SecretRedactionProcessor walks the
 * records and hands their strings here.
 */
class MachineTokenLogMasker implements LogMaskerInterface
{
    private readonly QueryParameterLogMasker $accessTokenMasker;

    public function __construct(
        #[Autowire(param: 'api_machine_token_prefix')]
        private readonly string $prefix,
    ) {
        $this->accessTokenMasker = new QueryParameterLogMasker(['access_token']);
    }

    public function mask(string $value): string
    {
        $value = preg_replace_callback(
            '/' . preg_quote($this->prefix, '/') . '[0-9A-Za-z]{' . MachineTokenHelper::SECRET_LENGTH . '}/',
            fn (array $match) => MachineTokenHelper::buildHint($match[0], $this->prefix),
            $value
        );

        return $this->accessTokenMasker->mask(preg_replace(
            '/(Bearer\s+)(?!' . preg_quote($this->prefix, '/') . '[0-9A-Za-z]{' . MachineTokenHelper::HINT_SECRET_LENGTH . '}…)[^\s"\',]+/i',
            '$1' . self::REDACTED,
            $value
        ));
    }
}
