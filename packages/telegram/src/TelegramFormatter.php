<?php

declare(strict_types=1);

namespace Botovis\Telegram;

/**
 * Format Botovis responses for Telegram using HTML parse mode.
 *
 * HTML is far more reliable than MarkdownV2 for Telegram —
 * no insane escaping rules, predictable rendering.
 *
 * Supported HTML tags:
 *   <b>, <i>, <u>, <s>, <code>, <pre>, <a href="">
 *   https://core.telegram.org/bots/api#html-style
 */
class TelegramFormatter
{
    /**
     * Format a Botovis response message for Telegram HTML.
     *
     * @return array{text: string, parse_mode: string}
     */
    public static function format(string $message): array
    {
        if (empty(trim($message))) {
            return ['text' => '...', 'parse_mode' => ''];
        }

        $html = self::markdownToHtml($message);

        return ['text' => $html, 'parse_mode' => 'HTML'];
    }

    /**
     * Format a confirmation message with action details.
     */
    public static function formatConfirmation(string $message, ?array $pendingAction): string
    {
        $lines = ['⚠️ <b>Yazma İşlemi</b>'];
        $lines[] = '';

        if ($pendingAction) {
            $action = $pendingAction['action'] ?? 'unknown';
            $params = $pendingAction['params'] ?? [];
            $table = $params['table'] ?? '';

            $lines[] = '🔧 <code>' . self::escapeHtml($action) . '</code>: <b>' . self::escapeHtml($table) . '</b>';

            foreach ($params as $key => $value) {
                if ($key === 'table') continue;
                $valueStr = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    : (string) $value;
                $lines[] = '   <i>' . self::escapeHtml($key) . '</i>: <code>' . self::escapeHtml($valueStr) . '</code>';
            }

            $lines[] = '';
        }

        if ($message) {
            $lines[] = self::escapeHtml($message);
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
            $parts[] = '💭 <i>' . self::escapeHtml($thought) . '</i>';
        }

        if ($action) {
            $parts[] = '🔧 <code>' . self::escapeHtml($action) . '</code>';
            if (!empty($params)) {
                $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $parts[] = '<pre>' . self::escapeHtml($json) . '</pre>';
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Convert standard Markdown to Telegram-supported HTML.
     */
    public static function markdownToHtml(string $text): string
    {
        // ── 1. Protect code blocks ──
        $codeBlocks = [];
        $text = preg_replace_callback('/```(\w*)\n?([\s\S]*?)```/', function ($m) use (&$codeBlocks) {
            $lang = $m[1] ?: '';
            $code = rtrim($m[2]);
            $key = "\x00CB" . count($codeBlocks) . "\x00";
            $langAttr = $lang ? ' class="language-' . self::escapeHtml($lang) . '"' : '';
            $codeBlocks[$key] = '<pre' . $langAttr . '>' . self::escapeHtml($code) . '</pre>';
            return $key;
        }, $text) ?? $text;

        // ── 2. Protect inline code ──
        $inlineCodes = [];
        $text = preg_replace_callback('/`([^`\n]+)`/', function ($m) use (&$inlineCodes) {
            $key = "\x00IC" . count($inlineCodes) . "\x00";
            $inlineCodes[$key] = '<code>' . self::escapeHtml($m[1]) . '</code>';
            return $key;
        }, $text) ?? $text;

        // ── 3. Escape HTML in remaining text ──
        $text = self::escapeHtml($text);

        // ── 4. Restore protected blocks ──
        foreach ($codeBlocks as $key => $value) {
            $text = str_replace(self::escapeHtml($key), $value, $text);
        }
        foreach ($inlineCodes as $key => $value) {
            $text = str_replace(self::escapeHtml($key), $value, $text);
        }

        // ── 5. Convert Markdown tables → monospace ──
        $text = self::convertTables($text);

        // ── 6. Bold: **text** → <b>text</b> ──
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $text) ?? $text;

        // ── 7. Italic: *text* → <i>text</i> (not at word boundaries to avoid list conflicts) ──
        $text = preg_replace('/(?<!\w)\*(?!\s)(.+?)(?<!\s)\*(?!\w)/s', '<i>$1</i>', $text) ?? $text;

        // ── 8. Strikethrough: ~~text~~ → <s>text</s> ──
        $text = preg_replace('/~~(.+?)~~/s', '<s>$1</s>', $text) ?? $text;

        // ── 9. Links: [text](url) → <a href="url">text</a> ──
        $text = preg_replace(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            '<a href="$2">$1</a>',
            $text
        ) ?? $text;

        // ── 10. Headers: # Title → <b>Title</b> ──
        $text = preg_replace('/^#{1,6}\s+(.+)$/m', '<b>$1</b>', $text) ?? $text;

        // ── 11. List items: * item or - item → • item ──
        $text = preg_replace('/^[\s]*[\*\-]\s+/m', '• ', $text) ?? $text;

        // ── 12. Numbered lists: clean up ──
        $text = preg_replace('/^(\s*\d+)\.\s+/m', '$1. ', $text) ?? $text;

        // ── 13. Horizontal rules ──
        $text = preg_replace('/^[-=\*]{3,}$/m', '───────────────', $text) ?? $text;

        // ── 14. Clean up extra blank lines ──
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Convert Markdown tables to monospace format wrapped in <pre>.
     */
    public static function convertTables(string $text): string
    {
        return preg_replace_callback(
            '/(\|.+\|[\r\n]+\|[-| :]+\|[\r\n]+(?:\|.+\|[\r\n]*)+)/m',
            function ($matches) {
                $table = trim($matches[0]);
                $lines = explode("\n", $table);

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

                return '<pre>' . implode("\n", $result) . '</pre>';
            },
            $text
        ) ?? $text;
    }

    /**
     * Escape HTML special characters.
     */
    public static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * Strip all Markdown formatting for plain text output.
     */
    public static function stripMarkdown(string $text): string
    {
        $text = preg_replace('/```[\s\S]*?```/', '', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        $text = preg_replace('/\*{1,2}(.+?)\*{1,2}/', '$1', $text);
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);
        $text = preg_replace('/\|/m', ' ', $text);
        $text = preg_replace('/^[-=]{3,}$/m', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
