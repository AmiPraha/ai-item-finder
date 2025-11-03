<?php

namespace AmiPraha\AiItemFinder;

use AmiPraha\AiItemFinder\Exceptions\InvalidApiResponseException;
use AmiPraha\AiItemFinder\Exceptions\InvalidConfigurationException;
use AmiPraha\AiItemFinder\Exceptions\InvalidInputException;
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

    /**
     * Create a new AiItemFinder instance
     *
     * @throws InvalidConfigurationException If the OpenAI API key is not configured
     */
    public function __construct()
    {
        $this->apiKey = config('ai-item-finder.openai_api_key');
        $this->model = config('ai-item-finder.model', 'gpt-4.1-mini');
        $this->apiUrl = config('ai-item-finder.api_url', 'https://api.openai.com/v1/chat/completions');

        if (empty($this->apiKey)) {
            throw new InvalidConfigurationException('OpenAI API key is missing. Please set it in config/ai-item-finder.php or OPENAI_API_KEY environment variable.');
        }
    }

    /**
     * Set list of items to search through
     *
     * @param array $list Array of items to search through. Each item should be an associative array.
     * @return self Returns the instance for method chaining
     */
    public function setList(array $list): self
    {
        $this->list = $list;

        return $this;
    }

    /**
     * Set the description of the list (used for the system message to yield more accurate results)
     *
     * @param string $descriptionOfList A short description of what the list items represent (e.g., "Airport codes and their corresponding cities")
     * @return self Returns the instance for method chaining
     */
    public function setDescriptionOfList(string $descriptionOfList): self
    {
        $this->descriptionOfList = $descriptionOfList;

        return $this;
    }

    /**
     * Set the item to search for in the list
     *
     * @param string $searchedItemKey The key name for the searched item (e.g., 'name', 'city', 'code')
     * @param int|string|float|bool $searchedItemValue The value to find a match for
     * @return self Returns the instance for method chaining
     */
    public function setSearchedItem(string $searchedItemKey, int|string|float|bool $searchedItemValue): self
    {
        $this->searchedItemKey = $searchedItemKey;
        $this->searchedItemValue = $searchedItemValue;

        return $this;
    }

    /**
     * Set additional instructions for the assistant
     *
     * @param string $instructions Additional instructions to guide the AI's matching behavior (e.g., "Match based on brand is sufficient")
     * @return self Returns the instance for method chaining
     */
    public function setAdditionalInstructions(string $instructions): self
    {
        $this->additionalInstructions = $instructions;

        return $this;
    }

    /**
     * Override the system message entirely
     *
     * @param string $systemMessage Complete custom system message to replace the default one
     * @return self Returns the instance for method chaining
     */
    public function setSystemMessage(string $systemMessage): self
    {
        $this->systemMessage = $systemMessage;

        return $this;
    }

    /**
     * Set whether to allow no result (null) to be returned if there is no close match.
     *
     * When set to true (default), enables confidence scoring (requires 2 API calls).
     * When set to false, always returns the closest match (requires only 1 API call).
     * Relevant to setNoResultConfidenceThreshold(): this flag determines whether the threshold set by setNoResultConfidenceThreshold()
     * will be respected, i.e., if closest item is below this threshold, null will be returned.
     *
     * @param bool $allowNoResult Whether to allow null results when confidence is low (default: true)
     * @return self Returns the instance for method chaining
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
     * If the confidence score is below this threshold, find() will return null instead of the matched item.
     *
     * @param int $noResultConfidenceThreshold The confidence threshold (0-100), default 80%
     * @return self Returns the instance for method chaining
     * @throws InvalidConfigurationException If the threshold is not between 0 and 100
     */
    public function setNoResultConfidenceThreshold(int $noResultConfidenceThreshold): self
    {
        if ($noResultConfidenceThreshold < 0 || $noResultConfidenceThreshold > 100) {
            throw new InvalidConfigurationException('No result confidence threshold must be between 0 and 100. Given value: ' . $noResultConfidenceThreshold . '.');
        }

        $this->noResultConfidenceThreshold = $noResultConfidenceThreshold;

        return $this;
    }

    /**
     * Set the OpenAI model to use
     *
     * @param string $model The OpenAI model name (e.g., 'gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini')
     * @return self Returns the instance for method chaining
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get the confidence score of the last match
     *
     * Returns null if:
     * - No match has been performed yet (find() not called)
     * - Confidence scoring was disabled (setAllowNoResult(false))
     *
     * @return int|null The confidence score (0-100), or null if not available
     */
    public function getConfidenceScore(): ?int
    {
        return $this->confidenceScore;
    }

    /**
     * Get the reasoning behind the confidence score of the last match
     *
     * Returns null if:
     * - No match has been performed yet (find() not called)
     * - Confidence scoring was disabled (setAllowNoResult(false))
     *
     * @return string|null The reasoning text explaining the confidence score, or null if not available
     */
    public function getConfidenceReasoning(): ?string
    {
        return $this->confidenceReasoning;
    }

    /**
     * Find the closest matching item from the list
     *
     * Performs AI-powered matching to find the most similar item from the configured list.
     * When allowNoResult is true, makes 2 API calls (match + confidence evaluation).
     * When allowNoResult is false, makes 1 API call (match only).
     *
     * @return array|null The closest matching item from the list, or null if allowNoResult is true and confidence is below threshold
     * @throws InvalidInputException If list or searched item is not set
     * @throws InvalidApiResponseException If the API request fails or returns invalid data
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
     * Makes a second API call to evaluate the quality of the match between the searched item
     * and the matched item. Uses a structured JSON schema to ensure consistent response format.
     * The confidence score ranges from 1 (extremely weak match) to 100 (excellent match).
     *
     * @param array $matchedItem The matched item from the list to evaluate
     * @return array<string, int|string> Array with keys: 'confidence_score' (int 1-100) and 'reasoning' (string)
     * @throws InvalidApiResponseException If the API request fails or returns invalid data
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
     * Validate the picked item returned by the API
     *
     * Ensures the AI returned a valid item that:
     * - Is a proper array structure
     * - Contains the searched item key
     * - Exists in the original list (prevents AI hallucination)
     *
     * @param array $pickedItem The picked item returned from the API
     * @throws InvalidApiResponseException If validation fails (invalid structure, missing key, or item not in list)
     */
    protected function validatePickedItem(array $pickedItem): void
    {
        if (!is_array($pickedItem)) {
            throw new InvalidApiResponseException('Picked item is not a valid array. Please report this to the package author.');
        }

        if (!array_key_exists($this->searchedItemKey, $pickedItem)) {
            throw new InvalidApiResponseException('Picked item does not contain the searched item key. Please report this to the package author.');
        }

        if (!collect($this->list)->contains(fn($item) => $item[$this->searchedItemKey] === $pickedItem[$this->searchedItemKey])) {
            throw new InvalidApiResponseException('Picked item is not in the provided list. Please report this to the package author.');
        }
    }

    /**
     * Make a request to the OpenAI Chat Completion API
     *
     * Sends a configured request to OpenAI's API and processes the response.
     * Handles error responses, validates the response structure, and extracts
     * the JSON content from the API response.
     *
     * @param array $config The request configuration including model, messages, and response_format
     * @return array The decoded JSON response data from the API
     * @throws InvalidApiResponseException If the API request fails, returns an error, or has invalid/empty content
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

            throw new InvalidApiResponseException("Error during OpenAI API request: $errorMessage");
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (empty($content)) {
            throw new InvalidApiResponseException('Empty response from OpenAI API.');
        }

        return json_decode($content, true);
    }

    /**
     * Validate that all required input data are present
     *
     * Checks that:
     * - The list has been set and is not empty
     * - The searched item key has been configured
     *
     * @throws InvalidInputException If list is empty or searched item is not set
     */
    protected function validateInputData(): void
    {
        if (count($this->list) === 0) {
            throw new InvalidInputException("List is not set! Use setList() method.");
        }

        if (empty($this->searchedItemKey)) {
            throw new InvalidInputException("Searched item is not set! Use setSearchedItem() method.");
        }
    }

    /**
     * Construct the system message for the AI
     *
     * Builds the system message by combining:
     * - Custom system message (if set) or default instruction
     * - List description (if provided)
     * - Additional instructions (if provided)
     *
     * @return string The complete system message for the AI
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
     * Construct the user message with the list and searched item
     *
     * Formats the list and searched item as JSON for the AI to process.
     * Uses JSON_UNESCAPED_UNICODE and JSON_UNESCAPED_SLASHES to ensure
     * proper encoding of international characters and URLs.
     *
     * @return string The formatted user message containing the list and item to match
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
