<?php

namespace Wexample\SymfonyApi\Helper;

class DebugHelper
{
    /**
     * Debug variable with simple text formatting
     */
    public static function dump(mixed $var): void
    {
        $output = \Wexample\SymfonyHelpers\Helper\DebugHelper::formatVar($var);
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $location = $trace['file'] . ':' . $trace['line'];

        echo "\n=== DEBUG (" . $location . ") ===\n";
        echo $output . "\n";
        echo "==============================\n";
    }

    /**
     * Debug variable and die
     */
    public static function dumpAndDie(mixed $var): never
    {
        static::dump($var);
        die(1);
    }

    /**
     * Debug variable and die
     */
    public static function dd(mixed $var): never
    {
        static::dumpAndDie($var);
    }

    public static function trace(bool $ignoreArgs = true): void
    {
        static::dump(debug_backtrace($ignoreArgs ? DEBUG_BACKTRACE_IGNORE_ARGS : null));
    }
}
