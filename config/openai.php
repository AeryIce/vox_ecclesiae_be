<?php

return [
    'enabled' => (bool) env('OPENAI_ENABLED', true),
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),
    'model' => env('OPENAI_MODEL', 'gpt-5'),
    'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 30),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 1200),
    'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'medium'),
];