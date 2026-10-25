<?php

declare(strict_types=1);

namespace SoulCMS\Support;

/** Safe BBCode subset for forum content. */
final class Bbcode
{
    /** Highlight a deliberately small set of tokens after source escaping. */
    private static function highlight(string $code, string $language): string
    {
        $keywords = match ($language) {
            'php' => ['echo', 'print', 'return', 'if', 'else', 'foreach', 'function', 'class', 'new', 'public', 'private', 'true', 'false', 'null'],
            'js', 'javascript' => ['const', 'let', 'var', 'function', 'return', 'if', 'else', 'new', 'true', 'false', 'null'],
            'sql' => ['select', 'from', 'where', 'insert', 'into', 'values', 'update', 'set', 'delete', 'join', 'and', 'or', 'null'],
            default => [],
        };
        if ($keywords === []) {
            return $code;
        }

        $words = implode('|', array_map(static fn (string $word): string => preg_quote($word, '/'), $keywords));
        $pattern = $language === 'php'
            ? '/(\$[A-Za-z_][A-Za-z0-9_]*|\b(?:' . $words . ')\b)/i'
            : '/\b(?:' . $words . ')\b/i';

        return preg_replace_callback($pattern, static function (array $match): string {
            $class = str_starts_with($match[0], '$') ? 'syntax-variable' : 'syntax-keyword';
            return '<span class="' . $class . '">' . $match[0] . '</span>';
        }, $code) ?? $code;
    }

    /**
     * @param callable(string):string $escape
     */
    public static function render(string $text, callable $escape): string
    {
        $protected = [];
        $prefix = 'SOULCMSBB' . bin2hex(random_bytes(12)) . 'END';
        while (str_contains($text, $prefix)) {
            $prefix = 'SOULCMSBB' . bin2hex(random_bytes(12)) . 'END';
        }

        $token = static function (string $html, bool $block = false) use (&$protected, $prefix): string {
            $value = $prefix . count($protected) . 'TOKEN';
            $protected[$value] = ['html' => $html, 'block' => $block];

            return $value;
        };

        $text = preg_replace_callback('/^```([a-zA-Z0-9_+.-]*)[ \\t]*\\r?\\n(.*?)^```[ \\t]*$/ms',
            static function (array $match) use ($escape, $token): string {
                $language = strtolower($match[1]);
                $source = preg_replace('/\\r?\\n$/', '', $match[2]) ?? $match[2];
                $code = $escape($source);
                if (in_array($language, ['php', 'js', 'javascript', 'sql'], true)) {
                    $code = self::highlight($code, $language);
                }
                $class = $language !== '' && in_array($language, ['php', 'js', 'javascript', 'sql'], true)
                    ? ' class="language-' . $language . '"'
                    : '';

                return "\n" . $token('<pre><code' . $class . '>' . $code . '</code></pre>', true) . "\n";
            },
            $text
        ) ?? $text;
        $text = preg_replace_callback('/\[code(?:=([a-zA-Z0-9_+.-]+))?\](.*?)\[\/code\]/is',
            static function (array $match) use ($escape, $token): string {
                $codeBlock = $match[1] !== '';
                $language = strtolower((string) ($match[1] ?? ''));
                $code = $escape($match[2]);
                if (in_array($language, ['php', 'js', 'javascript', 'sql'], true)) {
                    $code = self::highlight($code, $language);
                }
                $class = $language !== '' && in_array($language, ['php', 'js', 'javascript', 'sql'], true)
                    ? ' class="language-' . $language . '"'
                    : '';
                $fragment = $codeBlock
                    ? '<pre><code' . $class . '>' . $code . '</code></pre>'
                    : '<code>' . $code . '</code>';

                return $token($fragment, $codeBlock);
            },
            $text
        ) ?? $text;
        $text = preg_replace_callback('/\[list(?:=(\d))?\](.*?)\[\/list\]/is',
            static function (array $match) use ($escape, $token): string {
                $items = preg_split('/\[\*\]/i', trim($match[2]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if ($items === []) {
                    return $token('', true);
                }

                $tag = ($match[1] ?? '') === '1' ? 'ol' : 'ul';
                $html = '<' . $tag . '>';
                foreach ($items as $item) {
                    $content = self::render(trim($item), $escape);
                    if (preg_match('/^<p>(.*)<\/p>$/s', $content, $paragraph) === 1) {
                        $content = $paragraph[1];
                    }
                    $html .= '<li>' . $content . '</li>';
                }
                $html .= '</' . $tag . '>';

                return "\n" . $token($html, true) . "\n";
            },
            $text
        ) ?? $text;
        $text = preg_replace_callback('/\[hr\]/i',
            static fn (): string => "\n" . $token('<hr>', true) . "\n",
            $text
        ) ?? $text;
        $text = preg_replace_callback('/\[spoiler\](.*?)\[\/spoiler\]/is',
            static function (array $match) use ($escape, $token): string {
                $content = str_replace(["\r\n", "\r", "\n"], '<br>', $escape($match[1]));

                return "\n" . $token(
                    '<details class="bbcode-spoiler"><summary>Nội dung ẩn</summary><div>' . $content . '</div></details>',
                    true
                ) . "\n";
            },
            $text
        ) ?? $text;
        $text = preg_replace_callback('/\[quote(?:=([^]\r\n]+))?\](.*?)\[\/quote\]/is',
            static function (array $match) use ($escape, $token): string {
                $content = self::render(trim($match[2]), $escape);
                $content = preg_replace('/<\/p>\s*<p>/', '<br>', $content) ?? $content;
                $content = preg_replace('/^<p>|<\/p>$/', '', $content) ?? $content;
                $attribution = ($match[1] ?? '') !== '' ? '<cite>' . $escape(trim($match[1])) . '</cite>' : '';

                return "\n" . $token(
                    '<blockquote><p>' . $content . '</p>' . $attribution . '</blockquote>',
                    true
                ) . "\n";
            },
            $text
        ) ?? $text;
        $text = preg_replace_callback('/\[url=([^]\r\n]+)\](.*?)\[\/url\]/is',
            static function (array $match) use ($escape, $token): string {
                $url = trim($match[1]);
                $parts = parse_url($url);
                $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
                $safe = in_array($scheme, ['http', 'https'], true)
                    && isset($parts['host'])
                    && filter_var($url, FILTER_VALIDATE_URL) !== false;
                $label = self::render(trim($match[2]), $escape);
                if (preg_match('/^<p>(.*)<\/p>$/s', $label, $paragraph) === 1) {
                    $label = $paragraph[1];
                }

                return $token($safe
                    ? '<a href="' . $escape($url) . '" rel="nofollow noopener">' . $label . '</a>'
                    : $label);
            },
            $text
        ) ?? $text;
        $text = preg_replace_callback('/\[url\](.*?)\[\/url\]/is',
            static function (array $match) use ($escape, $token): string {
                $url = trim($match[1]);
                $parts = parse_url($url);
                $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
                $safe = in_array($scheme, ['http', 'https'], true)
                    && isset($parts['host'])
                    && filter_var($url, FILTER_VALIDATE_URL) !== false;

                return $token($safe
                    ? '<a href="' . $escape($url) . '" rel="nofollow noopener">' . $escape($url) . '</a>'
                    : $escape($url));
            },
            $text
        ) ?? $text;
        $text = preg_replace_callback('/\[img\](.*?)\[\/img\]/is',
            static function (array $match) use ($escape, $token): string {
                $url = trim($match[1]);
                $parts = parse_url($url);
                $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
                $safe = in_array($scheme, ['http', 'https'], true)
                    && isset($parts['host'])
                    && filter_var($url, FILTER_VALIDATE_URL) !== false;

                if (!$safe) {
                    return $token($escape($url));
                }

                $path = (string) ($parts['path'] ?? '');
                $alt = basename($path) ?: 'image';

                return $token('<img src="' . $escape($url) . '" alt="' . $escape($alt) . '" loading="lazy">');
            },
            $text
        ) ?? $text;

        $text = preg_replace_callback('/\[d\](.*?)\[\/d\]/is',
            static function (array $match) use ($escape, $token): string {
                $url = trim($match[1]);
                $parts = parse_url($url);
                $safe = is_array($parts)
                    && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                    && isset($parts['host'])
                    && filter_var($url, FILTER_VALIDATE_URL) !== false;

                if (!$safe) {
                    return $match[0];
                }

                $label = basename((string) ($parts['path'] ?? '')) ?: (string) $parts['host'];

                return $token('<a class="btn btn--primary" href="' . $escape($url) . '" rel="nofollow noopener">' . $escape($label) . '</a>');
            },
            $text
        ) ?? $text;

        $text = preg_replace_callback('/\[vid\](.*?)\[\/vid\]/is',
            static function (array $match) use ($escape, $token): string {
                $parts = parse_url(trim($match[1]));
                if (!is_array($parts)
                    || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                    || isset($parts['user'])
                    || isset($parts['pass'])
                    || isset($parts['port'])) {
                    return $token($escape($match[0]));
                }

                $host = strtolower((string) ($parts['host'] ?? ''));
                $path = (string) ($parts['path'] ?? '');
                $videoId = '';
                if ($host === 'youtu.be') {
                    $videoId = trim($path, '/');
                } elseif (in_array($host, ['youtube.com', 'www.youtube.com'], true)) {
                    if ($path === '/watch') {
                        parse_str((string) ($parts['query'] ?? ''), $query);
                        $videoId = (string) ($query['v'] ?? '');
                    } elseif (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})$~', $path, $idMatch)) {
                        $videoId = $idMatch[1];
                    }
                }

                if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
                    return $token($escape($match[0]));
                }

                return "\n" . $token(
                    '<iframe class="bbcode-video" src="https://www.youtube-nocookie.com/embed/' . $videoId . '" width="560" height="315" title="YouTube video" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
                    true
                ) . "\n";
            },
            $text
        ) ?? $text;

        $lines = explode("\n", $text);
        $cellsForRow = static function (string $line): ?array {
            $line = trim($line);
            if (!str_contains($line, '|')) {
                return null;
            }

            return array_map('trim', explode('|', trim($line, '|')));
        };
        $renderCell = static function (string $cell) use ($escape): string {
            $content = self::render($cell, $escape);
            if (preg_match('/^<p>(.*)<\/p>$/s', $content, $paragraph) === 1) {
                return $paragraph[1];
            }

            return $content;
        };
        for ($index = 0; $index < count($lines) - 1;) {
            $headers = $cellsForRow($lines[$index]);
            $separators = $cellsForRow($lines[$index + 1]);
            if ($headers === null || $separators === null || count($headers) < 2
                || count($headers) !== count($separators)
                || array_filter($separators, static fn (string $cell): bool => !preg_match('/^:?-{3,}:?$/', $cell)) !== []) {
                $index++;
                continue;
            }

            $table = '<div class="tablewrap"><table class="table"><thead><tr>';
            foreach ($headers as $header) {
                $table .= '<th>' . $renderCell($header) . '</th>';
            }
            $table .= '</tr></thead><tbody>';
            $next = $index + 2;
            while ($next < count($lines) && ($cells = $cellsForRow($lines[$next])) !== null) {
                $cells = array_pad(array_slice($cells, 0, count($headers)), count($headers), '');
                $table .= '<tr>';
                foreach ($cells as $cell) {
                    $table .= '<td>' . $renderCell($cell) . '</td>';
                }
                $table .= '</tr>';
                $next++;
            }
            $table .= '</tbody></table></div>';
            array_splice($lines, $index, $next - $index, [$token($table, true)]);
            $index++;
        }
        $text = implode("\n", $lines);

        $html = $escape($text);
        foreach (['b' => 'strong', 'strong' => 'strong', 'i' => 'em', 'em' => 'em', 'u' => 'u', 's' => 's', 'sub' => 'sub', 'sup' => 'sup'] as $tag => $element) {
            $html = preg_replace(
                '/\[' . $tag . '\](.*?)\[\/' . $tag . '\]/is',
                '<' . $element . '>$1</' . $element . '>',
                $html
            ) ?? $html;
        }

        $html = preg_replace_callback(
            '/\[color=(#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?)\](.*?)\[\/color\]/is',
            static fn (array $match): string => '<span style="color:' . strtolower($match[1]) . '">' . $match[2] . '</span>',
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/\[(red|blue|green|yellow|orange|purple|pink|brown|gray)\](.*?)\[\/\1\]/is',
            static fn (array $match): string => '<span style="color:' . strtolower($match[1]) . '">' . $match[2] . '</span>',
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/\[bcolor=(#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})|red|blue|green|yellow|orange|purple|pink|brown|gray)\](.*?)\[\/bcolor\]/is',
            static fn (array $match): string => '<span style="color:' . strtolower($match[1]) . ';font-weight:700">' . $match[2] . '</span>',
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/\[size=(\d+)\](.*?)\[\/size\]/is',
            static function (array $match): string {
                $size = (int) $match[1];
                if ($size < 8 || $size > 72) {
                    return $match[0];
                }

                return '<span style="font-size:' . $size . 'px">' . $match[2] . '</span>';
            },
            $html
        ) ?? $html;

        $alignment = ['center' => 'center', 'right' => 'right', 'left' => 'left', 'justify' => 'justify'];
        $html = preg_replace_callback(
            '/\[(center|right|left|justify)\](.*?)\[\/\1\]/is',
            static fn (array $match): string => '<span style="display:block;text-align:' . $alignment[strtolower($match[1])] . '">' . $match[2] . '</span>',
            $html
        ) ?? $html;

        $html = preg_replace('/~~([^~\n]+)~~/', '<del>$1</del>', $html) ?? $html;
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/(?<![\*\w])\*([^*\n]+)\*(?!\*)/s', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace('/`([^`\n]+)`/s', '<code>$1</code>', $html) ?? $html;
        $html = preg_replace_callback(
            '/<code>.*?<\/code>/s',
            static fn (array $match): string => $token($match[0]),
            $html
        ) ?? $html;
        $html = preg_replace_callback(
            '/\[([^\]\n]+)\]\(([^)\s]+)\)/',
            static function (array $match) use ($escape, $token): string {
                $url = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $parts = parse_url($url);
                $safe = is_array($parts)
                    && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                    && isset($parts['host'])
                    && filter_var($url, FILTER_VALIDATE_URL) !== false;

                return $safe
                    ? $token('<a href="' . $escape($url) . '" rel="nofollow noopener">' . $match[1] . '</a>')
                    : $match[0];
            },
            $html
        ) ?? $html;
        $html = preg_replace_callback(
            '~https?://[^\\s<>"\'\\[\\]]+~i',
            static function (array $match) use ($escape, $token): string {
                $url = $match[0];
                $punctuation = '';
                while ($url !== '' && preg_match('/[.,!?;:)]$/', $url)) {
                    $punctuation = substr($url, -1) . $punctuation;
                    $url = substr($url, 0, -1);
                }
                $decodedUrl = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $parts = parse_url($decodedUrl);
                $valid = is_array($parts)
                    && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                    && isset($parts['host'])
                    && filter_var($decodedUrl, FILTER_VALIDATE_URL) !== false;

                if (!$valid) {
                    return $match[0];
                }

                return $token('<a href="' . $escape($decodedUrl) . '" rel="nofollow noopener">' . $escape($decodedUrl) . '</a>') . $punctuation;
            },
            $html
        ) ?? $html;
        $smileys = [
            ':)' => '🙂', ':-)' => '🙂', ':(' => '🙁', ':-(' => '🙁', ':d' => '😄', ':-d' => '😄', ';)' => '😉', ';-)' => '😉', ':p' => '😛', ':-p' => '😛',
            ':/' => '😕', ':-/' => '😕', ':o' => '😮', ':-o' => '😮', ':|' => '😐', ':-|' => '😐', ":&#039;(" => '😢',
        ];
        $html = preg_replace_callback(
            '/:-?[)\(]|;-?[)]|:-?[dDpP]|:-?[\/]|:-?o|:-?\||:&#039;\(/i',
            static function (array $match) use ($smileys): string {
                return $smileys[strtolower($match[0])] ?? $match[0];
            },
            $html
        ) ?? $html;

        $lines = explode("\n", $html);
        $output = [];
        $listTag = null;

        for ($index = 0; $index < count($lines); ++$index) {
            $line = $lines[$index];
            if (preg_match('/^ {0,3}&gt; ?(.*)$/', $line, $firstQuote)) {
                if ($listTag !== null) {
                    $output[] = '</' . $listTag . '>';
                    $listTag = null;
                }
                $quoteLines = [$firstQuote[1]];
                while (isset($lines[$index + 1]) && preg_match('/^ {0,3}&gt; ?(.*)$/', $lines[$index + 1], $nextQuote)) {
                    $quoteLines[] = $nextQuote[1];
                    ++$index;
                }
                $output[] = '<blockquote><p>' . implode('<br>', $quoteLines) . '</p></blockquote>';
                continue;
            }

            $itemTag = null;
            if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $matches)) {
                $itemTag = 'ul';
            } elseif (preg_match('/^\s*\d{1,9}[.)]\s+(.*)$/', $line, $matches)) {
                $itemTag = 'ol';
            }
            if ($itemTag !== null) {
                if ($listTag !== $itemTag) {
                    if ($listTag !== null) {
                        $output[] = '</' . $listTag . '>';
                    }
                    $output[] = '<' . $itemTag . '>';
                    $listTag = $itemTag;
                }
                $output[] = '<li>' . $matches[1] . '</li>';
                continue;
            }

            if ($listTag !== null) {
                $output[] = '</' . $listTag . '>';
                $listTag = null;
            }
            if (trim($line) === '') {
                continue;
            }
            if (isset($protected[$line]) && $protected[$line]['block']) {
                $output[] = $line;
            } elseif (preg_match('/^ {0,3}(#{1,6})[ \t]+(.+?)[ \t]*#*[ \t]*$/', $line, $heading)) {
                $level = strlen($heading[1]);
                $output[] = '<h' . $level . '>' . $heading[2] . '</h' . $level . '>';
            } else {
                $output[] = '<p>' . $line . '</p>';
            }
        }

        if ($listTag !== null) {
            $output[] = '</' . $listTag . '>';
        }

        $rendered = implode("\n", $output);
        foreach ($protected as $placeholder => $fragment) {
            $rendered = str_replace($placeholder, $fragment['html'], $rendered);
        }

        return $rendered;
    }
}
