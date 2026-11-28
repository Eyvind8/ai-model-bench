<?php

/*
|--------------------------------------------------------------------------
| Scenarios
|--------------------------------------------------------------------------
|
| A scenario = prompt + expected response format + auto metrics.
| Adding a scenario = adding an entry here and running `bench:scenarios:sync`.
|
| Keys:
|   name
|   system_prompt
|   user_template    placeholders: {{input}}, {{title}}, {{url}}, {{meta.<key>}}
|   response_format  text | json_object | json_schema
|   json_schema      JSON Schema (array) for json_schema format and validation
|   params           temperature, max_tokens, top_p
|   metrics          result metrics: label => ['metric' => <bench.metrics key>, ...options]
|   judge_rubric     criteria for LLM-as-judge (stage 4)
|
*/

return [

    'digest-article' => [
        'name' => 'Digest: article score + announcement',
        'system_prompt' => <<<'PROMPT'
            You are an editor of a news digest. Read the article and:
            1. Rate how important it is for the digest on a scale from 1 to 10
               (1 - noise, 5 - ordinary news, 10 - must be in the digest).
            2. Write a short announcement of the article, at most 200 characters,
               in the same language as the article, without clickbait.
            Answer strictly with JSON: {"score": <integer 1-10>, "announcement": "<text>"}.
            PROMPT,
        'user_template' => "Title: {{title}}\n\n{{input}}",
        'response_format' => 'json_schema',
        'json_schema' => [
            'type' => 'object',
            'required' => ['score', 'announcement'],
            'properties' => [
                'score' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                'announcement' => ['type' => 'string', 'maxLength' => 200],
            ],
            'additionalProperties' => false,
        ],
        'params' => ['temperature' => 0.2, 'max_tokens' => 300],
        'metrics' => [
            'announcement_length_ok' => ['metric' => 'max_length', 'field' => 'announcement', 'max' => 200],
            'score_abs_error' => ['metric' => 'score_error', 'field' => 'score'],
            'language_match' => ['metric' => 'language_match', 'field' => 'announcement'],
        ],
        'judge_rubric' => 'accuracy (no facts missing from the article), informativeness, announcement style, adequacy of the score',
    ],

];
