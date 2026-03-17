<?php

return [

    'api_key'      => env('OPENAI_API_KEY', ''),
    'organization' => env('OPENAI_ORGANIZATION', null),
    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),

    'model'   => env('AI_MODEL', 'gpt-4o-mini'),
    'enabled' => (bool) env('AI_ENABLED', false),

];
