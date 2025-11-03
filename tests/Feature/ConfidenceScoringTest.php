<?php

namespace AmiPraha\AiItemFinder\Tests\Feature;

use AmiPraha\AiItemFinder\AiItemFinder;
use AmiPraha\AiItemFinder\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ConfidenceScoringTest extends TestCase
{
    /** @test */
    public function it_returns_null_when_confidence_is_below_threshold(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['name' => 'Item A'])
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'confidence_score' => 50,
                                    'reasoning' => 'This is a weak match because the items are only partially similar in some aspects but differ significantly in others.'
                                ])
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
            ->setSearchedItem('name', 'Something completely different')
            ->setAllowNoResult(true)
            ->setNoResultConfidenceThreshold(80)
            ->find();

        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_item_when_confidence_is_above_threshold(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['name' => 'Item A'])
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'confidence_score' => 95,
                                    'reasoning' => 'This is an excellent match because the items are virtually identical with only minor variations that do not affect their core similarity.'
                                ])
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
            ->setSearchedItem('name', 'Item A variant')
            ->setAllowNoResult(true)
            ->setNoResultConfidenceThreshold(80)
            ->find();

        $this->assertNotNull($result);
        $this->assertEquals('Item A', $result['name']);
    }

    /** @test */
    public function it_returns_item_when_allow_no_result_is_false_regardless_of_confidence(): void
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
            ->setSearchedItem('name', 'Something')
            ->setAllowNoResult(false)
            ->find();

        $this->assertNotNull($result);
        $this->assertEquals('Item A', $result['name']);
    }

    /** @test */
    public function it_can_set_custom_confidence_threshold(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['name' => 'Item A'])
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'confidence_score' => 60,
                                    'reasoning' => 'This is a moderate match with some similarities but also notable differences between the searched and matched items.'
                                ])
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
            ->setSearchedItem('name', 'Similar to A')
            ->setAllowNoResult(true)
            ->setNoResultConfidenceThreshold(50) // Lower threshold
            ->find();

        $this->assertNotNull($result);
        $this->assertEquals('Item A', $result['name']);
    }

    /** @test */
    public function it_accepts_confidence_threshold_of_zero(): void
    {
        $finder = new AiItemFinder();
        $result = $finder->setNoResultConfidenceThreshold(0);

        $this->assertInstanceOf(AiItemFinder::class, $result);
    }

    /** @test */
    public function it_accepts_confidence_threshold_of_hundred(): void
    {
        $finder = new AiItemFinder();
        $result = $finder->setNoResultConfidenceThreshold(100);

        $this->assertInstanceOf(AiItemFinder::class, $result);
    }

    /** @test */
    public function it_sends_correct_confidence_evaluation_request(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['city' => 'Praha'])
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'confidence_score' => 90,
                                    'reasoning' => 'The searched term Prague and matched item Praha refer to the same city with excellent correspondence between them.'
                                ])
                            ]
                        ]
                    ]
                ], 200)
        ]);

        $list = [
            ['city' => 'Praha'],
            ['city' => 'Brno'],
        ];

        $finder = new AiItemFinder();
        $finder->setList($list)
            ->setDescriptionOfList('Czech cities')
            ->setSearchedItem('city', 'Prague')
            ->setAllowNoResult(true)
            ->find();

        // Assert that the second request (confidence evaluation) was sent
        Http::assertSentCount(2);
        
        // Check that the second request has the json_schema format
        Http::assertSent(fn($request) => 
            isset($request['response_format']['type']) &&
            $request['response_format']['type'] === 'json_schema' &&
            isset($request['response_format']['json_schema'])
        );
    }
}

