<?php

namespace Bx\IblockCopy;

/**
 * Structured logger for copy operations.
 */
final class Logger
{
    private const MODULE_ID = 'bx.iblockcopy';

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
        \AddMessage2Log('[bx.iblockcopy] ' . $message . self::formatContext($context), self::MODULE_ID);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            '[%s] %s %s%s',
            date('Y-m-d H:i:s'),
            $level,
            $message,
            self::formatContext($context)
        );

        if (defined('LOG_FILENAME') && is_string(LOG_FILENAME) && LOG_FILENAME !== '') {
            @file_put_contents(LOG_FILENAME, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        unset($context['password'], $context['token'], $context['secret']);

        return ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
