<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OCR Service Configuration
    |--------------------------------------------------------------------------
    |
    | Choose your preferred OCR service: 'textract' or 'vision'
    |
    */
    'ocr_service' => env('OCR_SERVICE', 'textract'),

    /*
    |--------------------------------------------------------------------------
    | AWS Textract Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AWS Textract OCR service
    | Better for forms and structured documents
    |
    */
    'aws_textract' => [
        'region' => env('AWS_TEXTRACT_REGION', 'us-east-1'),
        'version' => 'latest',
    ],

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