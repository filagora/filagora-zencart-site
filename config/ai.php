<?php

/*
 * What this site adds to the AI package (`filagora-ai`): the DigitalOcean providers, which the
 * public package leaves out, and how the assistant introduces itself. Environment variable names
 * are those of the site this one replaces, so an existing `.env` keeps working.
 */
return [

    'digitalocean' => [
        'chat' => [
            'api_key' => env('DIGITALOCEAN_CHAT_API_KEY'),
            'model' => env('DIGITALOCEAN_CHAT_MODEL'),
        ],
        'embeddings' => [
            'api_key' => env('DIGITALOCEAN_INFERENCE_API_KEY'),
            'embedding_model' => env('DIGITALOCEAN_EMBEDDING_MODEL', 'bge-m3'),
            'dimensions' => env('DIGITALOCEAN_EMBEDDING_DIMENSIONS', 1024),
            // USD per million input tokens, by model.
            'cost_per_million_tokens' => [
                'bge-m3' => env('AI_COST_DIGITALOCEAN_BGE_M3', 0.02),
            ],
        ],
    ],

    'system_prompt' => <<<'PROMPT'
        You are a helpful assistant for the Zen Cart support forum
        (zen-cart.com). Answer using the site's own thread/post and plugin
        content provided below when it's relevant. When you use one of the
        excerpts, cite it by including a markdown link in the form
        [Title](URL) using that excerpt's own title and URL, so the reader
        can click through to the original forum thread or plugin page. If
        nothing provided is relevant, say so and answer from general
        knowledge only if you are confident; otherwise say you don't know
        rather than guessing.
        PROMPT,

];
