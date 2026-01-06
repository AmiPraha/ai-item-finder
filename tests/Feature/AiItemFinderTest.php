<?php

namespace AmiPraha\AiItemFinder\Tests\Feature;

use AmiPraha\AiItemFinder\AiItemFinder;
use AmiPraha\AiItemFinder\Exceptions\InvalidApiResponseException;
use AmiPraha\AiItemFinder\Exceptions\InvalidConfigurationException;
use AmiPraha\AiItemFinder\Exceptions\InvalidInputException;
use AmiPraha\AiItemFinder\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class AiItemFinderTest extends TestCase
{
    /** @test */
    public function it_can_find_matching_item_from_list(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['name' => 'Apple iPhone 14'])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $list = [
            ['name' => 'Apple iPhone 14'],
            ['name' => 'Samsung Galaxy S23'],
            ['name' => 'Google Pixel 7'],
        ];

        $finder = new AiItemFinder();
        $result = $finder->setList($list)
            ->setSearchedItem('name', 'iPhone 14 Pro')
            ->setAllowNoResult(false)
            ->find();

        $this->assertNotNull($result);
        $this->assertEquals('Apple iPhone 14', $result['name']);
    }

    /** @test */
    public function it_can_use_facade(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['city' => 'Praha'])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $list = [
            ['city' => 'Praha'],
            ['city' => 'Brno'],
            ['city' => 'Ostrava'],
        ];

        $result = \AmiPraha\AiItemFinder\Facades\AiItemFinder::setList($list)
            ->setSearchedItem('city', 'Prague')
            ->setAllowNoResult(false)
            ->find();

        $this->assertNotNull($result);
        $this->assertEquals('Praha', $result['city']);
    }

    /** @test */
    public function it_can_set_custom_model(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['name' => 'Product A'])
                            ]
                        ]
                    ]
                ], 200)
        ]);

        $list = [
            ['name' => 'Product A'],
            ['name' => 'Product B'],
        ];

        $finder = new AiItemFinder();
        $result = $finder->setList($list)
            ->setSearchedItem('name', 'A')
            ->setModel('gpt-4o')
            ->setAllowNoResult(false)
            ->find();

        Http::assertSent(fn($request) => 
            $request->hasHeader('Authorization', 'Bearer test-api-key') &&
            $request['model'] === 'gpt-4o'
        );

        $this->assertNotNull($result);
    }

    /** @test */
    public function it_can_set_additional_instructions(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['model' => 'Tesla Model 3'])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $list = [
            ['model' => 'Tesla Model 3', 'type' => 'electric'],
            ['model' => 'BMW X5', 'type' => 'suv'],
        ];

        $finder = new AiItemFinder();
        $result = $finder->setList($list)
            ->setSearchedItem('model', 'Model S')
            ->setAdditionalInstructions('Match based on brand is sufficient.')
            ->setAllowNoResult(false)
            ->find();

        Http::assertSent(fn($request) => 
            str_contains($request['messages'][0]['content'], 'Match based on brand is sufficient.')
        );

        $this->assertNotNull($result);
    }

    /** @test */
    public function it_can_set_list_description(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['name' => 'New York City'])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $list = [
            ['code' => 'NYC', 'name' => 'New York City'],
            ['code' => 'LAX', 'name' => 'Los Angeles'],
        ];

        $finder = new AiItemFinder();
        $result = $finder->setList($list)
            ->setDescriptionOfList('Airport codes and their corresponding cities')
            ->setSearchedItem('name', 'New York')
            ->setAllowNoResult(false)
            ->find();

        Http::assertSent(fn($request) => 
            str_contains($request['messages'][0]['content'], 'Airport codes and their corresponding cities')
        );

        $this->assertNotNull($result);
    }

    /** @test */
    public function it_can_set_custom_system_message(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['name' => 'Item A'])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $list = [
            ['name' => 'Item A'],
            ['name' => 'Item B'],
        ];

        $finder = new AiItemFinder();
        $result = $finder->setList($list)
            ->setSearchedItem('name', 'A')
            ->setCustomSystemMessage('Custom system message')
            ->setAllowNoResult(false)
            ->find();

        Http::assertSent(fn($request) => 
            $request['messages'][0]['content'] === 'Custom system message'
        );

        $this->assertNotNull($result);
    }

    /** @test */
    public function it_throws_exception_when_api_key_is_missing(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('OpenAI API key is missing');

        config(['ai-item-finder.openai_api_key' => null]);

        new AiItemFinder();
    }

    /** @test */
    public function it_throws_exception_when_list_is_empty(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('List is not set');

        $finder = new AiItemFinder();
        $finder->setSearchedItem('name', 'test')->find();
    }

    /** @test */
    public function it_throws_exception_when_searched_item_is_not_set(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Searched item is not set');

        $finder = new AiItemFinder();
        $finder->setList([['name' => 'test']])->find();
    }

    /** @test */
    public function it_throws_exception_for_invalid_confidence_threshold(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('No result confidence threshold must be between 0 and 100');

        $finder = new AiItemFinder();
        $finder->setNoResultConfidenceThreshold(150);
    }

    /** @test */
    public function it_throws_exception_for_negative_confidence_threshold(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('No result confidence threshold must be between 0 and 100');

        $finder = new AiItemFinder();
        $finder->setNoResultConfidenceThreshold(-10);
    }

    /** @test */
    public function it_throws_exception_when_api_returns_error(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid API key'
                ]
            ], 401)
        ]);

        $this->expectException(InvalidApiResponseException::class);
        $this->expectExceptionMessage('Error during OpenAI API request: Invalid API key');

        $finder = new AiItemFinder();
        $finder->setList([['name' => 'test']])
            ->setSearchedItem('name', 'test')
            ->find();
    }

    /** @test */
    public function it_throws_exception_when_api_returns_empty_content(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => ''
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->expectException(InvalidApiResponseException::class);
        $this->expectExceptionMessage('Empty response from OpenAI API');

        $finder = new AiItemFinder();
        $finder->setList([['name' => 'test']])
            ->setSearchedItem('name', 'test')
            ->find();
    }

    /** @test */
    public function it_throws_exception_when_picked_item_not_in_list(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['name' => 'Non-existent Item'])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->expectException(InvalidApiResponseException::class);
        $this->expectExceptionMessage('Picked item is not in the provided list');

        $list = [
            ['name' => 'Item A'],
            ['name' => 'Item B'],
        ];

        $finder = new AiItemFinder();
        $finder->setList($list)
            ->setSearchedItem('name', 'test')
            ->find();
    }

    /** @test */
    public function it_sends_correct_request_format_to_openai(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['name' => 'Test Item'])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $list = [['name' => 'Test Item']];

        $finder = new AiItemFinder();
        $finder->setList($list)
            ->setSearchedItem('name', 'test')
            ->setAllowNoResult(false)
            ->find();

        Http::assertSent(fn($request) => 
            $request->url() === 'https://api.openai.com/v1/chat/completions' &&
            $request->hasHeader('Content-Type', 'application/json') &&
            $request->hasHeader('Authorization', 'Bearer test-api-key') &&
            isset($request['model']) &&
            isset($request['messages']) &&
            isset($request['response_format']) &&
            $request['response_format']['type'] === 'json_schema' &&
            isset($request['response_format']['json_schema'])
        );
    }
}

