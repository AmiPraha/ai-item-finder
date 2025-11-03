# AI Item Finder

A Laravel package that uses Large Language Models (LLM) to find the closest matching item from a list. This package leverages OpenAI's GPT models to perform intelligent matching based on semantic similarity rather than simple string matching.

## Features

- Find the most similar item from a list using AI
- Configurable OpenAI models (GPT-4.1, GPT-5, etc.)
- Easy-to-use fluent interface
- Laravel facade support
- Customizable system instructions
- Confidence scoring with AI-powered match evaluation
- Optional "no result" mode to return `null` when confidence is below threshold
- List description support for more accurate matching context

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- OpenAI API key

## Installation

Install the package via Composer:

```bash
composer require ami-praha/ai-item-finder
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=ai-item-finder-config
```

Add your OpenAI API key to your `.env` file:

```env
AI_ITEM_FINDER_OPENAI_API_KEY=your-api-key-here
```

Optionally, you can also set the model:

```env
AI_ITEM_FINDER_OPENAI_MODEL=gpt-4.1-mini
```

## Usage

### Basic Usage

```php
use AmiPraha\AiItemFinder\Facades\AiItemFinder;

$list = [
    ['name' => 'Apple iPhone 14'],
    ['name' => 'Samsung Galaxy S23'],
    ['name' => 'Google Pixel 7'],
];

$result = AiItemFinder::setList($list)
    ->setSearchedItem('name', 'iPhone 14 Pro')
    ->find();

// Returns the closest matching item from the list
```

### Using the Service Class Directly

```php
use AmiPraha\AiItemFinder\AiItemFinder;

$finder = new AiItemFinder();

$list = [
    ['city' => 'Praha'],
    ['city' => 'Brno'],
    ['city' => 'Ostrava'],
];

$result = $finder->setList($list)
    ->setSearchedItem('city', 'Prague')
    ->find();

// Will match 'Praha' as it's semantically similar to 'Prague'
```

### Advanced Usage with Custom Instructions

```php
use AmiPraha\AiItemFinder\Facades\AiItemFinder;

$list = [
    ['model' => 'Tesla Model 3', 'type' => 'electric'],
    ['model' => 'BMW X5', 'type' => 'suv'],
    ['model' => 'Toyota Camry', 'type' => 'sedan'],
];

$result = AiItemFinder::setList($list)
    ->setSearchedItem('model', 'Model S')
    ->setAdditionalInstructions('Match based on brand is sufficient, no need to strictly match the model.')
    ->find();

// Will match 'Tesla Model 3' because of brand similarity
```

### Using Different Models

```php
use AmiPraha\AiItemFinder\Facades\AiItemFinder;

$result = AiItemFinder::setList($list)
    ->setSearchedItem('product', 'laptop')
    ->setModel('gpt-5') // Use a more powerful model
    ->find();
```

### Custom System Message

If you need complete control over the AI's behavior, you can set a custom system message:

```php
use AmiPraha\AiItemFinder\Facades\AiItemFinder;

$result = AiItemFinder::setList($list)
    ->setSearchedItem('name', 'search term')
    ->setSystemMessage('You are an expert at matching products. Find the most similar item.')
    ->find();
```

### Using List Description for Better Accuracy

You can provide a description of what the list items represent to help the AI make more accurate matches:

```php
use AmiPraha\AiItemFinder\Facades\AiItemFinder;

$list = [
    ['code' => 'NYC', 'name' => 'New York City'],
    ['code' => 'LAX', 'name' => 'Los Angeles'],
    ['code' => 'ORD', 'name' => 'Chicago'],
];

$result = AiItemFinder::setList($list)
    ->setDescriptionOfList('Airport codes and their corresponding cities')
    ->setSearchedItem('name', 'New York')
    ->find();

// Will match 'NYC' as its New York airport
```

### Handling Low-Confidence Matches

By default, the package evaluates match confidence and returns `null` if the match confidence is below 80%. You can customize this behavior:

```php
use AmiPraha\AiItemFinder\Facades\AiItemFinder;

// Allow low-confidence matches above 50% to be returned
$result = AiItemFinder::setList($list)
    ->setSearchedItem('name', 'search term')
    ->setNoResultConfidenceThreshold(50) // Set threshold to 50%
    ->find();

// $result will be null if confidence score is below 50%
```

```php
// Always return the closest match, even if confidence is low
$result = AiItemFinder::setList($list)
    ->setSearchedItem('name', 'search term')
    ->setAllowNoResult(false)
    ->find();

// $result will always contain the closest match, never null
```

## Configuration

The configuration file `config/ai-item-finder.php` contains the following options:

```php
return [
    // Your OpenAI API key
    'openai_api_key' => env('AI_ITEM_FINDER_OPENAI_API_KEY'),

    // The model to use (default: gpt-4.1-mini)
    'model' => env('AI_ITEM_FINDER_OPENAI_MODEL', 'gpt-4.1-mini'),

    // The API endpoint URL
    'api_url' => env('AI_ITEM_FINDER_OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
];
```

## Available Methods

### `setList(array $list)`
Set the list of items to search through.

### `setSearchedItem(string $key, mixed $value)`
Set the item you're searching for. The key is used for context, and the value is what will be matched.

### `setDescriptionOfList(string $description)`
Set a description of what the list items represent. This provides additional context to the AI for more accurate matching.

### `setAdditionalInstructions(string $instructions)`
Add additional instructions to guide the AI's matching behavior.

### `setSystemMessage(string $message)`
Override the default system message entirely.

### `setAllowNoResult(bool $allowNoResult = true)`
Set whether to allow `null` to be returned if the confidence score is below the threshold. When set to `true` (default), the `find()` method will return `null` if the best match has a confidence score below the threshold set by `setNoResultConfidenceThreshold()`. When set to `false`, the closest match will always be returned regardless of confidence.

### `setNoResultConfidenceThreshold(int $threshold)`
Set the minimum confidence threshold (0-100) required for returning a match. Default is 80%. This threshold only applies when `setAllowNoResult(true)` is set. If the best match has a confidence score below this threshold, `null` will be returned instead.

### `setModel(string $model)`
Set the OpenAI model to use (e.g., 'gpt-4.1', 'gpt-4.1-mini', 'gpt-5').

### `find()`
Execute the search and return the closest matching item. Returns `array|null` - the matched item from the list, or `null` if no sufficiently confident match is found (when `allowNoResult` is `true` and confidence is below the threshold).

## Error Handling

The package throws exceptions in the following cases:

- Missing OpenAI API key
- Empty list
- Missing searched item
- Invalid confidence threshold (not between 0-100)
- API errors
- Invalid API responses

```php
use AmiPraha\AiItemFinder\Facades\AiItemFinder;

try {
    $result = AiItemFinder::setList($list)
        ->setSearchedItem('name', 'search term')
        ->find();
    
    if ($result === null) {
        // No confident match found
        Log::info('No confident match found for the search term');
    } else {
        // Process the matched item
        Log::info('Found match: ' . json_encode($result));
    }
} catch (\Exception $e) {
    // Handle error
    Log::error('Item search failed: ' . $e->getMessage());
}
```

## Use Cases

- Matching user input to predefined options
- Finding similar products in a catalog
- Mapping inconsistent data to standardized values
- Intelligent autocomplete and suggestions
- Data normalization and deduplication
- Fuzzy matching with confidence thresholds for quality control
- Semantic search across categorized data with contextual descriptions

## License

MIT License

## Author

AMI Praha a.s.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
