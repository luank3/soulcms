<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Articles\Repositories;

use SoulCMS\Database\Query\QueryBuilder;
use SoulCMS\Database\Query\Raw;
use SoulCMS\Database\Repository\BaseRepository;

/**
 * Data access for the forum/article tables of the SketchCMS schema.
 */
final class ArticleRepository extends BaseRepository
{
    public const TABLE_POST = 'articles_post';

    public const TABLE_CATEGORY = 'articles_category';

    public const TABLE_CHAPTER = 'articles_chap';

    public const TABLE_COMMENT = 'articles_cmt';

    public const TABLE_FILE = 'articles_file';

    public const TABLE_TAGS = 'articles_tags';

    public const TABLE_POST_TAGS = 'articles_post_tags';

    public function table(): string
    {
        return self::TABLE_POST;
    }

    /**
     * The forum listing: posts joined with their category.
     *
     * @return list<array<string,mixed>>
     */
    public function postList(
        int $categoryId = 0,
        int $per = 10,
        string $orderBy = 'update_time',
        string $sort = 'desc',
        int $offset = 0,
        bool $excludeSticked = false,
    ): array {
        $query = $this->connection->queryBuilder()
            ->from(self::TABLE_POST)
            ->select(
                self::TABLE_POST . '.*',
                self::TABLE_CATEGORY . '.id AS category_id',
                self::TABLE_CATEGORY . '.name AS category_name',
                self::TABLE_CATEGORY . '.slug AS category_slug',
            )
            ->leftJoin(self::TABLE_CATEGORY, self::TABLE_CATEGORY . '.id', '=', self::TABLE_POST . '.category');

        if ($categoryId > 0 && $this->categoryExists($categoryId)) {
            $query->where(self::TABLE_CATEGORY . '.id', $categoryId);
        }

        if ($excludeSticked) {
            $query->where(self::TABLE_POST . '.sticked', 0);
        }

        return $query
            ->orderBy(self::TABLE_POST . '.' . $orderBy, $sort)
            ->limit($per)
            ->offset($offset)
            ->get();
    }

    /**
     * Pinned posts with the author of the most recent comment.
     *
     * @return list<array<string,mixed>>
     */
    public function postListSticked(int $limit = 5): array
    {
        $lastCommentAuthor = sprintf(
            '(SELECT %s.author FROM %s WHERE %s.blogid = %s.id ORDER BY %s.id DESC LIMIT 1)',
            self::TABLE_COMMENT,
            self::TABLE_COMMENT,
            self::TABLE_COMMENT,
            self::TABLE_POST,
            self::TABLE_COMMENT
        );

        return $this->connection->queryBuilder()
            ->from(self::TABLE_POST)
            ->select(
                self::TABLE_POST . '.*',
                self::TABLE_CATEGORY . '.id AS category_id',
                self::TABLE_CATEGORY . '.name AS category_name',
                Raw::expr($lastCommentAuthor . ' AS last_comment_author')
            )
            ->leftJoin(self::TABLE_CATEGORY, self::TABLE_CATEGORY . '.id', '=', self::TABLE_POST . '.category')
            ->where(self::TABLE_POST . '.sticked', 1)
            ->orderBy(self::TABLE_POST . '.update_time', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function categoryList(int $limit = 10, bool $excludeNews = true): array
    {
        $query = $this->connection->queryBuilder()
            ->from(self::TABLE_CATEGORY)
            ->select(
                self::TABLE_CATEGORY . '.*',
                Raw::expr('COUNT(' . self::TABLE_POST . '.id) AS count_post')
            )
            ->leftJoin(self::TABLE_POST, self::TABLE_CATEGORY . '.id', '=', self::TABLE_POST . '.category')
            ->groupBy(self::TABLE_CATEGORY . '.id')
            ->orderBy(self::TABLE_CATEGORY . '.id', 'asc')
            ->limit($limit);

        if ($excludeNews) {
            $query->where(self::TABLE_CATEGORY . '.slug', '!=', 'news');
        }

        return $query->get();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function categoryDetail(int $id): ?array
    {
        return $this->connection->selectOne(
            sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', self::TABLE_CATEGORY),
            [$id]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function categoryBySlug(string $slug): ?array
    {
        return $this->connection->selectOne(
            sprintf('SELECT * FROM `%s` WHERE `slug` = ? LIMIT 1', self::TABLE_CATEGORY),
            [$slug]
        );
    }

    public function categoryExists(int $id): bool
    {
        return $this->connection->selectOne(
            sprintf('SELECT `id` FROM `%s` WHERE `id` = ? LIMIT 1', self::TABLE_CATEGORY),
            [$id]
        ) !== null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function postDetail(int $id): ?array
    {
        return $this->connection->selectOne(
            sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', self::TABLE_POST),
            [$id]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function postBySlug(string $slug): ?array
    {
        return $this->connection->selectOne(
            sprintf('SELECT * FROM `%s` WHERE `slug` = ? ORDER BY `id` DESC LIMIT 1', self::TABLE_POST),
            [$slug]
        );
    }

    /**
     * Increment and return the view counter of a post.
     */
    public function incrementView(int $id, int $current = 0): int
    {
        $views = $current + 1;

        $this->connection->query(
            sprintf('UPDATE `%s` SET `view` = ? WHERE `id` = ?', self::TABLE_POST),
            [$views, $id]
        );

        return $views;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function chapterList(int $postId): array
    {
        return $this->connection->select(
            sprintf('SELECT * FROM `%s` WHERE `box` = ? ORDER BY `id` ASC', self::TABLE_CHAPTER),
            [$postId]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function chapterDetail(int $id): ?array
    {
        return $this->connection->selectOne(
            sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', self::TABLE_CHAPTER),
            [$id]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function commentList(int $postId, int $limit = 10, int $offset = 0): array
    {
        return $this->connection->select(
            sprintf(
                'SELECT * FROM `%s` WHERE `blogid` = ? ORDER BY `id` ASC LIMIT %d OFFSET %d',
                self::TABLE_COMMENT,
                max(1, $limit),
                max(0, $offset)
            ),
            [$postId]
        );
    }

    public function countCommentsInPost(int $postId): int
    {
        return (int) $this->connection->scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `blogid` = ?', self::TABLE_COMMENT),
            [$postId]
        );
    }

    public function countChaptersInPost(int $postId): int
    {
        return (int) $this->connection->scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `box` = ?', self::TABLE_CHAPTER),
            [$postId]
        );
    }

    public function countPostsInCategory(int $categoryId, bool $excludeSticked = false): int
    {
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `category` = ?', self::TABLE_POST);

        if ($excludeSticked) {
            $sql .= ' AND `sticked` = 0';
        }

        return (int) $this->connection->scalar($sql, [$categoryId]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function fileListForPost(int $postId): array
    {
        return $this->connection->select(
            sprintf('SELECT * FROM `%s` WHERE `blogid` = ? ORDER BY `id` ASC', self::TABLE_FILE),
            [$postId]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function fileDetail(int $id): ?array
    {
        return $this->connection->selectOne(
            sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', self::TABLE_FILE),
            [$id]
        );
    }

    /**
     * Free text search across posts and chapters.
     *
     * @return list<array<string,mixed>>
     */
    public function search(string $needle, int $limit = 50): array
    {
        $like = '%' . $needle . '%';

        $sql = sprintf(
            'SELECT \'%s\' AS source, id, title, slug, author, time FROM %s WHERE title LIKE :needle
             UNION ALL
             SELECT \'%s\' AS source, id, title, slug, author, time FROM %s WHERE title LIKE :needle
             ORDER BY time DESC
             LIMIT %d',
            self::TABLE_POST,
            self::TABLE_POST,
            self::TABLE_CHAPTER,
            self::TABLE_CHAPTER,
            $limit
        );

        return $this->connection->select($sql, ['needle' => $like]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function tagsForPost(int $postId): array
    {
        return $this->connection->select(
            sprintf(
                'SELECT t.* FROM `%s` t INNER JOIN `%s` pt ON pt.tag_id = t.id WHERE pt.post_id = ?',
                self::TABLE_TAGS,
                self::TABLE_POST_TAGS
            ),
            [$postId]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function tagBySlug(string $slug): ?array
    {
        return $this->connection->selectOne(
            sprintf('SELECT * FROM `%s` WHERE `slug` = ? LIMIT 1', self::TABLE_TAGS),
            [$slug]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function postListByTag(int $tagId, int $per = 10, int $offset = 0): array
    {
        return $this->connection->queryBuilder()
            ->from(self::TABLE_POST)
            ->select(
                self::TABLE_POST . '.*',
                self::TABLE_CATEGORY . '.id AS category_id',
                self::TABLE_CATEGORY . '.name AS category_name'
            )
            ->innerJoin(self::TABLE_POST_TAGS, self::TABLE_POST_TAGS . '.post_id', '=', self::TABLE_POST . '.id')
            ->leftJoin(self::TABLE_CATEGORY, self::TABLE_CATEGORY . '.id', '=', self::TABLE_POST . '.category')
            ->where(self::TABLE_POST_TAGS . '.tag_id', $tagId)
            ->orderBy(self::TABLE_POST . '.update_time', 'desc')
            ->limit($per)
            ->offset($offset)
            ->get();
    }

    /**
     * @param list<string> $tags
     */
    public function syncTags(int $postId, array $tags): void
    {
        $this->connection->query(
            sprintf('DELETE FROM `%s` WHERE `post_id` = ?', self::TABLE_POST_TAGS),
            [$postId]
        );

        foreach ($tags as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $slug = \SoulCMS\Foundation\Str::slug($name);

            $existing = $this->connection->selectOne(
                sprintf('SELECT `id` FROM `%s` WHERE `slug` = ? LIMIT 1', self::TABLE_TAGS),
                [$slug]
            );

            if ($existing === null) {
                $tagId = $this->connection->insert(self::TABLE_TAGS, [
                    'name' => $name,
                    'slug' => $slug,
                    'count' => 0,
                ]);
            } else {
                $tagId = (int) $existing['id'];
            }

            $this->connection->query(
                sprintf('INSERT INTO `%s` (`post_id`, `tag_id`) VALUES (?, ?)', self::TABLE_POST_TAGS),
                [$postId, $tagId]
            );

            $this->connection->query(
                sprintf('UPDATE `%s` SET `count` = `count` + 1 WHERE `id` = ?', self::TABLE_TAGS),
                [$tagId]
            );
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function publishPost(array $data): int
    {
        return $this->connection->insert(self::TABLE_POST, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function editPost(int $id, array $data): int
    {
        return $this->connection->update(self::TABLE_POST, $data, ['id' => $id]);
    }

    public function deletePost(int $id): int
    {
        $this->connection->query(
            sprintf('DELETE FROM `%s` WHERE `blogid` = ?', self::TABLE_COMMENT),
            [(string) $id]
        );

        $this->connection->query(
            sprintf('DELETE FROM `%s` WHERE `box` = ?', self::TABLE_CHAPTER),
            [$id]
        );

        return $this->connection->delete(self::TABLE_POST, ['id' => $id]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function publishChapter(array $data): int
    {
        return $this->connection->insert(self::TABLE_CHAPTER, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function editChapter(int $id, array $data): int
    {
        return $this->connection->update(self::TABLE_CHAPTER, $data, ['id' => $id]);
    }

    public function deleteChapter(int $id): int
    {
        return $this->connection->delete(self::TABLE_CHAPTER, ['id' => $id]);
    }

    public function addComment(int $postId, string $author, string $comment, int $time): int
    {
        $id = $this->connection->insert(self::TABLE_COMMENT, [
            'blogid' => (string) $postId,
            'author' => $author,
            'comment' => $comment,
            'time' => $time,
        ]);

        $this->connection->query(
            sprintf('UPDATE `%s` SET `update_time` = ? WHERE `id` = ?', self::TABLE_POST),
            [$time, $postId]
        );

        return $id;
    }

    /**
     * Aggregate counters used by the dashboard and the sidebar.
     *
     * @return array<string,int>
     */
    public function stats(): array
    {
        $row = $this->connection->selectOne(sprintf(
            'SELECT
                (SELECT COUNT(*) FROM `%s` WHERE `slug` != \'news\') AS count_category,
                (SELECT COUNT(*) FROM `%s`) AS count_post,
                (SELECT COUNT(*) FROM `%s`) AS count_chapter,
                (SELECT COUNT(*) FROM `%s`) AS count_comment,
                (SELECT COUNT(*) FROM `%s`) AS count_file,
                (SELECT COUNT(*) FROM `users`) AS count_user',
            self::TABLE_CATEGORY,
            self::TABLE_POST,
            self::TABLE_CHAPTER,
            self::TABLE_COMMENT,
            self::TABLE_FILE
        ));

        return array_map('intval', $row ?: []);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function postListByAuthor(string $nick, int $limit = 20, int $offset = 0): array
    {
        return $this->connection->queryBuilder()
            ->from(self::TABLE_POST)
            ->select(
                self::TABLE_POST . '.*',
                self::TABLE_CATEGORY . '.id AS category_id',
                self::TABLE_CATEGORY . '.name AS category_name',
                self::TABLE_CATEGORY . '.slug AS category_slug'
            )
            ->leftJoin(self::TABLE_CATEGORY, self::TABLE_CATEGORY . '.id', '=', self::TABLE_POST . '.category')
            ->where(self::TABLE_POST . '.author', $nick)
            ->orderBy(self::TABLE_POST . '.update_time', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * The number of comments of every given post, in one query.
     *
     * @param list<int> $postIds
     * @return array<int,int>
     */
    public function commentCounts(array $postIds): array
    {
        return $this->countsByPost(self::TABLE_COMMENT, 'blogid', $postIds);
    }

    /**
     * The number of chapters of every given post, in one query.
     *
     * @param list<int> $postIds
     * @return array<int,int>
     */
    public function chapterCounts(array $postIds): array
    {
        return $this->countsByPost(self::TABLE_CHAPTER, 'box', $postIds);
    }

    /**
     * @param list<int> $postIds
     * @return array<int,int>
     */
    private function countsByPost(string $table, string $column, array $postIds): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));

        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($postIds), '?'));

        $rows = $this->connection->select(
            sprintf(
                'SELECT `%s` AS post_id, COUNT(*) AS total FROM `%s` WHERE `%s` IN (%s) GROUP BY `%s`',
                $column,
                $table,
                $column,
                $placeholders,
                $column
            ),
            $postIds
        );

        $counts = array_fill_keys($postIds, 0);

        foreach ($rows as $row) {
            $counts[(int) $row['post_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * The number of pinned posts.
     */
    public function countSticked(): int
    {
        return (int) $this->connection->scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `sticked` = 1', self::TABLE_POST)
        );
    }

    /**
     * Attach the author record of every post with a single extra query.
     *
     * @param list<array<string,mixed>> $posts
     * @param array<string,array<string,mixed>> $members nick => member row
     * @return list<array<string,mixed>>
     */
    public function hydrateAuthors(array $posts, array $members): array
    {
        foreach ($posts as $index => $post) {
            $key = mb_strtolower((string) ($post['author'] ?? ''));

            $posts[$index]['author_detail'] = $members[$key] ?? null;
        }

        return $posts;
    }

    /**
     * Attach the comment and chapter counters of every post with two queries.
     *
     * @param list<array<string,mixed>> $posts
     * @return list<array<string,mixed>>
     */
    public function hydrateCounters(array $posts): array
    {
        if ($posts === []) {
            return $posts;
        }

        $ids = array_map(static fn (array $post): int => (int) $post['id'], $posts);
        $comments = $this->commentCounts($ids);
        $chapters = $this->chapterCounts($ids);

        foreach ($posts as $index => $post) {
            $id = (int) $post['id'];

            $posts[$index]['comment_count'] = $comments[$id] ?? 0;
            $posts[$index]['chapter_count'] = $chapters[$id] ?? 0;
        }

        return $posts;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function allPostsForSitemap(int $limit = 5000): array
    {
        $query = $this->query()
            ->select('id', 'slug', 'update_time')
            ->orderBy('update_time', 'desc')
            ->limit($limit);

        return $query->get();
    }
}
