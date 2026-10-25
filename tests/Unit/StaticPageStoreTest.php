<?php

declare(strict_types=1);

namespace SoulCMS\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use SoulCMS\Database\Connection;
use SoulCMS\Database\Driver;
use SoulCMS\Support\SettingsStore;
use SoulCMS\Support\StaticPageStore;

final class StaticPageStoreTest extends TestCase
{
    public function testPagesCanBeCreatedUpdatedFoundAndDeletedWithoutSchemaChanges(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $file = sys_get_temp_dir() . '/soulcms-static-pages-' . bin2hex(random_bytes(6)) . '.json';
        $pages = new StaticPageStore(new SettingsStore($connection, $file));

        try {
            self::assertTrue($pages->save(null, [
                'slug' => 'privacy',
                'title' => 'Privacy',
                'content' => 'Private details.',
                'description' => 'Privacy policy',
                'keyword' => 'privacy,data',
            ]));
            self::assertSame('Private details.', $pages->find('privacy')['content']);
            self::assertFalse($pages->save(null, [
                'slug' => 'privacy',
                'title' => 'Duplicate',
                'content' => 'Ignored',
                'description' => '',
                'keyword' => '',
            ]));

            self::assertTrue($pages->save('privacy', [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'content' => 'Updated.',
                'description' => '',
                'keyword' => '',
            ]));
            self::assertNull($pages->find('privacy'));
            self::assertSame('Updated.', $pages->find('privacy-policy')['content']);
            self::assertTrue($pages->delete('privacy-policy'));
            self::assertNull($pages->find('privacy-policy'));
        } finally {
            @unlink($file);
        }
    }

    public function testPageSlugsAndRequiredFieldsAreValidated(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'), Driver::Sqlite);
        $file = sys_get_temp_dir() . '/soulcms-static-pages-' . bin2hex(random_bytes(6)) . '.json';
        $pages = new StaticPageStore(new SettingsStore($connection, $file));

        try {
            self::assertFalse($pages->save(null, [
                'slug' => '../login',
                'title' => 'Unsafe',
                'content' => 'x',
                'description' => '',
                'keyword' => '',
            ]));
            self::assertFalse($pages->save(null, [
                'slug' => 'empty-title',
                'title' => ' ',
                'content' => 'x',
                'description' => '',
                'keyword' => '',
            ]));
            self::assertSame([], $pages->all());
        } finally {
            @unlink($file);
        }
    }
}
