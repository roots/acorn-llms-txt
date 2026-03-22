<?php

namespace Roots\AcornLlmsTxt\Console;

use Roots\Acorn\Console\Commands\Command;
use Roots\AcornLlmsTxt\Services\CacheInvalidator;

class LlmsTxtCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'llms-txt {action=info : Action to perform (info, clear-cache)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'LLMs text processing command for Acorn.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $action = $this->argument('action');

        match ($action) {
            'clear-cache' => $this->clearCache(),
            'info' => $this->showInfo(),
            default => $this->showInfo()
        };
    }

    protected function clearCache(): void
    {
        $cacheInvalidator = app(CacheInvalidator::class);
        $cacheInvalidator->clearAll();

        $this->info('✅ LLMs.txt cache cleared successfully!');
    }

    protected function showInfo(): void
    {
        $this->info('🚀 LLMs.txt Package for Acorn');
        $this->line('');
        $this->line('Available endpoints:');
        $this->line('  • /llms.txt - Table of contents');
        $this->line('  • /llms-full.txt - Complete content');

        if (config('llms-txt.individual_posts.enabled', false)) {
            $postTypesPattern = implode('|', config('llms-txt.individual_posts.post_types', ['post', 'page']));
            $this->line("  • /({$postTypesPattern})-{slug}.txt - Individual post markdown");
        }

        if (! config('llms-txt.individual_posts.enabled', false)) {
            $this->line('  • Individual post .txt endpoints (disabled)');
        }

        $this->line('');
        $this->line('Commands:');
        $this->line('  • wp acorn llms-txt clear-cache - Clear cached content');
        $this->line('');

        if (! config('llms-txt.individual_posts.enabled', false)) {
            $this->line('💡 Tip: Enable individual post endpoints in config/llms-txt.php');
            $this->line('   Set individual_posts.enabled = true');
        }
    }
}
