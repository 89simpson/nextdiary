<?php

namespace OCA\NextDiary\Service;

use OCA\NextDiary\Db\EntryTagMapper;
use OCA\NextDiary\Db\TagMapper;
use OCP\DB\Exception;

class TagService
{
    private TagMapper $tagMapper;
    private EntryTagMapper $entryTagMapper;

    public function __construct(TagMapper $tagMapper, EntryTagMapper $entryTagMapper)
    {
        $this->tagMapper = $tagMapper;
        $this->entryTagMapper = $entryTagMapper;
    }

    /**
     * Parse #hashtags from content and sync tags in DB.
     *
     * @return array Array of ['id' => int, 'name' => string]
     * @throws Exception
     */
    public function syncTagsForEntry(string $uid, int $entryId, string $content): array
    {
        $tagNames = $this->extractTags($content);

        // Remove old associations
        $this->entryTagMapper->detachAllFromEntry($entryId);

        $result = [];
        foreach ($tagNames as $name) {
            $tag = $this->tagMapper->findOrCreate($uid, $name);
            $this->entryTagMapper->attach($entryId, $tag->getId());
            $result[] = [
                'id' => $tag->getId(),
                'name' => $tag->getTagName(),
            ];
        }

        // Clean up unused tags
        $this->tagMapper->deleteUnusedTags($uid);

        return $result;
    }

    /**
     * Get tag cloud for a user (tags with counts).
     *
     * @return array Array of ['id' => int, 'name' => string, 'count' => int]
     * @throws Exception
     */
    public function getTagCloud(string $uid): array
    {
        return $this->tagMapper->findByUserWithCounts($uid);
    }

    /**
     * Get tags for a specific entry.
     *
     * @return array Array of ['id' => int, 'name' => string]
     * @throws Exception
     */
    public function getTagsForEntry(int $entryId): array
    {
        return $this->entryTagMapper->findTagsByEntry($entryId);
    }

    /**
     * Get entry IDs by tag.
     *
     * @return int[]
     * @throws Exception
     */
    public function getEntryIdsByTag(int $tagId, int $limit = 50, int $offset = 0): array
    {
        return $this->entryTagMapper->findEntryIdsByTag($tagId, $limit, $offset);
    }

    /**
     * Sync tags for an entry by tag names array.
     *
     * @param string[] $tagNames Array of tag names
     * @return array Array of ['id' => int, 'name' => string]
     * @throws Exception
     */
    public function syncTagsByNames(string $uid, int $entryId, array $tagNames): array
    {
        $this->entryTagMapper->detachAllFromEntry($entryId);

        $result = [];
        foreach ($tagNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $tag = $this->tagMapper->findOrCreate($uid, mb_strtolower($name));
            $this->entryTagMapper->attach($entryId, $tag->getId());
            $result[] = [
                'id' => $tag->getId(),
                'name' => $tag->getTagName(),
            ];
        }

        $this->tagMapper->deleteUnusedTags($uid);

        return $result;
    }

    /**
     * Remove all tags from an entry and clean up unused tags.
     *
     * @throws Exception
     */
    public function removeTagsFromEntry(string $uid, int $entryId): void
    {
        $this->entryTagMapper->detachAllFromEntry($entryId);
        $this->tagMapper->deleteUnusedTags($uid);
    }

    /**
     * Extract #hashtag names from content.
     *
     * @return string[] Unique lowercase tag names
     */
    public function extractTags(string $content): array
    {
        if (preg_match_all('/#([\p{L}\p{N}_-]+)/u', $content, $matches)) {
            $tags = array_map('mb_strtolower', $matches[1]);
            $tags = array_unique($tags);
            // Limit tag name length to 50
            $tags = array_filter($tags, fn($t) => mb_strlen($t) <= 50);
            return array_values($tags);
        }

        return [];
    }
}
