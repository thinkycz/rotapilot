<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Env;

$env = Env::inject();

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => 'openrouter',
    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => $env->parseNullableString('CACHE_STORE') ?? 'database',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => $env->parseNullableString('ANTHROPIC_API_KEY'),
            'url' => $env->parseNullableString('ANTHROPIC_URL') ?? 'https://api.anthropic.com/v1',
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => $env->parseNullableString('AZURE_OPENAI_API_KEY'),
            'url' => $env->parseNullableString('AZURE_OPENAI_URL'),
            'api_version' => $env->parseNullableString('AZURE_OPENAI_API_VERSION') ?? '2025-04-01-preview',
            'deployment' => $env->parseNullableString('AZURE_OPENAI_DEPLOYMENT') ?? 'gpt-4o',
            'embedding_deployment' => $env->parseNullableString('AZURE_OPENAI_EMBEDDING_DEPLOYMENT') ?? 'text-embedding-3-small',
            'image_deployment' => $env->parseNullableString('AZURE_OPENAI_IMAGE_DEPLOYMENT') ?? 'gpt-image-1',
            'store' => $env->parseNullableBool('AZURE_OPENAI_STORE') ?? true,
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => $env->parseNullableString('AWS_BEDROCK_REGION') ?? 'us-east-1',
            'key' => $env->parseNullableString('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => $env->parseNullableString('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => $env->parseNullableString('AWS_SECRET_ACCESS_KEY'),
            'session_token' => $env->parseNullableString('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => $env->parseNullableBool('AWS_USE_DEFAULT_CREDENTIALS') ?? true,
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => $env->parseNullableString('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => $env->parseNullableString('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => $env->parseNullableString('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => $env->parseNullableString('GEMINI_API_KEY'),
            'url' => $env->parseNullableString('GEMINI_URL') ?? 'https://generativelanguage.googleapis.com/v1beta/',
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => $env->parseNullableString('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => $env->parseNullableString('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => $env->parseNullableString('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => $env->parseNullableString('OLLAMA_API_KEY') ?? '',
            'url' => $env->parseNullableString('OLLAMA_URL') ?? 'http://localhost:11434',
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => $env->parseNullableString('OPENAI_API_KEY'),
            'url' => $env->parseNullableString('OPENAI_URL') ?? 'https://api.openai.com/v1',
            'store' => $env->parseNullableBool('OPENAI_STORE') ?? true,
        ],

        // NOTE: Of the providers declared below, the agent and any
        // production traffic only need 'openrouter' (text completions),
        // 'openai' (fallback / specific tools), and 'gemini' (image
        // generation per 'default_for_images'). The remaining keys
        // (anthropic, azure, bedrock, cohere, deepseek, eleven, groq,
        // jina, mistral, ollama, voyageai, xai) are declared because
        // the laravel/ai SDK ships a built-in driver for each, and
        // removing the block would mean re-registering the driver
        // manually if a future feature wants to use it. Leave them
        // as no-op configurations that resolve to null keys unless
        // the corresponding *_API_KEY env var is set.

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => $env->parseNullableString('OPENROUTER_API_KEY'),
            'models' => [
                'text' => [
                    'default' => $env->parseNullableString('OPENROUTER_MODEL') ?? 'anthropic/claude-sonnet-4.6',
                ],
            ],
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => $env->parseNullableString('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => $env->parseNullableString('XAI_API_KEY'),
        ],
    ],
];
