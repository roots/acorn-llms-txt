<?php

/**
 * Simple test runner for WP-CLI
 * Run with: wp eval-file tests/wp-cli-test.php
 */
if (! defined('WP_CLI') || ! WP_CLI) {
    exit('This script can only be run via WP-CLI');
}

class LlmsTxtTestRunner
{
    private $passed = 0;

    private $failed = 0;

    private $errors = [];

    public function run()
    {
        WP_CLI::line('Running LLMs.txt Package Tests...');
        WP_CLI::line('');

        $this->testContentFetcher();
        $this->testContentFormatter();
        $this->testSeoFilter();
        $this->testDataObjects();
        $this->testMarkdownConverter();
        $this->testCacheInvalidator();
        $this->testEndpoints();
        $this->testWordPressIntegration();
        $this->testConfigurationOptions();
        $this->testWooCommerceIntegration();
        $this->testSeoPluginIntegration();

        $this->printResults();
    }

    private function testContentFetcher()
    {
        WP_CLI::line('Testing ContentFetcher...');

        try {
            $fetcher = new \Roots\AcornLlmsTxt\ContentFetcher(['post', 'page']);
            $this->assert($fetcher instanceof \Roots\AcornLlmsTxt\ContentFetcher, 'ContentFetcher can be instantiated');
        } catch (Exception $e) {
            $this->fail('ContentFetcher instantiation', $e->getMessage());
        }

        // Test with real WordPress data
        try {
            $posts = get_posts(['numberposts' => 1, 'post_type' => 'post']);
            if (! empty($posts)) {
                $fetcher = new \Roots\AcornLlmsTxt\ContentFetcher(['post']);
                $collection = $fetcher->getPosts(1);
                $this->assert($collection instanceof \Illuminate\Support\Collection, 'getPosts returns Collection');
                $this->assert($collection->count() <= 1, 'getPosts respects limit');
            } else {
                WP_CLI::line('  Skipped: No posts found for testing');
            }
        } catch (Exception $e) {
            $this->fail('ContentFetcher getPosts', $e->getMessage());
        }
    }

    private function testSeoFilter()
    {
        WP_CLI::line('Testing SeoFilter...');

        try {
            $filter = new \Roots\AcornLlmsTxt\Services\SeoFilter;
            $this->assert($filter instanceof \Roots\AcornLlmsTxt\Services\SeoFilter, 'SeoFilter can be instantiated');
        } catch (Exception $e) {
            $this->fail('SeoFilter instantiation', $e->getMessage());
        }

        // Test with real post if available
        try {
            $posts = get_posts(['numberposts' => 1, 'post_type' => 'post']);
            if (! empty($posts)) {
                $filter = new \Roots\AcornLlmsTxt\Services\SeoFilter;
                $result = $filter->shouldExcludePost($posts[0]->ID);
                $this->assert(is_bool($result), 'shouldExcludePost returns boolean');
            } else {
                WP_CLI::line('  Skipped: No posts found for testing');
            }
        } catch (Exception $e) {
            $this->fail('SeoFilter shouldExcludePost', $e->getMessage());
        }
    }

    private function testDataObjects()
    {
        WP_CLI::line('Testing Data Objects...');

        try {
            $document = new \Roots\AcornLlmsTxt\Data\LlmsTxtDocument;
            $this->assert($document instanceof \Roots\AcornLlmsTxt\Data\LlmsTxtDocument, 'LlmsTxtDocument can be instantiated');

            $document->title('Test Title')->description('Test Description');
            $this->assert($document->toString() !== '', 'LlmsTxtDocument generates output');
        } catch (Exception $e) {
            $this->fail('LlmsTxtDocument', $e->getMessage());
        }

        try {
            $section = new \Roots\AcornLlmsTxt\Data\Section;
            $link = new \Roots\AcornLlmsTxt\Data\Link;

            $this->assert($section instanceof \Roots\AcornLlmsTxt\Data\Section, 'Section can be instantiated');
            $this->assert($link instanceof \Roots\AcornLlmsTxt\Data\Link, 'Link can be instantiated');
        } catch (Exception $e) {
            $this->fail('Section/Link objects', $e->getMessage());
        }
    }

    private function testEndpoints()
    {
        WP_CLI::line('Testing Endpoints...');

        // Test controller directly instead of HTTP request
        try {
            $controller = new \Roots\AcornLlmsTxt\Http\Controllers\LlmsTxtController(
                new \Roots\AcornLlmsTxt\ContentFetcher(['post', 'page']),
                new \Roots\AcornLlmsTxt\ContentFormatter(
                    new \Roots\AcornLlmsTxt\Services\MarkdownConverter
                )
            );

            $response = $controller->index();
            $this->assert($response instanceof \Illuminate\Http\Response, 'Controller returns Response object');
            $this->assert($response->getStatusCode() === 200, 'Controller returns 200 status');

            $content = $response->getContent();
            $this->assert(! empty($content), 'Controller returns content');
            $this->assert(strpos($content, get_bloginfo('name')) !== false, 'Content contains site name');
            $this->assert(strpos($content, 'llms-full.txt') !== false, 'Content contains full content link');

        } catch (Exception $e) {
            $this->fail('Controller test', $e->getMessage());
        }

        // Test other endpoints
        try {
            $response = $controller->full();
            $this->assert($response instanceof \Illuminate\Http\Response, 'Full endpoint returns Response');

            $response = $controller->small();
            $this->assert($response instanceof \Illuminate\Http\Response, 'Small endpoint returns Response');

            $response = $controller->sitemap();
            $this->assert($response instanceof \Illuminate\Http\Response, 'Sitemap endpoint returns Response');
            $this->assert($response->headers->get('Content-Type') === 'application/xml; charset=utf-8', 'Sitemap has correct content type');

        } catch (Exception $e) {
            $this->fail('Additional endpoints', $e->getMessage());
        }
    }

    private function testContentFormatter()
    {
        WP_CLI::line('Testing ContentFormatter...');

        try {
            $formatter = new \Roots\AcornLlmsTxt\ContentFormatter(
                new \Roots\AcornLlmsTxt\Services\MarkdownConverter
            );
            $this->assert($formatter instanceof \Roots\AcornLlmsTxt\ContentFormatter, 'ContentFormatter can be instantiated');

            // Test with sample data
            $posts = collect([
                [
                    'id' => 1,
                    'title' => 'Test Post',
                    'url' => 'https://test.local/test-post/',
                    'excerpt' => 'Test excerpt',
                    'content' => 'Test content',
                    'type' => 'Post',
                    'parent_id' => 0,
                ],
            ]);

            $document = $formatter->formatListing($posts);
            $this->assert($document instanceof \Roots\AcornLlmsTxt\Data\LlmsTxtDocument, 'formatListing returns LlmsTxtDocument');

            $output = $document->toString();
            $this->assert(strpos($output, get_bloginfo('name')) !== false, 'formatListing includes site name');
            $this->assert(strpos($output, 'Test Post') !== false, 'formatListing includes post title');

            $fullOutput = $formatter->formatFull($posts);
            $this->assert(! empty($fullOutput), 'formatFull returns content');
            $this->assert(strpos($fullOutput, 'Test Post') !== false, 'formatFull includes post title');

            $smallOutput = $formatter->formatSmall($posts);
            $this->assert(! empty($smallOutput), 'formatSmall returns content');
            $this->assert(strpos($smallOutput, 'Excerpts') !== false, 'formatSmall includes excerpts header');

        } catch (Exception $e) {
            $this->fail('ContentFormatter', $e->getMessage());
        }
    }

    private function testMarkdownConverter()
    {
        WP_CLI::line('Testing MarkdownConverter...');

        try {
            $converter = new \Roots\AcornLlmsTxt\Services\MarkdownConverter;
            $this->assert($converter instanceof \Roots\AcornLlmsTxt\Services\MarkdownConverter, 'MarkdownConverter can be instantiated');

            $html = '<h2>Test Heading</h2><p>Test paragraph with <strong>bold</strong> text.</p>';
            $markdown = $converter->convert($html);

            $this->assert(! empty($markdown), 'convert returns content');
            $this->assert(strpos($markdown, '## Test Heading') !== false, 'converts headings correctly');
            $this->assert(strpos($markdown, '**bold**') !== false, 'converts bold text correctly');

        } catch (Exception $e) {
            $this->fail('MarkdownConverter', $e->getMessage());
        }
    }

    private function testCacheInvalidator()
    {
        WP_CLI::line('Testing CacheInvalidator...');

        try {
            $invalidator = new \Roots\AcornLlmsTxt\Services\CacheInvalidator;
            $this->assert($invalidator instanceof \Roots\AcornLlmsTxt\Services\CacheInvalidator, 'CacheInvalidator can be instantiated');

            // Test cache invalidation doesn't throw errors
            $invalidator->invalidateCache();
            $this->assert(true, 'invalidateCache executes without error');

            $invalidator->clearAll();
            $this->assert(true, 'clearAll executes without error');

        } catch (Exception $e) {
            $this->fail('CacheInvalidator', $e->getMessage());
        }
    }

    private function testWordPressIntegration()
    {
        WP_CLI::line('Testing WordPress Integration...');

        try {
            // Test with actual WordPress hooks
            $hooksBefore = $GLOBALS['wp_filter'] ?? [];

            // Re-register hooks by creating new CacheInvalidator
            new \Roots\AcornLlmsTxt\Services\CacheInvalidator;

            $hooksAfter = $GLOBALS['wp_filter'] ?? [];
            $this->assert(count($hooksAfter) >= count($hooksBefore), 'WordPress hooks are registered');

            // Test post type validation
            $fetcher = new \Roots\AcornLlmsTxt\ContentFetcher(['post', 'invalid_type', 'page']);
            $posts = $fetcher->getPosts(1);
            $this->assert($posts instanceof \Illuminate\Support\Collection, 'Invalid post types handled gracefully');

        } catch (Exception $e) {
            $this->fail('WordPress Integration', $e->getMessage());
        }
    }

    private function testConfigurationOptions()
    {
        WP_CLI::line('Testing Configuration Options...');

        try {
            // Test different configurations don't break functionality
            $originalConfig = config('llms-txt');

            // Test with minimal config
            $fetcher = new \Roots\AcornLlmsTxt\ContentFetcher(['post']);
            $posts = $fetcher->getPosts(1);
            $this->assert($posts instanceof \Illuminate\Support\Collection, 'Works with minimal config');

            // Test excerpt length configuration
            if (! empty($posts)) {
                $post = (object) [
                    'ID' => 1,
                    'post_title' => 'Test',
                    'post_content' => 'This is a very long post content that should be trimmed according to the configured excerpt length setting.',
                    'post_excerpt' => '',
                ];

                $reflection = new ReflectionClass($fetcher);
                $method = $reflection->getMethod('getExcerpt');
                $method->setAccessible(true);
                $excerpt = $method->invoke($fetcher, $post);

                $this->assert(! empty($excerpt), 'Excerpt generation works');
                $this->assert(str_word_count($excerpt) <= 25, 'Excerpt respects length limits');
            }

        } catch (Exception $e) {
            $this->fail('Configuration Options', $e->getMessage());
        }
    }

    private function testWooCommerceIntegration()
    {
        WP_CLI::line('Testing WooCommerce Integration...');

        try {
            $fetcher = new \Roots\AcornLlmsTxt\ContentFetcher(['product']);

            // Test WooCommerce detection doesn't break when WooCommerce isn't active
            $posts = $fetcher->getPosts(1);
            $this->assert($posts instanceof \Illuminate\Support\Collection, 'WooCommerce integration graceful when plugin inactive');

            // Test WooCommerce data extraction with mock product
            if (class_exists('WooCommerce') && function_exists('wc_get_product')) {
                WP_CLI::line('  WooCommerce is active - testing product integration');

                $products = get_posts(['post_type' => 'product', 'numberposts' => 1]);
                if (! empty($products)) {
                    $productPosts = $fetcher->getPosts(1);
                    $this->assert($productPosts->count() <= 1, 'Product posts retrieved successfully');

                    if (! $productPosts->isEmpty()) {
                        $productData = $productPosts->first();
                        $this->assert(isset($productData['id']), 'Product data contains required fields');
                    }
                } else {
                    WP_CLI::line('  Skipped: No products found for testing');
                }
            } else {
                WP_CLI::line('  Skipped: WooCommerce not active');
            }

        } catch (Exception $e) {
            $this->fail('WooCommerce Integration', $e->getMessage());
        }
    }

    private function testSeoPluginIntegration()
    {
        WP_CLI::line('Testing SEO Plugin Integration...');

        try {
            $filter = new \Roots\AcornLlmsTxt\Services\SeoFilter;

            // Test with posts that have various meta configurations
            $posts = get_posts(['numberposts' => 3, 'post_type' => 'post']);

            foreach ($posts as $post) {
                $excluded = $filter->shouldExcludePost($post->ID);
                $this->assert(is_bool($excluded), "shouldExcludePost returns boolean for post {$post->ID}");
            }

            // Test custom noindex meta keys
            if (! empty($posts)) {
                $testPost = $posts[0];

                // Test doesn't throw errors with various meta configurations
                update_post_meta($testPost->ID, '_test_noindex', '1');
                $excluded = $filter->shouldExcludePost($testPost->ID);
                $this->assert(is_bool($excluded), 'Custom meta keys handled gracefully');

                // Clean up
                delete_post_meta($testPost->ID, '_test_noindex');
            }

            // Test SEO plugin detection methods don't error
            $reflection = new ReflectionClass($filter);

            $yoastMethod = $reflection->getMethod('isYoastActive');
            $yoastMethod->setAccessible(true);
            $yoastActive = $yoastMethod->invoke($filter);
            $this->assert(is_bool($yoastActive), 'Yoast detection returns boolean');

            $rankMathMethod = $reflection->getMethod('isRankMathActive');
            $rankMathMethod->setAccessible(true);
            $rankMathActive = $rankMathMethod->invoke($filter);
            $this->assert(is_bool($rankMathActive), 'RankMath detection returns boolean');

            $seoFrameworkMethod = $reflection->getMethod('isSeoFrameworkActive');
            $seoFrameworkMethod->setAccessible(true);
            $seoFrameworkActive = $seoFrameworkMethod->invoke($filter);
            $this->assert(is_bool($seoFrameworkActive), 'SEO Framework detection returns boolean');

            if ($yoastActive) {
                WP_CLI::line('  ✓ Yoast SEO detected');
            }
            if ($rankMathActive) {
                WP_CLI::line('  ✓ RankMath detected');
            }
            if ($seoFrameworkActive) {
                WP_CLI::line('  ✓ The SEO Framework detected');
            }

        } catch (Exception $e) {
            $this->fail('SEO Plugin Integration', $e->getMessage());
        }
    }

    private function assert($condition, $message)
    {
        if ($condition) {
            $this->passed++;
            WP_CLI::line('  ✓ '.$message);
        } else {
            $this->failed++;
            $this->errors[] = $message;
            WP_CLI::line('  ✗ '.$message);
        }
    }

    private function fail($test, $error)
    {
        $this->failed++;
        $this->errors[] = "$test: $error";
        WP_CLI::line("  ✗ $test: $error");
    }

    private function printResults()
    {
        WP_CLI::line('');
        WP_CLI::line('Results:');
        WP_CLI::line("  Passed: {$this->passed}");
        WP_CLI::line("  Failed: {$this->failed}");

        if ($this->failed > 0) {
            WP_CLI::line('');
            WP_CLI::line('Failures:');
            foreach ($this->errors as $error) {
                WP_CLI::line("  - $error");
            }
        }

        if ($this->failed === 0) {
            WP_CLI::success('All tests passed!');
        } else {
            WP_CLI::error("$this->failed tests failed!");
        }
    }
}

$runner = new LlmsTxtTestRunner;
$runner->run();
