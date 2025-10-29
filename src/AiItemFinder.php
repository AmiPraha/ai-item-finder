<?php

namespace AmiPraha\AiItemFinder;

use Exception;
use Illuminate\Support\Facades\Http;

class AiItemFinder
{
    protected array $list = [];
    protected ?string $searchedItemKey = null;
    protected mixed $searchedItemValue = null;
    protected ?string $additionalInstructions = null;
    protected ?string $systemMessage = null;
    protected string $model = 'gpt-4.1-mini';
    protected ?string $apiKey = null;
    protected string $apiUrl = 'https://api.openai.com/v1/chat/completions';

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
     * Set the OpenAI model to use
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Find the closest matching item from the list
     *
     * @return array The closest matching item from the list
     * @throws Exception
     */
    public function find(): array
    {
        $this->validate();

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
     * Validate that all required data is set
     *
     * @throws Exception
     */
    protected function validate(): void
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
            return $this->systemMessage;
        }

        $message = 'You are helpful assistant who picks the most similar item from a json list below to a provided json list item.';

        if (!empty($this->additionalInstructions)) {
            $message .= PHP_EOL . $this->additionalInstructions;
        }

        $message .= PHP_EOL . 'List: ' . json_encode($this->list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $message;
    }

    /**
     * Construct the user message with the searched item
     */
    protected function getUserMessage(): string
    {
        return 'List item: ' . json_encode(
            [$this->searchedItemKey => $this->searchedItemValue],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
