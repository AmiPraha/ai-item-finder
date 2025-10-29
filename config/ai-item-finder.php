<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | Your OpenAI API key. You can get one at https://platform.openai.com/api-keys
    |
    */
    'openai_api_key' => env('AI_ITEM_FINDER_OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Model
    |--------------------------------------------------------------------------
    |
    | The OpenAI model to use for finding closest items.
    | Default: gpt-4.1-mini (cost-effective and fast)
    | Alternatives: gpt-4.1, gpt-5
    |
    */
    'model' => env('AI_ITEM_FINDER_OPENAI_MODEL', 'gpt-4.1-mini'),

    /*
    |--------------------------------------------------------------------------
    | API URL
    |--------------------------------------------------------------------------
    |
    | The OpenAI API endpoint URL. You typically don't need to change this
    | unless you're using a proxy or alternative OpenAI-compatible service.
    |
    */
    'api_url' => env('AI_ITEM_FINDER_OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
];
