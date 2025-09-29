<?php

namespace App\Services;

use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Illuminate\Support\Facades\Log;
use Exception;

class OcrService
{
    private $client;

    public function __construct()
    {
        try {
            $this->client = new ImageAnnotatorClient([
                'credentials' => [
                    'type' => 'service_account',
                    'project_id' => config('ai.google_vision.project_id'),
                    'private_key_id' => env('GOOGLE_VISION_PRIVATE_KEY_ID'),
                    'private_key' => str_replace('\\n', "\n", env('GOOGLE_VISION_PRIVATE_KEY')),
                    'client_email' => env('GOOGLE_VISION_CLIENT_EMAIL'),
                    'client_id' => env('GOOGLE_VISION_CLIENT_ID'),
                    'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                    'token_uri' => 'https://oauth2.googleapis.com/token',
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error initializing Google Vision client: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract text from an image using Google Cloud Vision
     *
     * @param string $imagePath Path to the image file
     * @return array
     */
    public function extractTextFromImage(string $imagePath): array
    {
        try {
            if (!file_exists($imagePath)) {
                throw new Exception("Image file not found: {$imagePath}");
            }

            // Read the image file
            $imageContent = file_get_contents($imagePath);
            
            // Create Image object
            $image = new Image();
            $image->setContent($imageContent);

            // Configure the feature to detect text
            $feature = new Feature();
            $feature->setType(Type::TEXT_DETECTION);

            // Create the request
            $request = new AnnotateImageRequest();
            $request->setImage($image);
            $request->setFeatures([$feature]);

            // Perform the request
            $response = $this->client->batchAnnotateImages([$request]);
            $annotations = $response->getResponses()[0];

            if ($annotations->hasError()) {
                throw new Exception('Vision API error: ' . $annotations->getError()->getMessage());
            }

            $textAnnotations = $annotations->getTextAnnotations();
            
            if (count($textAnnotations) === 0) {
                return [
                    'success' => false,
                    'message' => 'No text found in the image',
                    'data' => null
                ];
            }

            // The first annotation contains the full text
            $fullText = $textAnnotations[0]->getDescription();

            return [
                'success' => true,
                'message' => 'Text extracted successfully',
                'data' => [
                    'full_text' => $fullText,
                    'confidence' => $this->calculateAverageConfidence($textAnnotations),
                    'word_count' => str_word_count($fullText),
                    'detected_language' => $this->detectLanguage($fullText)
                ]
            ];

        } catch (Exception $e) {
            Log::error('OCR Service Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error processing image: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Extract structured data from document images (like certificates)
     *
     * @param string $imagePath Path to the image file
     * @return array
     */
    public function extractDocumentData(string $imagePath): array
    {
        try {
            // First extract all text
            $textResult = $this->extractTextFromImage($imagePath);
            
            if (!$textResult['success']) {
                return $textResult;
            }

            $fullText = $textResult['data']['full_text'];

            // Use document text detection for better structure
            $imageContent = file_get_contents($imagePath);
            $image = new Image();
            $image->setContent($imageContent);

            $feature = new Feature();
            $feature->setType(Type::DOCUMENT_TEXT_DETECTION);

            $request = new AnnotateImageRequest();
            $request->setImage($image);
            $request->setFeatures([$feature]);

            $response = $this->client->batchAnnotateImages([$request]);
            $annotations = $response->getResponses()[0];

            if ($annotations->hasError()) {
                throw new Exception('Document Vision API error: ' . $annotations->getError()->getMessage());
            }

            $documentText = $annotations->getFullTextAnnotation();
            
            if (!$documentText) {
                return $textResult; // Fall back to basic text detection
            }

            return [
                'success' => true,
                'message' => 'Document data extracted successfully',
                'data' => [
                    'full_text' => $documentText->getText(),
                    'pages' => $documentText->getPages()->count(),
                    'blocks' => $this->extractBlocks($documentText),
                    'confidence' => $textResult['data']['confidence'],
                    'word_count' => str_word_count($documentText->getText()),
                    'detected_language' => $this->detectLanguage($documentText->getText())
                ]
            ];

        } catch (Exception $e) {
            Log::error('Document OCR Service Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error processing document: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Calculate average confidence from text annotations
     *
     * @param array $textAnnotations
     * @return float
     */
    private function calculateAverageConfidence($textAnnotations): float
    {
        if (count($textAnnotations) <= 1) {
            return 0.0;
        }

        $totalConfidence = 0;
        $count = 0;

        // Skip the first annotation (full text) and calculate for individual words
        for ($i = 1; $i < count($textAnnotations); $i++) {
            $confidence = $textAnnotations[$i]->getConfidence();
            if ($confidence > 0) {
                $totalConfidence += $confidence;
                $count++;
            }
        }

        return $count > 0 ? round($totalConfidence / $count, 2) : 0.0;
    }

    /**
     * Extract text blocks from document
     *
     * @param mixed $documentText
     * @return array
     */
    private function extractBlocks($documentText): array
    {
        $blocks = [];
        
        foreach ($documentText->getPages() as $page) {
            foreach ($page->getBlocks() as $block) {
                $blockText = '';
                foreach ($block->getParagraphs() as $paragraph) {
                    foreach ($paragraph->getWords() as $word) {
                        foreach ($word->getSymbols() as $symbol) {
                            $blockText .= $symbol->getText();
                        }
                        $blockText .= ' ';
                    }
                    $blockText .= "\n";
                }
                
                if (trim($blockText)) {
                    $blocks[] = [
                        'text' => trim($blockText),
                        'confidence' => $block->getConfidence()
                    ];
                }
            }
        }

        return $blocks;
    }

    /**
     * Simple language detection based on common patterns
     *
     * @param string $text
     * @return string
     */
    private function detectLanguage(string $text): string
    {
        // Simple heuristic for Spanish/English detection
        $spanishWords = ['de', 'la', 'el', 'en', 'que', 'y', 'por', 'con', 'para', 'certificado', 'nombre'];
        $englishWords = ['the', 'and', 'of', 'to', 'in', 'certificate', 'name', 'date', 'issued'];

        $words = str_word_count(strtolower($text), 1);
        $spanishCount = count(array_intersect($words, $spanishWords));
        $englishCount = count(array_intersect($words, $englishWords));

        if ($spanishCount > $englishCount) {
            return 'es';
        } elseif ($englishCount > $spanishCount) {
            return 'en';
        }

        return 'unknown';
    }

    /**
     * Clean up resources
     */
    public function __destruct()
    {
        if ($this->client) {
            $this->client->close();
        }
    }
}