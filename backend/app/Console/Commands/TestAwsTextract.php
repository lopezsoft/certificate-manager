<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\UnifiedOcrService;
use App\Services\CertificateProcessingService;
use Exception;

class TestAwsTextract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'textract:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar AWS Textract para certificados colombianos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🎯 PRUEBA DE AWS TEXTRACT PARA CERTIFICATE MANAGER');
        $this->info('Fecha: ' . date('Y-m-d H:i:s'));
        $this->line(str_repeat('=', 60));
        
        // Verificar estado de servicios
        $this->newLine();
        $this->info('🔍 Estado de servicios:');
        
        try {
            $ocrService = new UnifiedOcrService();
            $status = $ocrService->getServicesStatus();
            
            foreach ($status as $key => $value) {
                $icon = $value ? '✅' : '❌';
                $this->line("   {$key}: {$icon}");
            }
            
            // Probar extracción de documentos colombianos
            $this->testColombianDocuments($ocrService);
            
            // Probar procesamiento completo
            $this->testCertificateProcessing();
            
        } catch (Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            $this->error("📍 Archivo: {$e->getFile()}:{$e->getLine()}");
        }
        
        $this->newLine();
        $this->line(str_repeat('=', 60));
        $this->info('🎉 PRUEBA COMPLETADA');
        
        $this->newLine();
        $this->comment('💡 PRÓXIMOS PASOS:');
        $this->line('1. Configurar credenciales AWS reales en .env');
        $this->line('2. Subir documentos reales a storage/app/test-documents/');
        $this->line('3. Integrar en tu flujo de trabajo');
    }
    
    private function testColombianDocuments($ocrService)
    {
        $this->newLine();
        $this->info('🇨🇴 PROBANDO EXTRACCIÓN DE DOCUMENTOS COLOMBIANOS');
        $this->line(str_repeat('=', 50));

        $documentTypes = [
            'rut' => 'Registro Único Tributario',
            'cedula' => 'Cédula de Ciudadanía', 
            'chamber_commerce' => 'Cámara de Comercio'
        ];

        foreach ($documentTypes as $type => $description) {
            $this->newLine();
            $this->info("📄 Procesando: {$description}");
            $this->line(str_repeat('-', 40));
            
            $mockFilePath = storage_path("app/test-documents/{$type}_ejemplo.jpg");
            
            try {
                $result = $ocrService->extractColombianDocument($mockFilePath, $type);
                
                if ($result['success']) {
                    $this->info("✅ Extracción exitosa");
                    $this->line("📊 Confianza: {$result['data']['confidence']}%");
                    $this->line("⏱️  Tiempo: {$result['data']['extraction_time']}s");
                    $this->line("🔧 Servicio: {$result['data']['service']}");
                    
                    if (isset($result['data']['colombian_fields'])) {
                        $this->newLine();
                        $this->info('🎯 Campos extraídos:');
                        $fields = $result['data']['colombian_fields'];
                        
                        foreach ($fields as $fieldName => $fieldValue) {
                            if (is_array($fieldValue)) {
                                $this->line("   {$fieldName}: {$fieldValue['value']} (confianza: {$fieldValue['confidence']}%)");
                            } else {
                                $this->line("   {$fieldName}: {$fieldValue}");
                            }
                        }
                    }
                    
                    $text = substr($result['data']['text'], 0, 200);
                    $this->newLine();
                    $this->info('📝 Texto extraído (muestra):');
                    $this->line('   ' . str_replace("\n", "\n   ", $text) . '...');
                    
                } else {
                    $this->error("❌ Error: {$result['error']}");
                }
                
            } catch (Exception $e) {
                $this->error("❌ Excepción: {$e->getMessage()}");
            }
        }
    }
    
    private function testCertificateProcessing()
    {
        $this->newLine();
        $this->info('🏆 PROBANDO PROCESAMIENTO COMPLETO DE CERTIFICADOS');
        $this->line(str_repeat('=', 50));
        
        try {
            $processingService = new CertificateProcessingService();
            
            $this->info('📊 Estadísticas actuales:');
            $stats = $processingService->getStatistics();
            
            if ($stats['success']) {
                $data = $stats['data'];
                $this->line("   Certificados totales: {$data['total_certificates']}");
                $this->line("   Certificados analizados: {$data['analyzed_certificates']}");
                $this->line("   Eficiencia: {$data['processing_efficiency']}%");
                $this->line("   Tasa de éxito: {$data['success_rate']}%");
            }
            
            $this->newLine();
            $this->info('🚀 Sistema listo para procesar certificados con AWS Textract!');
            
        } catch (Exception $e) {
            $this->error("❌ Error en procesamiento: {$e->getMessage()}");
        }
    }
}
