<?php

namespace Wexample\SymfonyApi\Log;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;
use Wexample\SymfonySecurity\Interface\LogMaskerInterface;
use Wexample\SymfonySecurity\Log\Masker\QueryParameterLogMasker;

/**
 * Replaces any API token a log record holds — a machine's or a user's — by its
 * hint, in every channel: the router logs each request URI, an exception
 * message may quote a header. A bearer value or an `access_token` query
 * parameter that is not one of ours is masked whole. symfony-security's
 * SecretRedactionProcessor walks the records and hands their strings here.
 */
class ApiTokenLogMasker implements LogMaskerInterface
{
    private readonly QueryParameterLogMasker $accessTokenMasker;

    /**
     * @var list<string>
     */
    private readonly array $prefixes;

    public function __construct(
        #[Autowire(param: 'api_machine_token_prefix')]
        string $machinePrefix,
        #[Autowire(param: 'api_user_token_prefix')]
        string $userPrefix,
    ) {
        $this->accessTokenMasker = new QueryParameterLogMasker(['access_token']);
        $this->prefixes = array_values(array_unique([$machinePrefix, $userPrefix]));
    }

    public function mask(string $value): string
    {
        $hints = [];

        foreach ($this->prefixes as $prefix) {
            $value = preg_replace_callback(
                '/' . preg_quote($prefix, '/') . '[0-9A-Za-z]{' . MachineTokenHelper::SECRET_LENGTH . '}/',
                fn (array $match) => MachineTokenHelper::buildHint($match[0], $prefix),
                $value
            );
            $hints[] = preg_quote($prefix, '/') . '[0-9A-Za-z]{' . MachineTokenHelper::HINT_SECRET_LENGTH . '}…';
        }

        return $this->accessTokenMasker->mask(preg_replace(
            '/(Bearer\s+)(?!' . implode('|', $hints) . ')[^\s"\',]+/i',
            '$1' . self::REDACTED,
            $value
        ));
    }
}
