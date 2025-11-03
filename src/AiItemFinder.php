<?php

namespace AmiPraha\AiItemFinder;

use Exception;
use Illuminate\Support\Facades\Http;

class AiItemFinder
{
    protected string $model = 'gpt-4.1-mini';
    protected ?string $apiKey = null;
    protected string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    protected array $list = [];
    protected ?string $descriptionOfList = null; // short description of the list, used for the system message to yield more accurate results
    protected ?string $searchedItemKey = null;
    protected mixed $searchedItemValue = null;
    protected ?string $additionalInstructions = null;
    protected ?string $systemMessage = null;
    protected bool $allowNoResult = true;
    protected int $noResultConfidenceThreshold = 80; // limit under which the result is marked as not relevant (0-100), default 80%

    protected ?string $confidenceReasoning = null; // reasoning behind the returned confidence score for the match
    protected ?int $confidenceScore = null; // confidence score for the match (0-100)

    public function __construct()
    {
        $this->apiKey = config('ai-item-finder.openai_api_key');
        $this->model = config('ai-item-finder.model', 'gpt-4.1-mini');
        $this->apiUrl = config('ai-item-finder.api_url', 'https://api.openai.com/v1/chat/completions');

        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key is missing. Please set it in config/ai-item-finder.php or OPENAI_API_KEY environment variable.');
        }
    }

    /**
     * Set list of items to search through
     */
    public function setList(array $list): self
    {
        $this->list = $list;

        return $this;
    }

    /**
     * Set the description of the list (used for the system message to yield more accurate results)
     */
    public function setDescriptionOfList(string $descriptionOfList): self
    {
        $this->descriptionOfList = $descriptionOfList;

        return $this;
    }

    /**
     * Set the item to search for in the list
     *
     * @param string $searchedItemKey The key name for the searched item
     * @param mixed $searchedItemValue The value to find a match for
     */
    public function setSearchedItem(string $searchedItemKey, mixed $searchedItemValue): self
    {
        $this->searchedItemKey = $searchedItemKey;
        $this->searchedItemValue = $searchedItemValue;

        return $this;
    }

    /**
     * Set additional instructions for the assistant
     */
    public function setAdditionalInstructions(string $instructions): self
    {
        $this->additionalInstructions = $instructions;

        return $this;
    }

    /**
     * Override the system message entirely
     */
    public function setSystemMessage(string $systemMessage): self
    {
        $this->systemMessage = $systemMessage;

        return $this;
    }

    /**
     * Set whether to allow no result (null) to be returned if there is no close match.
     *
     * Relevant to setNoResultConfidenceThreshold(): this flag determines whether the threshold set by setNoResultConfidenceThreshold()
     * will be respected, i.e., if closes item is below this threshold, null will be returned.
     */
    public function setAllowNoResult(bool $allowNoResult = true): self
    {
        $this->allowNoResult = $allowNoResult;

        return $this;
    }

    /**
     * Set the no result threshold under which the result is marked as not relevant if allowNoResult flag is set to true
     * 
     * This threshold is irrelevant if allowNoResult flag is set to false.
     *
     * @param int $noResultConfidenceThreshold The confidence threshold (0-100), default 80%
     */
    public function setNoResultConfidenceThreshold(int $noResultConfidenceThreshold): self
    {
        if ($noResultConfidenceThreshold < 0 || $noResultConfidenceThreshold > 100) {
            throw new Exception('No result confidence threshold must be between 0 and 100. Given value: ' . $noResultConfidenceThreshold . '.');
        }

        $this->noResultConfidenceThreshold = $noResultConfidenceThreshold;

        return $this;
    }

    /**
     * Set the OpenAI model to use
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get the confidence score of the last match
     *
     * @return int|null The confidence score (0-100), or null if no match has been performed yet
     */
    public function getConfidenceScore(): ?int
    {
        return $this->confidenceScore;
    }

    /**
     * Get the reasoning behind the confidence score of the last match
     *
     * @return string|null The reasoning, or null if no match has been performed yet
     */
    public function getConfidenceReasoning(): ?string
    {
        return $this->confidenceReasoning;
    }

    /**
     * Find the closest matching item from the list
     *
     * @return array|null The closest matching item from the list or null if allowNoResult flag is set to true and no close match above the threshold is found
     * @throws Exception
     */
    public function find(): ?array
    {
        $this->validateInputData();

        $config = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSystemMessage(),
                ],
                [
                    'role' => 'user',
                    'content' => $this->getUserMessage(),
                ],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        $result = $this->getResultFromOpenAIChatCompletion($config);

        $this->validatePickedItem($result);

        $matchedItem = collect($this->list)
            ->firstWhere($this->searchedItemKey, $result[$this->searchedItemKey]);

        if ($this->allowNoResult) {
            $matchEvaluation = $this->getConfidenceScoreAndReasoningForMatch($matchedItem);

            $this->confidenceReasoning = $matchEvaluation['reasoning'];
            $this->confidenceScore = $matchEvaluation['confidence_score'];

            if ($matchEvaluation['confidence_score'] < $this->noResultConfidenceThreshold) {
                return null;
            }
        }

        return $matchedItem;
    }

    /**
     * Return the confidence score and reasoning for the match
     *
     * @param array $matchedItem The matched item
     * @return array<string, int|string> The confidence score and reasoning
     * 
     * @throws Exception
     */
    protected function getConfidenceScoreAndReasoningForMatch(array $matchedItem): array
    {
        $systemMessage = 'You are a helpful assistant that evaluates the quality of a match between a searched item and a matched item from a list. Provide a confidence score from 1 to 100, where 100 means you are extremely confident this is an excellent match (the items are virtually identical or perfectly align with provided instructions below), and 1 means this is an extremely weak match (minimal or no meaningful correspondence). Use the full range: assign scores close to 100 for strong matches, mid-range scores (40-60) for uncertain or partial matches, and scores close to 1 for very poor matches. '
            . PHP_EOL . 'List items are described as follows: "' . $this->descriptionOfList . '".';

        if (!empty($this->systemMessage)) {
            $systemMessage .= PHP_EOL . 'Important instructions used for the matching process: "' . $this->systemMessage . '".';
        }

        if (!empty($this->additionalInstructions)) {
            $systemMessage .= PHP_EOL . 'Additional instructions used for the matching process: "' . $this->additionalInstructions . '".';
        }

        $config = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemMessage,
                ],
                [
                    'role' => 'user',
                    'content' => 'Searched item: ' . json_encode(
                        [$this->searchedItemKey => $this->searchedItemValue],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ) . PHP_EOL . 'Matched item: ' . json_encode(
                        $matchedItem,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'similarity_score_response',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'confidence_score' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 100
                            ],
                            'reasoning' => [
                                'type' => 'string',
                                'description' => 'The reasoning behind the similarity score',
                                'maxLength' => 300,
                                'minLength' => 50,
                            ]
                        ],
                        'required' => ['confidence_score', 'reasoning'],
                        'additionalProperties' => false
                    ]
                ]
            ],
        ];

        $result = $this->getResultFromOpenAIChatCompletion($config);

        return [
            'confidence_score' => $result['confidence_score'],
            'reasoning' => $result['reasoning'],
        ];
    }

    /**
     * Validate the picked item
     *
     * @param array $pickedItem The picked item
     * @throws Exception
     */
    protected function validatePickedItem(array $pickedItem): void
    {
        if (!is_array($pickedItem)) {
            throw new Exception('Picked item is not a valid array. Please report this to the package author.');
        }

        if (!array_key_exists($this->searchedItemKey, $pickedItem)) {
            throw new Exception('Picked item does not contain the searched item key. Please report this to the package author.');
        }

        if (!collect($this->list)->contains(fn($item) => $item[$this->searchedItemKey] === $pickedItem[$this->searchedItemKey])) {
            throw new Exception('Picked item is not in the provided list. Please report this to the package author.');
        }
    }

    /**
     * Make a request to the OpenAI API
     *
     * @param array $config The request configuration
     * @return array The response data
     * @throws Exception
     */
    protected function getResultFromOpenAIChatCompletion(array $config): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->post($this->apiUrl, $config);

        $data = $response->json();

        if ($response->failed()) {
            $errorMessage = $data['error']['message'] ?? 'Unable to retrieve error details, maybe the API changed and request handling needs to be updated.';

            throw new Exception("Error during OpenAI API request: $errorMessage");
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (empty($content)) {
            throw new Exception('Empty response from OpenAI API.');
        }

        return json_decode($content, true);
    }

    /**
     * Validate that all required input data are present
     *
     * @throws Exception
     */
    protected function validateInputData(): void
    {
        if (count($this->list) === 0) {
            throw new Exception("List is not set! Use setList() method.");
        }

        if (empty($this->searchedItemKey)) {
            throw new Exception("Searched item is not set! Use setSearchedItem() method.");
        }
    }

    /**
     * Construct the system message
     */
    protected function getSystemMessage(): string
    {
        if (!empty($this->systemMessage)) {
            $systemMessage = $this->systemMessage;
        } else {
            $systemMessage = 'You are helpful assistant who picks the most similar item from a provided json list to a provided json list item. Choose only from the provided list, never invent any non existing item.';
        }

        if (!empty($this->descriptionOfList)) {
            $systemMessage .= PHP_EOL . 'List items are described as follows: "' . $this->descriptionOfList . '".';
        }

        if (!empty($this->additionalInstructions)) {
            $systemMessage .= PHP_EOL . 'Additional instructions: "' . $this->additionalInstructions . '".';
        }

        return $systemMessage;
    }

    /**
     * Construct the user message with the searched item
     */
    protected function getUserMessage(): string
    {
        return 'List: ' . json_encode($this->list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . PHP_EOL
            . 'List item: ' . json_encode(
                [$this->searchedItemKey => $this->searchedItemValue],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
    }
}
