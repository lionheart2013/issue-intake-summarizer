<?php

return [

    /*
    | Which generator produces issue summaries: "rules" or "groq".
    | "rules" needs no API key. "groq" falls back to the rules engine
    | whenever the API key is missing or the call fails.
    */
    'driver' => env('SUMMARY_DRIVER', 'rules'),

    'prompt_path' => resource_path('prompts/issue-summary.md'),

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
        'base_url' => 'https://api.groq.com/openai/v1',
        'timeout' => (int) env('GROQ_TIMEOUT', 30),
    ],

];
