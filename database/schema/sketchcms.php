<?php

/**
 * SketchCMS compatible schema.
 *
 * Every table and column below mirrors the original SketchCMS schema
 * (github.com/pentanoic/2026_SketchCMS, branch v1) so that an existing
 * SketchCMS database can be served by SoulCMS without any migration.
 *
 * The definition is written in a driver neutral vocabulary; the DDL is
 * rendered by the per-driver schema grammar (MySQL/MariaDB, PostgreSQL,
 * SQLite).
 */

declare(strict_types=1);

use SoulCMS\Database\Schema\Column;
use SoulCMS\Database\Schema\TableDefinition;

return [

    'chat' => (new TableDefinition('chat'))
        ->column(Column::id())
        ->column(Column::make('name', Column::TYPE_STRING)->length(255))
        ->column(Column::make('time', Column::TYPE_INT))
        ->column(Column::make('comment', Column::TYPE_TEXT))
        ->index(['time']),

    'articles_category' => (new TableDefinition('articles_category'))
        ->column(Column::id())
        ->column(Column::make('community', Column::TYPE_INT)->default(1))
        ->column(Column::make('name', Column::TYPE_STRING)->length(255))
        ->column(Column::make('slug', Column::TYPE_STRING)->length(255))
        ->column(Column::make('content', Column::TYPE_TEXT))
        ->column(Column::make('keyword', Column::TYPE_STRING)->length(255))
        ->column(Column::make('meta_data', Column::TYPE_TEXT)->nullable())
        ->index(['slug']),

    'articles_chap' => (new TableDefinition('articles_chap'))
        ->column(Column::id())
        ->column(Column::make('title', Column::TYPE_STRING)->length(255))
        ->column(Column::make('slug', Column::TYPE_STRING)->length(255))
        ->column(Column::make('content', Column::TYPE_TEXT))
        ->column(Column::make('time', Column::TYPE_INT))
        ->column(Column::make('box', Column::TYPE_INT))
        ->column(Column::make('author', Column::TYPE_STRING)->length(255))
        ->index(['box'])
        ->index(['slug']),

    'articles_cmt' => (new TableDefinition('articles_cmt'))
        ->column(Column::id())
        ->column(Column::make('time', Column::TYPE_INT))
        ->column(Column::make('comment', Column::TYPE_TEXT))
        ->column(Column::make('author', Column::TYPE_STRING)->length(255))
        ->column(Column::make('blogid', Column::TYPE_STRING)->length(255))
        ->index(['blogid']),

    'articles_file' => (new TableDefinition('articles_file'))
        ->column(Column::id())
        ->column(Column::make('time', Column::TYPE_INT))
        ->column(Column::make('filename', Column::TYPE_STRING)->length(255))
        ->column(Column::make('filecate', Column::TYPE_STRING)->length(255))
        ->column(Column::make('filesize', Column::TYPE_INT))
        ->column(Column::make('passphrase', Column::TYPE_STRING)->length(255)->nullable()->default('0'))
        ->column(Column::make('type', Column::TYPE_STRING)->length(10)->default('normal'))
        ->column(Column::make('author', Column::TYPE_STRING)->length(255)->default('sei'))
        ->column(Column::make('status', Column::TYPE_STRING)->length(10)->default('private'))
        ->column(Column::make('price', Column::TYPE_INT)->default(0))
        ->column(Column::make('saleoff', Column::TYPE_INT)->nullable())
        ->column(Column::make('condition', Column::TYPE_INT)->nullable())
        ->column(Column::make('mua', Column::TYPE_STRING)->length(255)->nullable())
        ->column(Column::make('blogid', Column::TYPE_INT)->nullable())
        ->index(['blogid']),

    'articles_tags' => (new TableDefinition('articles_tags'))
        ->column(Column::id())
        ->column(Column::make('name', Column::TYPE_STRING)->length(255))
        ->column(Column::make('slug', Column::TYPE_STRING)->length(255))
        ->column(Column::make('count', Column::TYPE_INT)->default(0))
        ->unique(['slug'], 'articles_tags_slug_unique'),

    'articles_post_tags' => (new TableDefinition('articles_post_tags'))
        ->column(Column::make('post_id', Column::TYPE_INT_UNSIGNED))
        ->column(Column::make('tag_id', Column::TYPE_INT_UNSIGNED))
        ->primaryKey('post_id', 'tag_id')
        ->index(['tag_id']),

    'articles_post' => (new TableDefinition('articles_post'))
        ->column(Column::id())
        ->column(Column::make('title', Column::TYPE_TEXT))
        ->column(Column::make('slug', Column::TYPE_STRING)->length(255))
        ->column(Column::make('content', Column::TYPE_TEXT))
        ->column(Column::make('meta_data', Column::TYPE_TEXT)->nullable())
        ->column(Column::make('time', Column::TYPE_INT))
        ->column(Column::make('blocked', Column::TYPE_INT)->default(0))
        ->column(Column::make('sticked', Column::TYPE_INT)->default(0))
        ->column(Column::make('update_time', Column::TYPE_INT))
        ->column(Column::make('category', Column::TYPE_INT))
        ->column(Column::make('view', Column::TYPE_INT)->default(1))
        ->column(Column::make('author', Column::TYPE_STRING)->length(255))
        ->column(Column::make('buyfile', Column::TYPE_INT)->default(0))
        ->column(Column::make('like', Column::TYPE_TEXT)->nullable())
        ->index(['category'])
        ->index(['author'])
        ->index(['update_time'])
        ->index(['slug']),

    'mail' => (new TableDefinition('mail'))
        ->column(Column::id())
        ->column(Column::make('sender_receiver', Column::TYPE_STRING)->length(255)->nullable())
        ->column(Column::make('nick', Column::TYPE_STRING)->length(255)->nullable())
        ->column(Column::make('content', Column::TYPE_TEXT)->nullable())
        ->column(Column::make('time', Column::TYPE_INT)->nullable())
        ->column(Column::make('update_time', Column::TYPE_INT)->nullable())
        ->column(Column::make('type', Column::TYPE_STRING)->length(10)->nullable())
        ->column(Column::make('view', Column::TYPE_STRING)->length(255)->default('no'))
        ->index(['sender_receiver']),

    'users' => (new TableDefinition('users'))
        ->column(Column::id())
        ->column(Column::make('email', Column::TYPE_STRING)->length(255)->nullable())
        ->column(Column::make('nick', Column::TYPE_STRING)->length(255))
        ->column(Column::make('name', Column::TYPE_STRING)->length(255))
        ->column(Column::make('pass', Column::TYPE_STRING)->length(255))
        ->column(Column::make('reset_token', Column::TYPE_STRING)->length(255)->nullable())
        ->column(Column::make('reset_expires', Column::TYPE_INT)->nullable())
        ->column(Column::make('failed_logins', Column::TYPE_INT)->nullable()->default(0))
        ->column(Column::make('lock_time', Column::TYPE_INT)->nullable()->default(0))
        ->column(Column::make('sex', Column::TYPE_STRING)->length(255)->default('boy'))
        ->column(Column::make('avatar', Column::TYPE_STRING)->length(255)->nullable()->default('15'))
        ->column(Column::make('cover', Column::TYPE_STRING)->length(255)->nullable()->default('8'))
        ->column(Column::make('new_mail', Column::TYPE_TEXT)->nullable())
        ->column(Column::make('mail_list', Column::TYPE_TEXT)->nullable())
        ->column(Column::make('blocklist', Column::TYPE_TEXT)->nullable())
        ->column(Column::make('level', Column::TYPE_INT)->default(0))
        ->column(Column::make('xu', Column::TYPE_INT)->default(500))
        ->column(Column::make('tiemluc', Column::TYPE_INT)->default(0))
        ->column(Column::make('reg', Column::TYPE_INT)->default(0))
        ->column(Column::make('on', Column::TYPE_INT)->default(0))
        ->column(Column::make('login', Column::TYPE_STRING)->length(255)->nullable())
        ->column(Column::make('status', Column::TYPE_STRING)->length(255)->nullable())
        ->column(Column::make('karma', Column::TYPE_INT)->default(0))
        ->column(Column::make('time_shoutbox', Column::TYPE_INT)->default(0))
        ->column(Column::make('sft_time', Column::TYPE_INT)->nullable()->default(0))
        ->column(Column::make('sft_level', Column::TYPE_INT)->nullable()->default(1))
        ->column(Column::make('karma_daily_time', Column::TYPE_INT)->nullable()->default(0))
        ->column(Column::make('karma_level', Column::TYPE_INT)->nullable()->default(0))
        ->column(Column::make('remember_token', Column::TYPE_STRING)->length(255)->nullable())
        ->index(['nick'])
        ->index(['email']),

    'users_waifu' => (new TableDefinition('users_waifu'))
        ->column(Column::id())
        ->column(Column::make('user_id', Column::TYPE_INT))
        ->column(Column::make('header', Column::TYPE_STRING)->length(50)->default('/images/header.png'))
        ->column(Column::make('rleft', Column::TYPE_STRING)->length(50)->default('/images/rleft.png'))
        ->column(Column::make('rright', Column::TYPE_STRING)->length(50)->default('/images/rright.png'))
        ->column(Column::make('close', Column::TYPE_INT)->default(0))
        ->index(['user_id']),
];
