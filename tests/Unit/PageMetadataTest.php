<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SoulCMS\Support\PageMetadata;

final class PageMetadataTest extends TestCase
{
    public function testPostMetadataFallsBackToTitleKeywordsAndContentDescription(): void
    {
        self::assertSame(
            [
                'title' => 'Bài viết thử',
                'description' => 'Nội dung có đậm & an toàn',
                'keyword' => 'Bài, viết, thử',
            ],
            PageMetadata::forPost('Bài viết thử', 'Nội dung có [b]đậm[/b] & an toàn', null)
        );
    }

    public function testGeneratedDescriptionIsLimitedToOneHundredFiftyCharacters(): void
    {
        $metadata = PageMetadata::forPost('Tiêu đề', str_repeat('Nội dung ', 30), null);

        self::assertSame(150, mb_strlen($metadata['description']));
    }

    public function testPostMetadataUsesStoredNonEmptySeoOverrides(): void
    {
        self::assertSame(
            [
                'title' => 'Tiêu đề SEO',
                'description' => 'Mô tả riêng',
                'keyword' => 'diễn đàn, cộng đồng',
            ],
            PageMetadata::forPost(
                'Bài viết',
                'Nội dung mặc định',
                '{"meta_title":"Tiêu đề SEO","meta_desc":"Mô tả riêng","meta_keywords":"diễn đàn, cộng đồng"}'
            )
        );
    }

    public function testInvalidOrEmptyStoredSeoMetadataFallsBackToGeneratedValues(): void
    {
        $expected = [
            'title' => 'Bài viết',
            'description' => 'Nội dung mặc định',
            'keyword' => 'Bài, viết',
        ];

        self::assertSame($expected, PageMetadata::forPost('Bài viết', 'Nội dung mặc định', '{invalid'));
        self::assertSame(
            $expected,
            PageMetadata::forPost(
                'Bài viết',
                'Nội dung mặc định',
                '{"meta_title":"","meta_desc":"","meta_keywords":""}'
            )
        );
    }
}
