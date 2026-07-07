<?php
/**
 * EJEMPLO DE INTEGRACIÓN AWS TEXTRACT
 * 
 * Este archivo muestra cómo integrar AWS Textract en tu aplicación
 * para procesar certificados colombianos automáticamente
 */

// ============================================
// 1. PROCESAMIENTO INDIVIDUAL DE CERTIFICADO
// ============================================

// En tu controlador o servicio
use App\Jobs\ProcessCertificateJob;
use App\Services\CertificateProcessingService;

class CertificateController extends Controller 
{
    public function processCertificate($certificateId)
    {
        // Opción 1: Procesamiento asíncrono (recomendado)
        ProcessCertificateJob::dispatch($certificateId);
        
        return response()->json([
            'message' => 'Certificado enviado para procesamiento con IA',
            'certificate_id' => $certificateId,
            'status' => 'processing'
        ]);
    }
    
    public function processCertificateSync($certificateId)
    {
        // Opción 2: Procesamiento síncrono (para casos urgentes)
        $processingService = new CertificateProcessingService();
        $result = $processingService->processCertificate($certificateId);
        
        return response()->json($result);
    }
}

// ============================================
// 2. PROCESAMIENTO EN LOTE
// ============================================

class BatchProcessingController extends Controller
{
    public function processBatch()
    {
        // Procesar certificados recientes (últimas 24 horas)
        ProcessCertificateJob::processRecentCertificates();
        
        return response()->json([
            'message' => 'Procesamiento en lote iniciado',
            'status' => 'started'
        ]);
    }
    
    public function processSpecificBatch(Request $request)
    {
        $certificateIds = $request->input('certificate_ids', []);
        
        // Procesar lote específico
        ProcessCertificateJob::processBatchCertificates($certificateIds);
        
        return response()->json([
            'message' => 'Lote específico enviado para procesamiento',
            'certificates' => count($certificateIds),
            'status' => 'processing'
        ]);
    }
}

// ============================================
// 3. CONSULTAR RESULTADOS DE ANÁLISIS
// ============================================

class AnalysisResultsController extends Controller
{
    public function getAnalysisResult($documentAnalysisId)
    {
        $processingService = new CertificateProcessingService();
        $result = $processingService->getAnalysisResult($documentAnalysisId);
        
        return response()->json($result);
    }
    
    public function getStatistics()
    {
        $processingService = new CertificateProcessingService();
        $stats = $processingService->getStatistics();
        
        return response()->json($stats);
    }
}

// ============================================
// 4. WEBHOOK PARA PROCESAMIENTO AUTOMÁTICO
// ============================================

class WebhookController extends Controller
{
    public function certificateUploaded(Request $request)
    {
        $certificateId = $request->input('certificate_id');
        
        // Procesar automáticamente cuando se sube un certificado
        ProcessCertificateJob::dispatch($certificateId);
        
        return response()->json(['status' => 'queued']);
    }
}

// ============================================
// 5. COMANDO PARA CRON JOBS
// ============================================

// En tu crontab o Task Scheduler:
// 0 2 * * * cd /path/to/your/app && php artisan queue:work --stop-when-empty

// Comando manual para procesar pendientes:
// php artisan textract:test

// ============================================
// 6. CONFIGURACIÓN DE COLAS (RECOMENDADO)
// ============================================

// En config/queue.php - usar database o redis:
// 'default' => env('QUEUE_CONNECTION', 'database'),

// Ejecutar worker de colas:
// php artisan queue:work

// ============================================
// 7. MONITOREO Y LOGS
// ============================================

use Illuminate\Support\Facades\Log;

class CertificateMonitoringService
{
    public function checkProcessingStatus()
    {
        $processingService = new CertificateProcessingService();
        $stats = $processingService->getStatistics();
        
        if ($stats['success']) {
            $data = $stats['data'];
            
            // Log estadísticas
            Log::info('Certificate Processing Stats', [
                'total_certificates' => $data['total_certificates'],
                'analyzed_certificates' => $data['analyzed_certificates'],
                'success_rate' => $data['success_rate'],
                'processing_efficiency' => $data['processing_efficiency']
            ]);
            
            // Alertar si la eficiencia es baja
            if ($data['processing_efficiency'] < 80) {
                Log::warning('Low processing efficiency detected', $data);
                // Enviar notificación al administrador
            }
        }
        
        return $stats;
    }
}

// ============================================
// 8. EJEMPLO DE USO CON TIPOS DE DOCUMENTOS
// ============================================

use App\Services\UnifiedOcrService;

class DocumentTypeProcessor
{
    private $ocrService;
    
    public function __construct()
    {
        $this->ocrService = new UnifiedOcrService();
    }
    
    public function processColombianDocument($filePath, $documentType)
    {
        // Tipos soportados: 'rut', 'cedula', 'chamber_commerce'
        $result = $this->ocrService->extractColombianDocument($filePath, $documentType);
        
        if ($result['success']) {
            $extractedData = $result['data'];
            
            // Procesar según el tipo de documento
            switch ($documentType) {
                case 'rut':
                    return $this->processRutData($extractedData);
                    
                case 'cedula':
                    return $this->processCedulaData($extractedData);
                    
                case 'chamber_commerce':
                    return $this->processChamberCommerceData($extractedData);
            }
        }
        
        return $result;
    }
    
    private function processRutData($data)
    {
        // Extraer campos específicos del RUT
        $rutFields = $data['colombian_fields'] ?? [];
        
        return [
            'nit' => $rutFields['nit']['value'] ?? null,
            'business_name' => $rutFields['razon_social']['value'] ?? null,
            'activity_code' => $rutFields['codigo_actividad']['value'] ?? null,
            'registration_date' => $rutFields['fecha_matricula']['value'] ?? null,
        ];
    }
    
    private function processCedulaData($data)
    {
        // Extraer campos específicos de la cédula
        $cedulaFields = $data['colombian_fields'] ?? [];
        
        return [
            'document_number' => $cedulaFields['numero_cedula']['value'] ?? null,
            'full_name' => $cedulaFields['nombres']['value'] ?? null,
            'birth_date' => $cedulaFields['fecha_nacimiento']['value'] ?? null,
            'place_of_birth' => $cedulaFields['lugar_nacimiento']['value'] ?? null,
        ];
    }
    
    private function processChamberCommerceData($data)
    {
        // Extraer campos específicos de la cámara de comercio
        $chamberFields = $data['colombian_fields'] ?? [];
        
        return [
            'registration_number' => $chamberFields['numero_matricula']['value'] ?? null,
            'company_name' => $chamberFields['nombre_empresa']['value'] ?? null,
            'legal_representative' => $chamberFields['representante_legal']['value'] ?? null,
            'registration_date' => $chamberFields['fecha_matricula']['value'] ?? null,
        ];
    }
}

// ============================================
// 9. CONFIGURACIÓN DE AMBIENTE DE PRODUCCIÓN
// ============================================

// En tu .env de producción:
/*
AWS_ACCESS_KEY_ID=YOUR_REAL_ACCESS_KEY
AWS_SECRET_ACCESS_KEY=YOUR_REAL_SECRET_KEY
AWS_DEFAULT_REGION=us-east-1
AWS_TEXTRACT_ENABLED=true
AWS_TEXTRACT_REGION=us-east-1

QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database

# Para mejor rendimiento, usar Redis:
# QUEUE_CONNECTION=redis
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379
*/