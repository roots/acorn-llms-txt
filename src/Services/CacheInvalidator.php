<?php

namespace Roots\AcornLlmsTxt\Services;

use Illuminate\Support\Facades\Cache;

class CacheInvalidator
{
    protected array $cacheTags = ['llms-txt'];

    protected array $cacheKeys = [
        'llms_txt_listing',
        'llms_txt_full',
        'llms_txt_small',
        'llms_txt_sitemap',
    ];

    public function __construct()
    {
        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        // Clear cache when posts are saved, deleted, or status changes
        add_action('save_post', [$this, 'invalidateCache']);
        add_action('delete_post', [$this, 'invalidateCache']);
        add_action('transition_post_status', [$this, 'invalidateCache']);

        // Clear cache when post meta changes (SEO settings)
        add_action('updated_post_meta', [$this, 'handleMetaUpdate'], 10, 4);
        add_action('added_post_meta', [$this, 'handleMetaUpdate'], 10, 4);
        add_action('deleted_post_meta', [$this, 'handleMetaDelete'], 10, 2);

        // Clear cache when taxonomy terms are updated
        add_action('edited_term', [$this, 'invalidateCache']);
        add_action('created_term', [$this, 'invalidateCache']);
        add_action('deleted_term', [$this, 'invalidateCache']);
    }

    public function invalidateCache(int|string|null $postId = null): void
    {
        // Only invalidate for configured post types
        if ($postId && ! $this->shouldInvalidateForPost($postId)) {
            return;
        }

        // Clear individual cache keys since tags aren't supported by all drivers
        foreach ($this->cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear individual post cache if applicable
        if ($postId) {
            $this->clearIndividualPostCache($postId);
        }
    }

    public function handleMetaUpdate(int $metaId, int $postId, string $metaKey, $metaValue): void
    {
        // Only clear cache for SEO-related meta changes
        if ($this->isSeoMeta($metaKey)) {
            $this->invalidateCache($postId);
        }
    }

    public function handleMetaDelete(array $metaIds, int $postId): void
    {
        // For deleted meta, we don't know which keys were deleted
        // so invalidate cache for any post that had meta deleted
        $this->invalidateCache($postId);
    }

    protected function shouldInvalidateForPost(int|string $postId): bool
    {
        $postType = get_post_type($postId);
        $configuredTypes = config('llms-txt.post_types', ['post', 'page']);

        return in_array($postType, $configuredTypes);
    }

    protected function isSeoMeta(string $metaKey): bool
    {
        $seoMetaKeys = [
            '_yoast_wpseo_meta-robots-noindex',
            'rank_math_robots',
            '_genesis_noindex',
        ];

        // Add custom noindex meta keys from configuration
        $customMetaKeys = config('llms-txt.seo.custom_noindex_meta_keys', []);
        $seoMetaKeys = array_merge($seoMetaKeys, $customMetaKeys);

        return in_array($metaKey, $seoMetaKeys);
    }

    protected function clearIndividualPostCache(int $postId): void
    {
        // Clear individual post cache if the feature is enabled
        if (! config('llms-txt.individual_posts.enabled', false)) {
            return;
        }

        $post = get_post($postId);
        if ($post && $post->post_name) {
            $cacheKey = "llms_txt_individual_{$post->post_name}";
            Cache::forget($cacheKey);
        }
    }

    public function clearAll(): void
    {
        foreach ($this->cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear all individual post caches (pattern-based clearing)
        if (config('llms-txt.individual_posts.enabled', false)) {
            // This is a simple approach - in production you might want
            // more sophisticated cache tagging
            $this->clearAllIndividualCaches();
        }
    }

    protected function clearAllIndividualCaches(): void
    {
        // Get all posts that could have individual caches
        $postTypes = config('llms-txt.individual_posts.post_types', ['post', 'page']);
        $posts = get_posts([
            'post_type' => $postTypes,
            'post_status' => 'any',
            'posts_per_page' => -1,
        ]);
        $posts = collect($posts)->pluck('post_name')->toArray();

        foreach ($posts as $slug) {
            $cacheKey = "llms_txt_individual_{$slug}";
            Cache::forget($cacheKey);
        }
    }
}
