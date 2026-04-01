<?php

/**
 * SCRIPT DE PRUEBA: AWS TEXTRACT PARA CERTIFICADOS COLOMBIANOS
 * 
 * Este script prueba la extracción de datos de documentos colombianos
 * usando AWS Textract en modo simulado (mientras configuras las credenciales reales)
 */

// Cargar Laravel
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap/app.php';

use App\Services\UnifiedOcrService;
use App\Services\CertificateProcessingService;

// Función para probar extracción de diferentes tipos de documentos
function testColombianDocuments()
{
    echo "🇨🇴 PROBANDO EXTRACCIÓN DE DOCUMENTOS COLOMBIANOS\n";
    echo "=" . str_repeat("=", 50) . "\n";

    $ocrService = new UnifiedOcrService();
    
    // Simular diferentes tipos de documentos
    $documentTypes = [
        'rut' => 'Registro Único Tributario',
        'cedula' => 'Cédula de Ciudadanía', 
        'chamber_commerce' => 'Cámara de Comercio'
    ];

    foreach ($documentTypes as $type => $description) {
        echo "\n📄 Procesando: {$description}\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        // Simular path de archivo (el servicio genera datos mock)
        $mockFilePath = storage_path("app/test-documents/{$type}_ejemplo.jpg");
        
        try {
            // Probar extracción específica para documentos colombianos
            $result = $ocrService->extractColombianDocument($mockFilePath, $type);
            
            if ($result['success']) {
                echo "✅ Extracción exitosa\n";
                echo "📊 Confianza: {$result['data']['confidence']}%\n";
                echo "⏱️  Tiempo: {$result['data']['extraction_time']}s\n";
                echo "🔧 Servicio: {$result['data']['service']}\n";
                
                // Mostrar campos específicos extraídos
                if (isset($result['data']['colombian_fields'])) {
                    echo "\n🎯 Campos extraídos:\n";
                    $fields = $result['data']['colombian_fields'];
                    
                    foreach ($fields as $fieldName => $fieldValue) {
                        if (is_array($fieldValue)) {
                            echo "   {$fieldName}: {$fieldValue['value']} (confianza: {$fieldValue['confidence']}%)\n";
                        } else {
                            echo "   {$fieldName}: {$fieldValue}\n";
                        }
                    }
                }
                
                // Mostrar texto extraído (primeras 200 caracteres)
                $text = substr($result['data']['text'], 0, 200);
                echo "\n📝 Texto extraído (muestra):\n   " . str_replace("\n", "\n   ", $text) . "...\n";
                
            } else {
                echo "❌ Error: {$result['error']}\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Excepción: {$e->getMessage()}\n";
        }
        
        echo "\n";
    }
}

// Función para probar el procesamiento completo de certificados
function testCertificateProcessing()
{
    echo "\n🏆 PROBANDO PROCESAMIENTO COMPLETO DE CERTIFICADOS\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    try {
        $processingService = new CertificateProcessingService();
        
        // Obtener estadísticas actuales
        echo "📊 Estadísticas actuales:\n";
        $stats = $processingService->getStatistics();
        
        if ($stats['success']) {
            $data = $stats['data'];
            echo "   Certificados totales: {$data['total_certificates']}\n";
            echo "   Certificados analizados: {$data['analyzed_certificates']}\n";
            echo "   Eficiencia: {$data['processing_efficiency']}%\n";
            echo "   Tasa de éxito: {$data['success_rate']}%\n";
        }
        
        echo "\n🚀 Sistema listo para procesar certificados con AWS Textract!\n";
        
    } catch (Exception $e) {
        echo "❌ Error en procesamiento: {$e->getMessage()}\n";
    }
}

// Función principal
function main()
{
    echo "🎯 PRUEBA DE AWS TEXTRACT PARA CERTIFICATE MANAGER\n";
    echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
    echo "=" . str_repeat("=", 60) . "\n";
    
    // Verificar estado de servicios
    echo "\n🔍 Estado de servicios:\n";
    $ocrService = new UnifiedOcrService();
    $status = $ocrService->getServicesStatus();
    
    foreach ($status as $key => $value) {
        $icon = $value ? '✅' : '❌';
        echo "   {$key}: {$icon}\n";
    }
    
    // Ejecutar pruebas
    testColombianDocuments();
    testCertificateProcessing();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 PRUEBA COMPLETADA\n";
    echo "\n💡 PRÓXIMOS PASOS:\n";
    echo "1. Configurar credenciales AWS reales en .env\n";
    echo "2. Subir documentos reales a storage/app/test-documents/\n";
    echo "3. Ejecutar: php artisan tinker < test_aws_textract.php\n";
    echo "4. Integrar en tu flujo de trabajo\n";
}

// Ejecutar si se llama directamente
if (php_sapi_name() === 'cli') {
    try {
        main();
    } catch (Exception $e) {
        echo "❌ Error fatal: {$e->getMessage()}\n";
        echo "📍 Archivo: {$e->getFile()}:{$e->getLine()}\n";
    }
}