<?php

namespace Roots\AcornLlmsTxt;

use Illuminate\Support\Collection;
use Roots\AcornLlmsTxt\Data\Link;
use Roots\AcornLlmsTxt\Data\LlmsTxtDocument;
use Roots\AcornLlmsTxt\Data\Section;
use Roots\AcornLlmsTxt\Services\MarkdownConverter;

class ContentFormatter
{
    public function __construct(
        protected MarkdownConverter $markdownConverter
    ) {}

    public function formatListing(Collection $posts): LlmsTxtDocument
    {
        $document = (new LlmsTxtDocument)
            ->title(get_bloginfo('name'))
            ->description(get_bloginfo('description') ?: '');

        // Add additional resources section
        $resourcesSection = (new Section)
            ->name('Additional Resources')
            ->addLink(
                (new Link)
                    ->title('Full Content')
                    ->url(home_url().'/llms-full.txt')
                    ->details('Complete content of all '.$this->getConfiguredPostTypesDescription().' in Markdown format')
            )
            ->addLink(
                (new Link)
                    ->title('Small Content')
                    ->url(home_url().'/llms-small.txt')
                    ->details('Excerpts and summaries of all '.$this->getConfiguredPostTypesDescription().' for faster processing')
            );

        $document->addSection($resourcesSection);

        // Allow developers to add sections before default content
        do_action('acorn/llms_txt/before_sections', $document);

        // Group posts by type and create sections
        $grouped = $posts->groupBy('type');

        // Apply posts per section limit
        $postsPerSection = config('llms-txt.limits.posts_per_section', 50);

        $grouped->each(function ($items, $type) use ($document, $postsPerSection) {
            // Limit posts per section if configured
            if ($postsPerSection > 0) {
                $items = $items->take($postsPerSection);
            }

            $sectionName = match ($type) {
                'Post' => 'Recent Posts',
                'Page' => 'Pages',
                default => "{$type}s"
            };

            $section = (new Section)->name($sectionName);

            // Handle hierarchical structure for pages
            if ($type === 'Page') {
                $this->addHierarchicalPages($section, $items);

                return;
            }

            // Regular flat structure for non-pages
            $items->each(function ($post) use ($section) {
                $metadata = $this->formatMetadata($post);
                $title = $post['title'].$metadata;

                $link = (new Link)
                    ->title($title)
                    ->url($post['url'])
                    ->details($post['excerpt']);

                $section->addLink($link);
            });

            $document->addSection($section);
        });

        // Allow developers to add sections after default content
        do_action('acorn/llms_txt/after_sections', $document);

        // Allow developers to add completely custom sections
        do_action('acorn/llms_txt/custom_sections', $document);

        // Allow filtering of the entire document before output
        return apply_filters('acorn/llms_txt/document', $document);
    }

    public function formatFull(Collection $posts): string
    {
        $output = [];

        // Site header
        $siteName = get_bloginfo('name');
        $output[] = "# {$siteName}";
        $output[] = '';

        // Add each post with full content
        $posts->each(function ($post) use (&$output) {
            $output[] = '---';
            $output[] = '';
            $output[] = "## {$post['title']}";
            $output[] = "URL: {$post['url']}";

            // Add optional metadata based on configuration
            if (config('llms-txt.content.include_date', true) && isset($post['date'])) {
                $dateFormat = config('llms-txt.content.date_format', 'Y-m-d');
                $output[] = 'Date: '.date($dateFormat, strtotime($post['date']));
            }

            if (config('llms-txt.content.include_author', true) && isset($post['author'])) {
                $output[] = "Author: {$post['author']}";
            }

            $output[] = "Type: {$post['type']}";

            // Add taxonomy information if enabled
            if (config('llms-txt.content.include_taxonomies', true) && ! empty($post['taxonomies'])) {
                foreach ($post['taxonomies'] as $taxonomy) {
                    $output[] = "{$taxonomy['name']}: ".implode(', ', $taxonomy['terms']);
                }
            }

            // Add featured image if enabled and available
            if (config('llms-txt.content.include_featured_image', false) && isset($post['featured_image'])) {
                $output[] = "Featured Image: {$post['featured_image']}";
            }

            // Add WooCommerce product data if available
            if (! empty($post['sku'])) {
                $output[] = "SKU: {$post['sku']}";
            }

            if (! empty($post['price'])) {
                $output[] = "Price: {$post['price']}";
            }

            if (! empty($post['stock_status'])) {
                $output[] = "Stock Status: {$post['stock_status']}";
            }

            if (! empty($post['product_type'])) {
                $output[] = "Product Type: {$post['product_type']}";
            }

            $output[] = '';

            // Convert content to markdown
            $content = $this->markdownConverter->convert($post['content']);
            $output[] = $content;
            $output[] = '';
        });

        $content = implode("\n", $output);

        // Allow filtering of final content before serving
        return apply_filters('acorn/llms_txt/content', $content);
    }

    public function formatSmall(Collection $posts): string
    {
        $output = [];

        // Site header
        $siteName = get_bloginfo('name');
        $output[] = "# {$siteName} (Excerpts)";
        $output[] = '';

        // Add each post with excerpt/summary only
        $posts->each(function ($post) use (&$output) {
            $metadata = $this->formatMetadata($post);
            $title = $post['title'].$metadata;

            $output[] = "## {$title}";
            $output[] = "URL: {$post['url']}";

            // Add optional metadata based on configuration
            if (config('llms-txt.content.include_date', true) && isset($post['date'])) {
                $dateFormat = config('llms-txt.content.date_format', 'Y-m-d');
                $output[] = 'Date: '.date($dateFormat, strtotime($post['date']));
            }

            if (config('llms-txt.content.include_author', true) && isset($post['author'])) {
                $output[] = "Author: {$post['author']}";
            }

            $output[] = '';

            // Use excerpt instead of full content
            $output[] = $post['excerpt'];
            $output[] = '';
        });

        $content = implode("\n", $output);

        // Allow filtering of final content before serving
        return apply_filters('acorn/llms_txt/content', $content);
    }

    public function formatSitemap(Collection $posts): string
    {
        $output = [];
        $output[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $output[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Add main llms.txt files
        $baseUrl = home_url();
        $lastmod = date('Y-m-d\TH:i:s+00:00');

        $output[] = '    <url>';
        $output[] = "        <loc>{$baseUrl}/llms.txt</loc>";
        $output[] = "        <lastmod>{$lastmod}</lastmod>";
        $output[] = '        <changefreq>daily</changefreq>';
        $output[] = '        <priority>1.0</priority>';
        $output[] = '    </url>';

        $output[] = '    <url>';
        $output[] = "        <loc>{$baseUrl}/llms-full.txt</loc>";
        $output[] = "        <lastmod>{$lastmod}</lastmod>";
        $output[] = '        <changefreq>daily</changefreq>';
        $output[] = '        <priority>0.8</priority>';
        $output[] = '    </url>';

        $output[] = '    <url>';
        $output[] = "        <loc>{$baseUrl}/llms-small.txt</loc>";
        $output[] = "        <lastmod>{$lastmod}</lastmod>";
        $output[] = '        <changefreq>daily</changefreq>';
        $output[] = '        <priority>0.6</priority>';
        $output[] = '    </url>';

        // Add individual posts if enabled
        if (config('llms-txt.individual_posts.enabled', false)) {
            $posts->each(function ($post) use (&$output, $baseUrl) {
                $postType = get_post_type($post['id']);
                if (!in_array($postType, config('llms-txt.individual_posts.post_types'))) {
                    return;
                }
                $slug = get_post_field('post_name', $post['id']);
                if (! empty($slug)) {
                    $lastmod = date('Y-m-d\TH:i:s+00:00', strtotime($post['date'] ?? 'now'));

                    $output[] = '    <url>';
                    $output[] = "        <loc>{$baseUrl}/{$postType}-{$slug}.txt</loc>";
                    $output[] = "        <lastmod>{$lastmod}</lastmod>";
                    $output[] = '        <changefreq>weekly</changefreq>';
                    $output[] = '        <priority>0.4</priority>';
                    $output[] = '    </url>';
                }
            })->filter();
        }

        $output[] = '</urlset>';

        $content = implode("\n", $output);

        // Allow filtering of sitemap content before serving
        return apply_filters('acorn/llms_txt/sitemap', $content);
    }

    protected function formatMetadata(array $post): string
    {
        $metadata = " ({$post['type']}";

        // Only include taxonomies if enabled in configuration
        if (config('llms-txt.content.include_taxonomies', true) && ! empty($post['taxonomies'])) {
            $taxonomyStrings = [];
            foreach ($post['taxonomies'] as $taxonomy) {
                $taxonomyStrings[] = $taxonomy['name'].': '.implode(', ', $taxonomy['terms']);
            }
            if (! empty($taxonomyStrings)) {
                $metadata .= ' | '.implode(' | ', $taxonomyStrings);
            }
        }

        // Add WooCommerce product data if available
        if (! empty($post['sku'])) {
            $metadata .= ' | SKU: '.$post['sku'];
        }

        if (! empty($post['price'])) {
            $metadata .= ' | Price: '.$post['price'];
        }

        $metadata .= ')';

        return $metadata;
    }

    protected function addHierarchicalPages(Section $section, $pages): void
    {
        // Build a tree structure from the flat page collection
        $pageTree = $this->buildPageTree($pages);

        // Add pages to section with hierarchy
        $this->addPagesToSection($section, $pageTree, 0);
    }

    protected function buildPageTree($pages): array
    {
        $tree = [];
        $indexed = [];

        // Index all pages by ID for quick lookup
        foreach ($pages as $page) {
            $indexed[$page['id']] = $page;
            $indexed[$page['id']]['children'] = [];
        }

        // Build the tree structure
        foreach ($pages as $page) {
            if ($page['parent_id'] == 0) {
                $tree[] = &$indexed[$page['id']];

                continue;
            }

            // Child page - add to parent's children array
            if (isset($indexed[$page['parent_id']])) {
                $indexed[$page['parent_id']]['children'][] = &$indexed[$page['id']];

                continue;
            }

            // Parent not found, treat as root level
            $tree[] = &$indexed[$page['id']];
        }

        return $tree;
    }

    protected function addPagesToSection(Section $section, array $pages, int $depth): void
    {
        foreach ($pages as $page) {
            $indent = str_repeat('  ', $depth);
            $title = $indent.$page['title'];

            $link = (new Link)
                ->title($title)
                ->url($page['url'])
                ->details($page['excerpt']);

            $section->addLink($link);

            // Recursively add children
            if (! empty($page['children'])) {
                $this->addPagesToSection($section, $page['children'], $depth + 1);
            }
        }
    }

    protected function getConfiguredPostTypesDescription(): string
    {
        $postTypes = config('llms-txt.post_types', ['post', 'page']);

        $labels = collect($postTypes)->map(function ($postType) {
            $object = get_post_type_object($postType);

            return $object ? strtolower($object->labels->name) : $postType;
        });

        return match ($labels->count()) {
            0 => 'content',
            1 => $labels->first(),
            2 => $labels->join(' and '),
            default => $labels->slice(0, -1)->join(', ').', and '.$labels->last()
        };
    }
}
