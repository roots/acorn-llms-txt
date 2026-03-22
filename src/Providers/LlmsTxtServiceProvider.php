<?php

namespace Roots\AcornLlmsTxt\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Roots\AcornLlmsTxt\Console\LlmsTxtCommand;
use Roots\AcornLlmsTxt\ContentFetcher;
use Roots\AcornLlmsTxt\ContentFormatter;
use Roots\AcornLlmsTxt\Http\Controllers\LlmsTxtController;
use Roots\AcornLlmsTxt\Services\CacheInvalidator;
use Roots\AcornLlmsTxt\Services\MarkdownConverter;
use Roots\AcornLlmsTxt\Services\SeoFilter;
use Roots\AcornLlmsTxt\Services\SitemapIntegration;

class LlmsTxtServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(SeoFilter::class);

        $this->app->singleton(ContentFetcher::class, function ($app) {
            return new ContentFetcher(
                config('llms-txt.post_types', ['post', 'page']),
                $app->make(SeoFilter::class)
            );
        });

        $this->app->singleton(MarkdownConverter::class);

        $this->app->singleton(ContentFormatter::class, function ($app) {
            return new ContentFormatter($app->make(MarkdownConverter::class));
        });

        $this->app->singleton(CacheInvalidator::class);

        $this->mergeConfigFrom(
            __DIR__.'/../../config/llms-txt.php',
            'llms-txt'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/llms-txt.php' => $this->app->configPath('llms-txt.php'),
        ], 'config');

        $this->commands([
            LlmsTxtCommand::class,
        ]);

        // Initialize cache invalidation hooks
        $this->app->make(CacheInvalidator::class);

        // Initialize sitemap integrations
        $this->app->make(SitemapIntegration::class);

        // Register routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        // Conditionally register individual post routes
        $this->registerIndividualPostRoutes();
    }

    /**
     * Register individual post routes only if enabled in configuration.
     */
    protected function registerIndividualPostRoutes(): void
    {
        if (config('llms-txt.individual_posts.enabled', false)) {
            $postTypesPattern = implode('|', config('llms-txt.individual_posts.post_types', ['post', 'page']));
            Route::get('{postType}-{slug}.txt', [LlmsTxtController::class, 'individual'])
                ->where('postType', $postTypesPattern)
                ->where('slug', '.+');
        }
    }
}
