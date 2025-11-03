<?php

namespace AmiPraha\AiItemFinder\Tests;

use AmiPraha\AiItemFinder\AiItemFinderServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AiItemFinderServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'AiItemFinder' => \AmiPraha\AiItemFinder\Facades\AiItemFinder::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Set up test configuration
        $app['config']->set('ai-item-finder.openai_api_key', 'test-api-key');
        $app['config']->set('ai-item-finder.model', 'gpt-4o-mini');
        $app['config']->set('ai-item-finder.api_url', 'https://api.openai.com/v1/chat/completions');
    }
}

