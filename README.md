# AI Closest Item Finder

A Laravel package that uses Large Language Models (LLM) to find the closest matching item from a list. This package leverages OpenAI's GPT models to perform intelligent matching based on semantic similarity rather than simple string matching.

## Features

- Find the most similar item from a list using AI
- Configurable OpenAI models (GPT-4.1, GPT-5, etc.)
- Easy-to-use fluent interface
- Laravel facade support
- Customizable system instructions

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
    ->setAdditionalInstructions('Prioritize matching by brand over model type')
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

### `setAdditionalInstructions(string $instructions)`
Add additional instructions to guide the AI's matching behavior.

### `setSystemMessage(string $message)`
Override the default system message entirely.

### `setModel(string $model)`
Set the OpenAI model to use (e.g., 'gpt-4.1', 'gpt-4.1-mini', 'gpt-5').

### `find()`
Execute the search and return the closest matching item.

## Error Handling

The package throws exceptions in the following cases:

- Missing OpenAI API key
- Empty list
- Missing searched item
- API errors

```php
use AmiPraha\AiItemFinder\Facades\AiItemFinder;

try {
    $result = AiItemFinder::setList($list)
        ->setSearchedItem('name', 'search term')
        ->find();
} catch (\Exception $e) {
    // Handle error
    Log::error('Closest item search failed: ' . $e->getMessage());
}
```

## Use Cases

- Matching user input to predefined options
- Finding similar products in a catalog
- Mapping inconsistent data to standardized values
- Intelligent autocomplete and suggestions
- Data normalization and deduplication

## License

MIT License

## Author

AMI Praha a.s.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
