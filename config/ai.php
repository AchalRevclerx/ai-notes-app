<?php

$driver = env('AI_DRIVER', env('AI_API_KEY') ? 'openai' : 'local');

return [

    /*
    | "openai" talks to any OpenAI-compatible API (OpenAI, Gemini's OpenAI
    | endpoint, Ollama, Groq, ...). "local" is an offline fallback used by
    | the test suite and when no API key is configured.
    */
    'driver' => $driver,

    'api_key' => env('AI_API_KEY'),
    'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
    'chat_model' => env('AI_CHAT_MODEL', 'gpt-4.1-mini'),
    'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),

    // Size of the pgvector column. Changing it requires a fresh migration + reindex.
    'embedding_dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 768),

    'timeout' => (int) env('AI_TIMEOUT', 30),

    // Cosine similarity (0..1) a note must reach to be returned by search.
    // Lexical local vectors score lower than real embeddings, hence the split default.
    'search_min_similarity' => (float) env('AI_SEARCH_MIN_SIMILARITY', $driver === 'local' ? 0.1 : 0.3),

];
