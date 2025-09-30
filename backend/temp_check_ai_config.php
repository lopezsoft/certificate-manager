<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OcrService;
use App\Services\AiContentService;
use Exception;

/**
 * COMANDO TEMPORAL PARA VERIFICAR CONFIGURACIÓN DE IA
 * 
 * Uso: php artisan ai:check-config
 * 
 * Este comando verifica que las claves de IA estén configuradas correctamente.
 * Elimínalo después de configurar las APIs.
 */
class CheckAiConfigCommand extends Command
{
    protected $signature = 'ai:check-config';
    protected $description = 'Verificar configuración de APIs de IA';

    public function handle()
    {
        $this->info('🔍 Verificando configuración de APIs de IA...');
        $this->newLine();

        // Verificar variables de entorno
        $this->checkEnvironmentVariables();
        
        // Verificar servicios
        $this->checkServices();
        
        $this->newLine();
        $this->info('✅ Verificación completada');
    }

    private function checkEnvironmentVariables()
    {
        $this->info('📋 Variables de entorno:');
        
        $vars = [
            'GOOGLE_CLOUD_PROJECT_ID' => config('ai.google_vision.project_id'),
            'GOOGLE_APPLICATION_CREDENTIALS' => env('GOOGLE_APPLICATION_CREDENTIALS'),
            'GEMINI_API_KEY' => config('ai.gemini.api_key'),
            'GEMINI_MODEL' => config('ai.gemini.model'),
        ];

        foreach ($vars as $name => $value) {
            if (empty($value)) {
                $this->error("  ❌ {$name}: No configurada");
            } else {
                $masked = $this->maskSensitiveValue($name, $value);
                $this->info("  ✅ {$name}: {$masked}");
            }
        }
    }

    private function checkServices()
    {
        $this->newLine();
        $this->info('⚙️ Servicios:');

        // Verificar OCR Service
        try {
            $ocrService = app(OcrService::class);
            $this->info('  ✅ OcrService: Inicializado correctamente');
        } catch (Exception $e) {
            $this->error('  ❌ OcrService: ' . $e->getMessage());
        }

        // Verificar AI Service
        try {
            $aiService = app(AiContentService::class);
            $this->info('  ✅ AiContentService: Inicializado correctamente');
        } catch (Exception $e) {
            $this->error('  ❌ AiContentService: ' . $e->getMessage());
        }

        // Verificar archivo de credenciales
        $credentialsPath = env('GOOGLE_APPLICATION_CREDENTIALS');
        if ($credentialsPath && file_exists($credentialsPath)) {
            $this->info('  ✅ Archivo de credenciales: Encontrado');
        } elseif ($credentialsPath) {
            $this->error('  ❌ Archivo de credenciales: No encontrado en ' . $credentialsPath);
        } else {
            $this->warn('  ⚠️  Archivo de credenciales: No configurado (modo API key)');
        }
    }

    private function maskSensitiveValue($name, $value)
    {
        if (strpos($name, 'API_KEY') !== false) {
            return substr($value, 0, 8) . '...' . substr($value, -4);
        }
        
        if (strpos($name, 'CREDENTIALS') !== false) {
            return basename($value);
        }
        
        return $value;
    }
}