<?php

namespace AmiPraha\AiItemFinder\Tests\Feature;

use AmiPraha\AiItemFinder\AiItemFinder;
use AmiPraha\AiItemFinder\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ConfidenceDataAccessTest extends TestCase
{
    /** @test */
    public function it_can_get_confidence_score_after_finding_match(): void
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
                                    'confidence_score' => 85,
                                    'reasoning' => 'This is a strong match because the items are very similar with high correspondence in their key attributes and overall characteristics.'
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
            ->find();

        $this->assertNotNull($result);
        $this->assertEquals(85, $finder->getConfidenceScore());
        $this->assertIsString($finder->getConfidenceReasoning());
        $this->assertStringContainsString('strong match', $finder->getConfidenceReasoning());
    }

    /** @test */
    public function it_returns_null_for_confidence_data_before_finding(): void
    {
        $finder = new AiItemFinder();

        $this->assertNull($finder->getConfidenceScore());
        $this->assertNull($finder->getConfidenceReasoning());
    }

    /** @test */
    public function it_does_not_populate_confidence_data_when_allow_no_result_is_false(): void
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
            ->setSearchedItem('name', 'test')
            ->setAllowNoResult(false) // Don't evaluate confidence
            ->find();

        $this->assertNotNull($result);
        // Confidence data should not be populated when allowNoResult is false
        $this->assertNull($finder->getConfidenceScore());
        $this->assertNull($finder->getConfidenceReasoning());
    }

    /** @test */
    public function it_can_access_confidence_data_via_facade(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['name' => 'Product X'])
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'confidence_score' => 92,
                                    'reasoning' => 'This is an excellent match demonstrating very high similarity and strong alignment between the searched and matched items with minimal differences.'
                                ])
                            ]
                        ]
                    ]
                ], 200)
        ]);

        $list = [
            ['name' => 'Product X'],
            ['name' => 'Product Y'],
        ];

        $facade = \AmiPraha\AiItemFinder\Facades\AiItemFinder::setList($list)
            ->setSearchedItem('name', 'Product X Pro')
            ->setAllowNoResult(true);

        $result = $facade->find();

        $this->assertNotNull($result);
        $this->assertEquals(92, $facade->getConfidenceScore());
        $this->assertStringContainsString('excellent match', $facade->getConfidenceReasoning());
    }
}

