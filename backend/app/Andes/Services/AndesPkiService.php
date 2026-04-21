<?php

namespace App\Andes\Services;

use App\Andes\Contracts\AndesPkiServiceContract;
use App\Andes\DTOs\CertificateEmissionRequest;
use App\Andes\DTOs\CertificateEmissionResponse;
use App\Andes\DTOs\CertificateQueryResponse;
use App\Andes\Exceptions\AndesCertificateEmissionException;
use Illuminate\Support\Facades\Log;
use SoapFault;

/**
 * AndesPkiService
 *
 * Comunicación SOAP con ANDES WS PKI.
 * Alcance MVP: CertificadoFacturacionElectronica (tipoCert 10 y 11).
 *
 * Seguridad: nunca loguea el campo 'soporte' (base64 ZIP con documentos).
 */
class AndesPkiService implements AndesPkiServiceContract
{
    public function __construct(
        private readonly AndesSoapClientFactory $soapFactory,
    ) {}

    /**
     * Solicita emisión de certificado de Facturación Electrónica.
     * Método SOAP: CertificadoFacturacionElectronica
     * Alcance: tipoCert 10 (P.Jurídica) y 11 (P.Natural)
     *
     * @throws AndesCertificateEmissionException
     */
    public function requestElectronicInvoiceCertificate(CertificateEmissionRequest $dto): CertificateEmissionResponse
    {
        Log::info('[ANDES-PKI] Solicitando emisión de certificado FE.', [
            'tipoCert' => $dto->tipoCert,
            'municipio' => $dto->municipio,
        ]);

        try {
            $client   = $this->soapFactory->create();
            $params   = $dto->toSoapArray();
            $result   = $client->CertificadoFacturacionElectronica($params);
            $response = $this->normalizeSoapResult($result);

            Log::info('[ANDES-PKI] Respuesta de emisión recibida.', [
                'estado'  => $response['estado'] ?? 'N/A',
                'mensaje' => $response['mensaje'] ?? $response['Mensaje'] ?? 'N/A',
            ]);

            return CertificateEmissionResponse::fromSoapResponse($response);
        } catch (SoapFault $e) {
            Log::error('[ANDES-PKI] SoapFault en emisión de certificado.', [
                'fault' => $e->faultstring ?? $e->getMessage(),
            ]);
            throw new AndesCertificateEmissionException(
                "Error SOAP al solicitar certificado: {$e->getMessage()}"
            );
        }
    }

    /**
     * Consulta el estado de una solicitud por número+documento.
     * Método SOAP: ConsultarSolicitud
     *
     * @throws AndesCertificateEmissionException
     */
    public function queryRequestStatus(string $solicitudId, string $documento): CertificateQueryResponse
    {
        Log::info('[ANDES-PKI] Consultando estado de solicitud.', ['solicitudId' => $solicitudId]);

        try {
            $client = $this->soapFactory->create();
            $result = $client->ConsultarSolicitud([
                'NumSolicitud' => $solicitudId,
                'Identificacion' => $documento,
            ]);

            $response = $this->normalizeSoapResult($result);

            return CertificateQueryResponse::fromSoapResponse($response);
        } catch (SoapFault $e) {
            Log::error('[ANDES-PKI] SoapFault al consultar solicitud.', [
                'fault' => $e->getMessage(),
            ]);
            throw new AndesCertificateEmissionException(
                "Error SOAP al consultar solicitud {$solicitudId}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Obtiene todos los certificados emitidos para una persona.
     * Método SOAP: ConsultarCert
     *
     * @throws AndesCertificateEmissionException
     */
    public function getCertificatesByPerson(string $tipoDoc, string $documento): array
    {
        Log::info('[ANDES-PKI] Consultando certificados por persona.');

        try {
            $client = $this->soapFactory->create();
            $result = $client->ConsultarCert([
                'TipoDoc'   => $tipoDoc,
                'Documento' => $documento,
            ]);

            return $this->normalizeSoapResult($result);
        } catch (SoapFault $e) {
            throw new AndesCertificateEmissionException(
                "Error SOAP al consultar certificados: {$e->getMessage()}"
            );
        }
    }

    /**
     * Obtiene el certificado en formato PEM.
     * Método SOAP: ObtenerCertificado
     *
     * @throws AndesCertificateEmissionException
     */
    public function getCertificatePem(string $solicitudId, string $documento): string
    {
        Log::info('[ANDES-PKI] Obteniendo certificado PEM.', ['solicitudId' => $solicitudId]);

        try {
            $client = $this->soapFactory->create();
            $result = $client->ObtenerCertificado([
                'NumSolicitud'   => $solicitudId,
                'Identificacion' => $documento,
            ]);

            $data = $this->normalizeSoapResult($result);
            return $data['certificado'] ?? $data['Certificado'] ?? '';
        } catch (SoapFault $e) {
            throw new AndesCertificateEmissionException(
                "Error SOAP al obtener PEM: {$e->getMessage()}"
            );
        }
    }

    /**
     * Revoca un certificado.
     * Método SOAP: Revocacion
     *
     * @param int    $causal  Código de causal de revocación
     * @param string $motivo  Descripción del motivo
     *
     * @throws AndesCertificateEmissionException
     */
    public function revokeCertificate(string $serial, string $documento, int $causal, string $motivo): bool
    {
        Log::info('[ANDES-PKI] Solicitando revocación de certificado.', [
            'serial' => $serial,
            'causal' => $causal,
        ]);

        try {
            $client = $this->soapFactory->create();
            $result = $client->Revocacion([
                'Serial'         => $serial,
                'Identificacion' => $documento,
                'Causal'         => $causal,
                'Motivo'         => $motivo,
            ]);

            $data = $this->normalizeSoapResult($result);
            return isset($data['estado']) && (int) $data['estado'] === 0;
        } catch (SoapFault $e) {
            throw new AndesCertificateEmissionException(
                "Error SOAP al revocar certificado: {$e->getMessage()}"
            );
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Normaliza la respuesta SOAP (objeto o array) a array asociativo.
     */
    private function normalizeSoapResult(mixed $result): array
    {
        if (is_array($result)) {
            return $result;
        }

        if (is_object($result)) {
            return json_decode(json_encode($result), true) ?? [];
        }

        return [];
    }
}

