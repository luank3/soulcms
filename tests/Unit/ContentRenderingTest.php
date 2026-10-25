<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ContentRenderingTest extends TestCase
{
    public function testSmileysRenderAsUnicodeWithoutParsingCodeOrEscapedHtml(): void
    {
        self::assertSame(
            '<p>Hello 🙂 😄 and &lt;script&gt;x&lt;/script&gt;</p>',
            markdown_to_html('Hello :) :D and <script>x</script>')
        );
        self::assertSame('<p><code>:)</code> 🙂</p>', markdown_to_html('`:)` :)'));
    }
    public function testAdditionalCommonSmileysRenderOutsideInlineCode(): void
    {
        self::assertSame(
            '<p>confused 😕 surprised 😮 neutral 😐 sad 😢 <code>:/ :o :| :&#039;(</code></p>',
            markdown_to_html("confused :/ surprised :o neutral :| sad :'( `:/ :o :| :'(`")
        );
    }

    public function testPhpCodeBlocksHighlightKeywordsVariablesAndEscapeSource(): void
    {
        $html = markdown_to_html('[code=php]<?php echo $message; ?> <script>alert(1)</script>[/code]');

        self::assertStringContainsString('<pre><code class="language-php">', $html);
        self::assertStringContainsString('<span class="syntax-keyword">echo</span>', $html);
        self::assertStringContainsString('<span class="syntax-variable">$message</span>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('<span class="syntax-keyword">const</span>', markdown_to_html('[code=javascript]const answer = 42;[/code]'));
        self::assertSame('<pre><code>plain &lt;script&gt;</code></pre>', markdown_to_html('[code=unknown]plain <script>[/code]'));
    }

    public function testBbcodeBasicFormattingAndMarkdownAreSupported(): void
    {
        self::assertSame(
            '<p><strong>bold</strong> and <em>italic</em> and <u>underlined</u></p>',
            markdown_to_html('[b]bold[/b] and [i]italic[/i] and [u]underlined[/u]')
        );
        self::assertSame('<p><strong>bold</strong> and <em>italic</em></p>', markdown_to_html('**bold** and *italic*'));
    }

    public function testBbcodeSubscriptAndSuperscriptUseSemanticTags(): void
    {
        self::assertSame(
            '<p>H<sub>2</sub>O and x<sup>2</sup></p>',
            markdown_to_html('H[sub]2[/sub]O and x[sup]2[/sup]')
        );
    }

    public function testSpoilerBbcodeRendersCollapsedEscapedDisclosure(): void
    {
        self::assertSame(
            '<details class="bbcode-spoiler"><summary>Nội dung ẩn</summary><div>&lt;script&gt;alert(1)&lt;/script&gt;</div></details>',
            markdown_to_html('[spoiler]<script>alert(1)</script>[/spoiler]')
        );
    }

    public function testColorBbcodeAllowsOnlySafeHexColors(): void
    {
        self::assertSame(
            '<p><span style="color:#369">blue</span></p>',
            markdown_to_html('[color=#369]blue[/color]')
        );
        self::assertSame(
            '<p>[color=red;position:absolute]text[/color]</p>',
            markdown_to_html('[color=red;position:absolute]text[/color]')
        );
    }

    public function testFontSizeBbcodeAcceptsOnlyBoundedPixelValues(): void
    {
        self::assertSame(
            '<p><span style="font-size:24px">large</span></p>',
            markdown_to_html('[size=24]large[/size]')
        );
        self::assertSame(
            '<p>[size=999]too large[/size]</p>',
            markdown_to_html('[size=999]too large[/size]')
        );
    }

    public function testAlignmentBbcodeUsesOnlySupportedTextAlignmentValues(): void
    {
        self::assertSame(
            '<p><span style="display:block;text-align:center">centered</span> <span style="display:block;text-align:justify">justified</span></p>',
            markdown_to_html('[center]centered[/center] [justify]justified[/justify]')
        );
    }

    public function testLegacyFormattingAliasesAndNamedColorsRenderSafely(): void
    {
        self::assertSame(
            '<p><strong>strong</strong> and <em>emphasis</em> <span style="color:red">red</span></p>',
            markdown_to_html('[strong]strong[/strong] and [em]emphasis[/em] [red]red[/red]')
        );
    }

    public function testBbcodeListNumericValuesOtherThanOneRemainUnordered(): void
    {
        self::assertSame(
            '<ul><li>first</li><li>second</li></ul>',
            markdown_to_html('[list=2][*]first[*]second[/list]')
        );
    }

    public function testBbcodeQuotesRenderInlineFormattingAndEscapeHtml(): void
    {
        self::assertSame(
            '<blockquote><p><strong>quoted</strong><br>&lt;script&gt;</p></blockquote>',
            markdown_to_html('[quote][b]quoted[/b]' . "\n" . '<script>[/quote]')
        );
    }

    public function testBbcodeListsRenderOrderedAndUnorderedWithEscapedItems(): void
    {
        self::assertSame(
            '<ul><li>one</li><li>&lt;script&gt;</li></ul>',
            markdown_to_html('[list][*]one[*]<script>[/list]')
        );
        self::assertSame(
            '<ol><li>first</li><li>second</li></ol>',
            markdown_to_html('[list=1][*]first[*]second[/list]')
        );
    }

    public function testBbcodeListItemsRenderInlineFormattingAndEscapeHtml(): void
    {
        self::assertSame(
            '<ul><li><strong>bold</strong> and &lt;script&gt;</li></ul>',
            markdown_to_html('[list][*][b]bold[/b] and <script>[/list]')
        );
    }

    public function testBoldColorBbcodeRestrictsColorToHexOrKnownNames(): void
    {
        self::assertSame(
            '<p><span style="color:#c00;font-weight:700">alert</span></p>',
            markdown_to_html('[bcolor=#c00]alert[/bcolor]')
        );
        self::assertSame(
            '<p>[bcolor=red;display:none]alert[/bcolor]</p>',
            markdown_to_html('[bcolor=red;display:none]alert[/bcolor]')
        );
    }

    public function testHorizontalRuleBbcodeRendersAsAStandaloneBlock(): void
    {
        self::assertSame(
            "<p>before</p>\n<hr>\n<p>after</p>",
            markdown_to_html("before\n[hr]\nafter")
        );
    }

    public function testVideoBbcodeEmbedsOnlyValidatedYouTubeIds(): void
    {
        self::assertSame(
            '<iframe class="bbcode-video" src="https://www.youtube-nocookie.com/embed/abcdefghijk" width="560" height="315" title="YouTube video" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
            markdown_to_html('[vid]https://youtu.be/abcdefghijk[/vid]')
        );
        self::assertSame(
            '<p>[vid]https://evil.test/embed/abcdefghijk[/vid]</p>',
            markdown_to_html('[vid]https://evil.test/embed/abcdefghijk[/vid]')
        );
    }

    public function testMarkdownTablesRenderSemanticEscapedCells(): void
    {
        self::assertSame(
            '<div class="tablewrap"><table class="table"><thead><tr><th>Name</th><th>Value</th></tr></thead><tbody><tr><td>one</td><td>&lt;script&gt;</td></tr></tbody></table></div>',
            markdown_to_html("| Name | Value |\n| --- | --- |\n| one | <script> |")
        );
    }

    public function testMarkdownTableCellsRenderInlineFormattingAndEscapeHtml(): void
    {
        self::assertSame(
            '<div class="tablewrap"><table class="table"><thead><tr><th><strong>Name</strong></th><th>Value</th></tr></thead><tbody><tr><td><em>one</em></td><td>&lt;script&gt;</td></tr></tbody></table></div>',
            markdown_to_html("| **Name** | Value |\n| --- | --- |\n| *one* | <script> |")
        );
    }

    public function testDownloadBbcodeCreatesOnlySafeHttpLinks(): void
    {
        self::assertSame(
            '<p><a class="btn btn--primary" href="https://files.example.test/archive.zip" rel="nofollow noopener">archive.zip</a></p>',
            markdown_to_html('[d]https://files.example.test/archive.zip[/d]')
        );
        self::assertSame(
            '<p>[d]javascript:alert(1)[/d]</p>',
            markdown_to_html('[d]javascript:alert(1)[/d]')
        );
    }

    public function testBbcodeUrlLabelsRenderInlineFormattingSafely(): void
    {
        self::assertSame(
            '<p><a href="https://example.test/path" rel="nofollow noopener"><strong>bold</strong> &lt;script&gt;</a></p>',
            markdown_to_html('[url=https://example.test/path][b]bold[/b] <script>[/url]')
        );
        self::assertSame(
            '<p><strong>bad</strong></p>',
            markdown_to_html('[url=javascript:alert(1)][b]bad[/b][/url]')
        );
    }

    public function testMarkdownLinksRenderSafeHttpUrlsAndRejectExecutableSchemes(): void
    {
        self::assertSame(
            '<p><a href="https://example.test/path" rel="nofollow noopener">SoulCMS</a></p>',
            markdown_to_html('[SoulCMS](https://example.test/path)')
        );
        self::assertSame(
            '<p>[bad](javascript:alert(1))</p>',
            markdown_to_html('[bad](javascript:alert(1))')
        );
    }

    public function testMarkdownOrderedListsRenderAsOrderedLists(): void
    {
        self::assertSame(
            "<ol>\n<li>first</li>\n<li>second</li>\n</ol>",
            markdown_to_html("1. first\n2. second")
        );
    }

    public function testMarkdownListsCloseAndSwitchTypesWhenMarkersChange(): void
    {
        self::assertSame(
            "<ul>\n<li>bullet</li>\n</ul>\n<ol>\n<li>numbered</li>\n</ol>",
            markdown_to_html("- bullet\n1. numbered")
        );
    }

    public function testMarkdownBlockquotesRenderEscapedInlineFormattedContent(): void
    {
        self::assertSame(
            '<blockquote><p>Quoted <strong>text</strong></p></blockquote>',
            markdown_to_html('> Quoted **text**')
        );
    }

    public function testMarkdownFencedCodeBlocksAreEscapedAndHighlightedForSupportedLanguages(): void
    {
        self::assertSame(
            '<pre><code class="language-js"><span class="syntax-keyword">const</span> value = &lt;script&gt;;</code></pre>',
            markdown_to_html("```js\nconst value = <script>;\n```")
        );
    }

    public function testMarkdownAtxHeadingsRenderAtTheirDeclaredLevel(): void
    {
        self::assertSame(
            '<h2>Chapter <strong>One</strong></h2>',
            markdown_to_html('## Chapter **One**')
        );
    }

    public function testMarkdownStrikethroughRendersSemanticDeletion(): void
    {
        self::assertSame('<p>Marked <del>obsolete</del> text</p>', markdown_to_html('Marked ~~obsolete~~ text'));
    }

    public function testPlainHttpUrlsAutolinkWithoutConsumingTrailingPunctuation(): void
    {
        self::assertSame(
            '<p>Visit <a href="https://example.test/page" rel="nofollow noopener">https://example.test/page</a>.</p>',
            markdown_to_html('Visit https://example.test/page.')
        );
        self::assertSame(
            '<p><code>https://example.test/page</code></p>',
            markdown_to_html('[code]https://example.test/page[/code]')
        );
    }

    public function testBbcodeUrlsAndImagesRejectUnsafeSchemes(): void
    {
        self::assertSame(
            '<p><a href="https://example.test/path" rel="nofollow noopener">site</a></p>',
            markdown_to_html('[url=https://example.test/path]site[/url]')
        );
        self::assertSame('<p>x</p>', markdown_to_html('[url=javascript:alert(1)]x[/url]'));
        self::assertSame('<p>javascript:alert(1) &lt;script&gt;x&lt;/script&gt;</p>', markdown_to_html('[img]javascript:alert(1)[/img] <script>x</script>'));
    }

    public function testContentExcerptStripsFormattingAndLimitsUnicodeText(): void
    {
        self::assertSame(
            'Một câu có đậm và ký tự & đặc biệt…',
            content_excerpt('Một câu có [b]đậm[/b] và ký tự & đặc biệt vượt quá giới hạn', 34)
        );
        self::assertSame('Đoạn ngắn', content_excerpt('[i]Đoạn ngắn[/i]', 32));
    }

    public function testAttributedQuoteEscapesAttributionAndContent(): void
    {
        self::assertSame(
            '<blockquote><p>quoted &lt;script&gt;</p><cite>&lt;Author&gt;</cite></blockquote>',
            markdown_to_html('[quote=<Author>]quoted <script>[/quote]')
        );
    }

    public function testQuoteAndCodeBbcodeDoNotParseEmbeddedMarkup(): void
    {
        self::assertSame(
            '<blockquote><p>quoted &lt;script&gt;</p></blockquote>',
            markdown_to_html('[quote]quoted <script>[/quote]')
        );
        self::assertSame('<p><code>[b]literal[/b]</code></p>', markdown_to_html('[code][b]literal[/b][/code]'));
    }
}
