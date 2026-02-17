<?php

declare(strict_types=1);

namespace Botovis\Telegram;

/**
 * Format Botovis responses for Telegram.
 *
 * Handles Markdown → MarkdownV2 conversion and table formatting
 * for Telegram's limited markup support.
 */
class TelegramFormatter
{
    /**
     * Characters that must be escaped in Telegram MarkdownV2.
     * See: https://core.telegram.org/bots/api#markdownv2-style
     */
    private const ESCAPE_CHARS = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

    /**
     * Format a Botovis response message for Telegram.
     *
     * Tries MarkdownV2 first; if escaping fails or content is too complex,
     * falls back to plain text.
     *
     * @return array{text: string, parse_mode: string}
     */
    public static function format(string $message): array
    {
        if (empty(trim($message))) {
            return ['text' => '...', 'parse_mode' => ''];
        }

        // Try to convert markdown tables to monospace
        $message = self::convertTables($message);

        // Try MarkdownV2 formatting
        $formatted = self::toMarkdownV2($message);

        if ($formatted !== null) {
            return ['text' => $formatted, 'parse_mode' => 'MarkdownV2'];
        }

        // Fallback: strip markdown and send as plain text
        return ['text' => self::stripMarkdown($message), 'parse_mode' => ''];
    }

    /**
     * Format a confirmation message with action details.
     */
    public static function formatConfirmation(string $message, ?array $pendingAction): string
    {
        $lines = ['⚠️ *Yazma İşlemi*'];
        $lines[] = '';

        if ($pendingAction) {
            $action = $pendingAction['action'] ?? 'unknown';
            $params = $pendingAction['params'] ?? [];
            $table = $params['table'] ?? '';

            $lines[] = self::escapeMarkdownV2("🔧 {$action}: {$table}");

            // Show key parameters
            foreach ($params as $key => $value) {
                if ($key === 'table') continue;
                $valueStr = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
                $lines[] = self::escapeMarkdownV2("   {$key}: {$valueStr}");
            }

            $lines[] = '';
        }

        if ($message) {
            $lines[] = self::escapeMarkdownV2($message);
        }

        return implode("\n", $lines);
    }

    /**
     * Format a step notification for Telegram.
     */
    public static function formatStep(string $thought, string $action, array $params = []): string
    {
        $parts = [];

        if ($thought) {
            $parts[] = '💭 ' . self::escapeMarkdownV2($thought);
        }

        if ($action) {
            $paramStr = !empty($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : '';
            $parts[] = '🔧 `' . $action . '`';
            if ($paramStr) {
                $parts[] = '```json' . "\n" . $paramStr . "\n" . '```';
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Convert standard Markdown tables to monospace format.
     *
     * Input:
     *   | Name | Price |
     *   |------|-------|
     *   | iPhone | 999 |
     *
     * Output (monospace):
     *   Name    | Price
     *   --------|------
     *   iPhone  | 999
     */
    public static function convertTables(string $text): string
    {
        return preg_replace_callback(
            '/(\|.+\|[\r\n]+\|[-| :]+\|[\r\n]+(?:\|.+\|[\r\n]*)+)/m',
            function ($matches) {
                $table = trim($matches[0]);
                $lines = explode("\n", $table);

                // Parse rows
                $rows = [];
                $isSeparator = [];
                foreach ($lines as $line) {
                    $line = trim($line, '| ');
                    $cells = array_map('trim', explode('|', $line));
                    $rows[] = $cells;
                    $isSeparator[] = (bool) preg_match('/^[-: |]+$/', $line);
                }

                if (count($rows) < 2) {
                    return $matches[0];
                }

                // Calculate column widths
                $colCount = count($rows[0]);
                $widths = array_fill(0, $colCount, 0);
                foreach ($rows as $i => $row) {
                    if ($isSeparator[$i]) continue;
                    foreach ($row as $j => $cell) {
                        if ($j < $colCount) {
                            $widths[$j] = max($widths[$j], mb_strlen($cell));
                        }
                    }
                }

                // Build monospace table
                $result = [];
                foreach ($rows as $i => $row) {
                    if ($isSeparator[$i]) {
                        $result[] = implode('─┼─', array_map(fn($w) => str_repeat('─', $w), $widths));
                        continue;
                    }
                    $cells = [];
                    foreach ($row as $j => $cell) {
                        if ($j < $colCount) {
                            $cells[] = mb_str_pad($cell, $widths[$j]);
                        }
                    }
                    $result[] = implode(' │ ', $cells);
                }

                return "```\n" . implode("\n", $result) . "\n```";
            },
            $text
        ) ?? $text;
    }

    /**
     * Convert standard Markdown to Telegram MarkdownV2.
     *
     * Returns null if conversion fails (caller should use plain text).
     */
    public static function toMarkdownV2(string $text): ?string
    {
        try {
            // Protect code blocks first
            $codeBlocks = [];
            $text = preg_replace_callback('/```[\s\S]*?```/', function ($m) use (&$codeBlocks) {
                $key = '%%CODE_BLOCK_' . count($codeBlocks) . '%%';
                $codeBlocks[$key] = $m[0];
                return $key;
            }, $text);

            // Protect inline code
            $inlineCodes = [];
            $text = preg_replace_callback('/`[^`]+`/', function ($m) use (&$inlineCodes) {
                $key = '%%INLINE_CODE_' . count($inlineCodes) . '%%';
                $inlineCodes[$key] = $m[0];
                return $key;
            }, $text);

            // Convert bold: **text** → *text*
            $text = preg_replace('/\*\*(.+?)\*\*/', '*$1*', $text);

            // Escape special characters in remaining text (outside protected blocks)
            $text = self::escapeMarkdownV2($text);

            // Restore protected blocks (unescape the %% markers)
            $text = str_replace(
                array_map(fn($k) => self::escapeMarkdownV2($k), array_keys($codeBlocks)),
                array_values($codeBlocks),
                $text
            );
            $text = str_replace(
                array_map(fn($k) => self::escapeMarkdownV2($k), array_keys($inlineCodes)),
                array_values($inlineCodes),
                $text
            );

            // Also try direct replacement (in case escaping didn't change placeholder)
            foreach ($codeBlocks as $key => $value) {
                $text = str_replace($key, $value, $text);
            }
            foreach ($inlineCodes as $key => $value) {
                $text = str_replace($key, $value, $text);
            }

            return $text;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Escape special characters for MarkdownV2.
     */
    public static function escapeMarkdownV2(string $text): string
    {
        foreach (self::ESCAPE_CHARS as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }

    /**
     * Strip all Markdown formatting for plain text output.
     */
    public static function stripMarkdown(string $text): string
    {
        // Remove code blocks
        $text = preg_replace('/```[\s\S]*?```/', '', $text);
        // Remove inline code
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        // Remove bold/italic
        $text = preg_replace('/\*{1,2}(.+?)\*{1,2}/', '$1', $text);
        // Remove headers
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);
        // Remove table pipes
        $text = preg_replace('/\|/m', ' ', $text);
        // Remove horizontal rules
        $text = preg_replace('/^[-=]{3,}$/m', '', $text);
        // Clean up extra whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
