<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Cloud Vision Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for Google Cloud Vision API
    |
    */
    'google_vision' => [
        'api_key' => env('GOOGLE_VISION_API_KEY'),
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for Google Gemini API
    |
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Processing Settings
    |--------------------------------------------------------------------------
    |
    | General settings for AI processing
    |
    */
    'processing' => [
        'max_file_size' => env('AI_MAX_FILE_SIZE', 10485760), // 10MB default
        'supported_formats' => ['jpg', 'jpeg', 'png', 'pdf'],
        'timeout' => env('AI_REQUEST_TIMEOUT', 30), // seconds
    ],
];