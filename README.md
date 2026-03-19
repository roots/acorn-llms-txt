# Acorn LLMs.txt

<a href="https://packagist.org/packages/roots/acorn-llms-txt"><img alt="Packagist Downloads" src="https://img.shields.io/packagist/dt/roots/acorn-llms-txt?label=downloads&colorB=2b3072&colorA=525ddc&style=flat-square"></a>
<a href="https://github.com/roots/acorn-llms-txt/actions/workflows/main.yml"><img alt="Build Status" src="https://img.shields.io/github/actions/workflow/status/roots/acorn-llms-txt/main.yml?branch=main&logo=github&label=CI&style=flat-square"></a>
<a href="https://twitter.com/rootswp"><img alt="Follow Roots" src="https://img.shields.io/badge/follow%20@rootswp-1da1f2?logo=twitter&logoColor=ffffff&message=&style=flat-square"></a>
<a href="https://github.com/sponsors/roots"><img src="https://img.shields.io/badge/sponsor%20roots-525ddc?logo=github&style=flat-square&logoColor=ffffff&message=" alt="Sponsor Roots"></a>

Expose your WordPress site content through `/llms.txt` endpoints following the [llmstxt.org specification](https://llmstxt.org/). This makes your WordPress content easily accessible to Large Language Models (LLMs) in a structured, markdown format.

## Support us

Roots is an independent open source org, supported only by developers like you. Your sponsorship funds [WP Packages](https://wp-packages.org/) and the entire Roots ecosystem, and keeps them independent. Support us by purchasing [Radicle](https://roots.io/radicle/) or [sponsoring us on GitHub](https://github.com/sponsors/roots) — sponsors get access to our private Discord.

## Features

- **llmstxt.org compliant**: Implements the standard specification for LLM-readable content
- **Multiple endpoints**: `/llms.txt` (listing), `/llms-full.txt` (complete content), `/llms-small.txt` (excerpts), individual post endpoints
- **XML sitemap integration**: `/llms-sitemap.xml` with automatic SEO plugin integration (Yoast, RankMath, The SEO Framework, WordPress core)
- **SEO integration**: Respects noindex settings and adds X-Robots-Tag headers to prevent search engine indexing
- **WooCommerce support**: Product SKUs, prices, and metadata automatically included
- **Hierarchical pages**: Displays page hierarchy with proper indentation
- **High performance**: Built-in Laravel Cache integration with automatic invalidation
- **WordPress shortcode processing**: Converts shortcodes to readable content before markdown conversion
- **Extensible**: Developer hooks and filters for customization
- **Configurable**: Comprehensive configuration options for content filtering and formatting

## Installation

Install via Composer:

```bash
composer require roots/acorn-llms-txt
```

Publish the configuration file:

```bash
wp acorn vendor:publish --provider="Roots\AcornLlmsTxt\Providers\LlmsTxtServiceProvider"
```

## Endpoints

### `/llms.txt`
A structured listing of your site content (like a table of contents):

```
# Your Site Name

> Your site tagline or description (if available)

## Additional Resources

- [Full Content](https://yoursite.com/llms-full.txt) - Complete content of all posts and pages in Markdown format

## Recent Posts

- [Hello World (Post | Category: Uncategorized)](https://yoursite.com/hello-world/) - Welcome to WordPress. This is your first post.

## Pages

- [About (Page)](https://yoursite.com/about/) - Information about your site
  - [Contact (Page)](https://yoursite.com/about/contact/) - Get in touch with us
```

### `/llms-full.txt`
Complete content of all posts and pages in markdown format:

```
# Your Site Name

---

## Hello World
URL: https://yoursite.com/hello-world/
Date: 2025-01-01
Author: Admin
Type: Post
Category: Uncategorized

Welcome to WordPress. This is your first post. Edit or delete it, then start writing!
```

### `/llms-small.txt`
Compressed version with excerpts only for faster processing:

```
# Your Site Name (Excerpts)

## Hello World (Post | Category: Uncategorized)
URL: https://yoursite.com/hello-world/
Date: 2025-01-01
Author: Admin

Welcome to WordPress. This is your first post...
```

### `/llms-sitemap.xml`
XML sitemap listing all llms.txt endpoints, automatically integrated with SEO plugins.

### Individual Post Endpoints (Optional)
Access individual posts via `/post-slug.txt` (disabled by default for performance).

## Configuration

After publishing, edit `config/llms-txt.php`:

### Post Types
```php
'post_types' => ['post', 'page'],
```

### Content Processing
```php
'content' => [
    'process_shortcodes' => true, // Process WordPress shortcodes
    'include_featured_image' => false,
    'include_author' => true,
    'include_date' => true,
    'include_taxonomies' => true,
    'excerpt_length' => 20,
    'date_format' => 'Y-m-d',
],
```

### Content Limits
```php
'limits' => [
    'max_posts' => 1000,
    'max_content_length' => 50000,
    'posts_per_section' => 50,
],
```

### Exclusions
```php
'exclude' => [
    'post_ids' => [123, 456], // Specific posts to exclude
    'password_protected' => true,
    'sticky_posts' => false,
],
```

### Individual Post Endpoints
```php
'individual_posts' => [
    'enabled' => false, // Enable /post-slug.txt endpoints
    'post_types' => ['post', 'page'],
    'cache_ttl' => 3600,
],
```

### Caching
```php
'cache_ttl' => 3600, // 1 hour in seconds
```

### WooCommerce Integration
```php
'woocommerce' => [
    'enabled' => true, // Enable WooCommerce integration
    'include_price' => true, // Include product price
    'include_stock_status' => false, // Include stock status
    'include_product_type' => false, // Include product type
],
```

## SEO Integration

The package automatically respects SEO plugin settings and integrates with their sitemaps:

**Content Filtering:**
- **Yoast SEO**: Excludes posts/pages with noindex meta
- **RankMath**: Excludes posts/pages with noindex settings
- **The SEO Framework**: Excludes posts/pages marked as noindex

**Sitemap Integration:**
- **Yoast SEO**: Adds `/llms-sitemap.xml` to main sitemap index
- **RankMath**: Registers llms sitemap in sitemap array
- **The SEO Framework**: Creates custom sitemap endpoint
- **WordPress Core**: Integrates with wp_sitemaps system

**Search Engine Protection:**
- Adds `X-Robots-Tag: noindex` header to all llms.txt endpoints to prevent search engine indexing

Posts marked as noindex by SEO plugins will not appear in any llms.txt endpoints.

## WP-CLI Commands

### View package information
```bash
wp acorn llms-txt
```

### Clear cached content
```bash
wp acorn llms-txt clear-cache
```

## Developer Hooks

### Filters

Filter which post types are included:
```php
add_filter('acorn/llms_txt/post_types', function ($postTypes) {
    return array_merge($postTypes, ['custom_post_type']);
});
```

Exclude specific posts:
```php
add_filter('acorn/llms_txt/exclude_post', function ($exclude, $post) {
    return $post->post_title === 'Secret Post';
}, 10, 2);
```

Filter individual post data:
```php
add_filter('acorn/llms_txt/post_data', function ($data, $post) {
    $data['custom_field'] = get_post_meta($post->ID, 'custom_field', true);
    return $data;
}, 10, 2);
```

Filter the complete posts collection:
```php
add_filter('acorn/llms_txt/posts', function ($posts) {
    return $posts->sortBy('title');
});
```

Filter the entire document before output:
```php
add_filter('acorn/llms_txt/document', function ($document) {
    // Modify the LlmsTxtDocument object
    return $document;
});
```

Filter final content before serving:
```php
add_filter('acorn/llms_txt/content', function ($content) {
    return $content . "\n\n<!-- Generated at " . date('Y-m-d H:i:s') . " -->";
});
```

Filter XML sitemap before serving:
```php
add_filter('acorn/llms_txt/sitemap', function ($sitemap) {
    // Modify the XML sitemap content
    return $sitemap;
});
```

Control sitemap integration:
```php
add_filter('acorn/llms_txt/include_in_sitemaps', function ($include) {
    // Disable sitemap integration conditionally
    return false;
});
```

### Actions

Add custom sections before default content:
```php
add_action('acorn/llms_txt/before_sections', function ($document) {
    $section = (new \Roots\AcornLlmsTxt\Data\Section)
        ->name('Custom Section')
        ->addLink(
            (new \Roots\AcornLlmsTxt\Data\Link)
                ->title('Custom Link')
                ->url('https://example.com')
                ->details('Custom description')
        );

    $document->addSection($section);
});
```

Add custom sections after default content:
```php
add_action('acorn/llms_txt/after_sections', function ($document) {
    // Add sections after default content
});
```

Add completely custom sections:
```php
add_action('acorn/llms_txt/custom_sections', function ($document) {
    // Add your own sections
});
```

## Cache Management

The package automatically invalidates cache when:
- Posts are created, updated, or deleted
- Post status changes
- Post metadata is updated

Manual cache clearing:
```bash
wp acorn llms-txt clear-cache
```

## Performance

This package is optimized for performance with:

- **Efficient caching**: Laravel Cache integration reduces database queries
- **Automatic cache invalidation**: Updates cache only when content changes
- **Memory optimization**: Designed to handle large sites with thousands of posts
- **Configurable limits**: Control content length and post counts to manage resource usage

## Troubleshooting

### Cache Issues
If content isn't updating, clear the cache:
```bash
wp acorn llms-txt clear-cache
```

### Routes Not Working
Ensure Acorn routes are optimized:
```bash
wp acorn optimize
```

### Performance on Large Sites
For sites with many posts, adjust limits in `config/llms-txt.php`:
```php
'limits' => [
    'max_posts' => 500,
    'max_content_length' => 25000,
],
```

## Requirements

- PHP 8.2+
- WordPress with [Acorn](https://roots.io/acorn/)

## Community

Keep track of development and community news.

- Join us on Discord by [sponsoring us on GitHub](https://github.com/sponsors/roots)
- Join us on [Roots Discourse](https://discourse.roots.io/)
- Follow [@rootswp on Twitter](https://twitter.com/rootswp)
- Follow the [Roots Blog](https://roots.io/blog/)
- Subscribe to the [Roots Newsletter](https://roots.io/subscribe/)
