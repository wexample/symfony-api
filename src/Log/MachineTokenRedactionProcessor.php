<?php

namespace Wexample\SymfonyApi\Log;

use Error;
use Exception;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;

/**
 * Replaces any machine token a log record holds by its hint, in every channel:
 * the router logs each request URI, an exception message may quote a header.
 * A bearer value or an `access_token` query parameter that is not one of ours
 * is masked whole.
 *
 * An exception carried by a record is rewritten in place: the formatter reads
 * its message after this processor, and a 404 quotes the referer, query
 * string included.
 */
#[AsMonologProcessor]
class MachineTokenRedactionProcessor
{
    public const string REDACTED = '[redacted]';

    public function __construct(
        #[Autowire(param: 'api_machine_token_prefix')]
        private readonly string $prefix,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redact($record->message),
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    private function redact(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = preg_replace_callback(
                '/' . preg_quote($this->prefix, '/') . '[0-9A-Za-z]{' . MachineTokenHelper::SECRET_LENGTH . '}/',
                fn (array $match) => MachineTokenHelper::buildHint($match[0], $this->prefix),
                $value
            );

            return preg_replace(
                [
                    '/(Bearer\s+)(?!' . preg_quote($this->prefix, '/') . '[0-9A-Za-z]{' . MachineTokenHelper::HINT_SECRET_LENGTH . '}…)[^\s"\',]+/i',
                    '/([?&]access_token=)[^&\s"\'#]+/',
                ],
                '$1' . self::REDACTED,
                $value
            );
        }

        if (is_array($value)) {
            return array_map($this->redact(...), $value);
        }

        if ($value instanceof Throwable) {
            for ($exception = $value; null !== $exception; $exception = $exception->getPrevious()) {
                $message = $this->redact($exception->getMessage());

                if ($message !== $exception->getMessage()) {
                    $property = new ReflectionProperty($exception instanceof Exception ? Exception::class : Error::class, 'message');
                    $property->setValue($exception, $message);
                }
            }
        }

        return $value;
    }
}
