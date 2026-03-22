<?php

namespace Roots\AcornLlmsTxt\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Roots\AcornLlmsTxt\ContentFetcher;
use Roots\AcornLlmsTxt\ContentFormatter;
use Roots\AcornLlmsTxt\Services\MarkdownConverter;
use Roots\AcornLlmsTxt\Services\SeoFilter;

class LlmsTxtController
{
    protected ContentFetcher $fetcher;

    protected ContentFormatter $formatter;

    public function __construct(ContentFetcher $fetcher, ContentFormatter $formatter)
    {
        $this->fetcher = $fetcher;
        $this->formatter = $formatter;
    }

    public function index(): Response
    {
        $cacheTtl = config('llms-txt.cache_ttl', 3600);
        $content = Cache::remember('llms_txt_listing', $cacheTtl, function () {
            $posts = $this->fetcher->getPosts();
            $document = $this->formatter->formatListing($posts);

            return $document->toString();
        });

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function full(): Response
    {
        $cacheTtl = config('llms-txt.cache_ttl', 3600);
        $content = Cache::remember('llms_txt_full', $cacheTtl, function () {
            $posts = $this->fetcher->getPosts();

            return $this->formatter->formatFull($posts);
        });

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function small(): Response
    {
        $cacheTtl = config('llms-txt.cache_ttl', 3600);
        $content = Cache::remember('llms_txt_small', $cacheTtl, function () {
            $posts = $this->fetcher->getPosts();

            return $this->formatter->formatSmall($posts);
        });

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function sitemap(): Response
    {
        $cacheTtl = config('llms-txt.cache_ttl', 3600);
        $content = Cache::remember('llms_txt_sitemap', $cacheTtl, function () {
            if (config('llms-txt.individual_posts.enabled') && !empty(config('llms-txt.individual_posts.post_types'))) {
                add_filter('acorn/llms_txt/post_types', [$this, 'getIndividualPostTypes']);
            }

            $posts = $this->fetcher->getPosts();

            if (has_filter('acorn/llms_txt/post_types', [$this, 'getIndividualPostTypes'])) {
                remove_filter('acorn/llms_txt/post_types', [$this, 'getIndividualPostTypes']);
            }

            return $this->formatter->formatSitemap($posts);
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function getIndividualPostTypes(): array
    {
        return config('llms-txt.individual_posts.post_types');
    }

    public function individual(string $postType, string $slug): Response
    {
        // Get configuration for individual posts
        $cacheTtl = config('llms-txt.individual_posts.cache_ttl', 3600);

        $cacheKey = "llms_txt_individual_{$postType}_{$slug}";

        $content = Cache::remember($cacheKey, $cacheTtl, function () use ($postType, $slug) {

            // Find the post by slug and posttype
            $post = $this->findPostByPostTypeAndSlug($postType, $slug);

            if (! $post) {
                return null;
            }

            // Check if post should be excluded (SEO filters, etc.)
            $seoFilter = new SeoFilter;
            if ($seoFilter->shouldExcludePost($post->ID)) {
                return null;
            }

            return $this->formatIndividualPost($post);
        });

        if ($content === null) {
            abort(404);
        }

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex');
    }

    protected function findPostByPostTypeAndSlug(string $postType, string $slug): ?\WP_Post
    {
        // Validate post types before querying
        if (! post_type_exists($postType)) {
            error_log('No valid post types for individual post lookup: '.$postType);

            return null;
        }

        $args = [
            'name' => sanitize_title($slug),
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => 1,
        ];

        $posts = get_posts($args);

        return ! empty($posts) ? $posts[0] : null;
    }

    protected function formatIndividualPost(\WP_Post $post): string
    {
        $markdownConverter = new MarkdownConverter;
        $output = [];

        // Post header
        $output[] = "# {$post->post_title}";
        $output[] = '';
        $output[] = 'URL: '.get_permalink($post->ID);

        // Add optional metadata based on configuration
        if (config('llms-txt.content.include_date', true)) {
            $dateFormat = config('llms-txt.content.date_format', 'Y-m-d');
            $output[] = 'Date: '.date($dateFormat, strtotime($post->post_date));
        }

        if (config('llms-txt.content.include_author', true)) {
            $author = get_the_author_meta('display_name', $post->post_author);
            $output[] = "Author: {$author}";
        }

        $postTypeObj = get_post_type_object($post->post_type);
        $output[] = 'Type: '.($postTypeObj?->labels?->singular_name ?? ucfirst($post->post_type));

        // Add taxonomy information if enabled
        if (config('llms-txt.content.include_taxonomies', true)) {
            $taxonomies = get_object_taxonomies($post->ID);
            foreach ($taxonomies as $taxonomy) {
                $terms = wp_get_post_terms($post->ID, $taxonomy);
                if (! is_wp_error($terms) && ! empty($terms)) {
                    $taxonomyObj = get_taxonomy($taxonomy);
                    $termNames = array_map(fn ($term) => $term->name, $terms);
                    $output[] = "{$taxonomyObj->labels->singular_name}: ".implode(', ', $termNames);
                }
            }
        }

        // Add featured image if enabled
        if (config('llms-txt.content.include_featured_image', false)) {
            $featuredImage = get_the_post_thumbnail_url($post->ID, 'full');
            if ($featuredImage) {
                $output[] = "Featured Image: {$featuredImage}";
            }
        }

        $output[] = '';
        $output[] = '---';
        $output[] = '';

        // Process and convert content
        $content = $post->post_content;

        // Process blocks and shortcodes if configured (default true)
        if (config('llms-txt.content.process_shortcodes', true)) {
            // Parse blocks first (Gutenberg content)
            $content = do_blocks($content);
            // Then process any remaining shortcodes
            $content = do_shortcode($content);
        }

        if (! config('llms-txt.content.process_shortcodes', true)) {
            // Strip both blocks and shortcodes
            $content = strip_shortcodes($content);
            // Remove block markup patterns
            $content = preg_replace('/<!-- wp:.*?-->/s', '', $content);
            $content = preg_replace('/<!-- \/wp:.*?-->/s', '', $content);
        }

        // Apply content length limit if configured
        $maxLength = config('llms-txt.limits.max_content_length', 0);
        if ($maxLength > 0 && Str::length($content) > $maxLength) {
            $content = Str::limit($content, $maxLength);
        }

        // Convert to markdown
        $markdownContent = $markdownConverter->convert($content);
        $output[] = $markdownContent;

        $finalContent = implode("\n", $output);

        // Allow filtering of final content before serving
        return apply_filters('acorn/llms_txt/content', $finalContent);
    }
}
