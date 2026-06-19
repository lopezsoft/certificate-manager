<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\Certificate\IssuanceRequest;
use App\DTOs\Certificate\IssuanceResult;

/**
 * Contrato agnóstico para proveedores de emisión de certificados digitales.
 *
 * Cualquier integración (correo electrónico legacy, Viafirma RA PKCS#10,
 * Andes SCD, GSE, Certicámara API, etc.) debe implementar esta interfaz.
 *
 * Diseñado bajo el principio de Inversión de Dependencias (SOLID-D):
 * el orquestador de aplicación no conoce la implementación, sólo el contrato.
 *
 * @see docs/2026-05-19-15-00-PLAN-UNIFICACION-API-V1-Y-PROVEEDOR-AGNOSTICO-VIAFIRMA.md
 */
interface CertificateIssuanceProvider
{
    /**
     * Identificador único y estable del proveedor.
     *
     * Se usa como clave en la configuración, en los logs y en el campo
     * `provider` que se persiste en histórico/auditoría.
     */
    public function name(): string;

    /**
     * Determina si el proveedor puede procesar la solicitud indicada.
     *
     * Reglas típicas: feature flag activo, datos mínimos presentes, perfil
     * soportado por el proveedor, empresa habilitada, etc.
     *
     * Debe ser **idempotente y libre de efectos secundarios** — el factory
     * la invoca antes de elegir el proveedor.
     */
    public function supports(IssuanceRequest $request): bool;

    /**
     * Determina si este proveedor es el que actualmente gestiona o emitió la solicitud.
     * Útil para enrutar consultas de estado o descargas al proveedor correcto
     * después de que la emisión ya inició o concluyó.
     */
    public function manages(int $certificateRequestId): bool;

    /**
     * Ejecuta el flujo de emisión (síncrono o asíncrono según el proveedor)
     * y devuelve un resultado normalizado para la capa de presentación.
     *
     * @throws \App\Exceptions\Certificate\CertificateIssuanceException
     *         Si el proveedor no puede completar la emisión por una causa
     *         de negocio reconocida (no por errores transientes de red).
     */
    public function issue(IssuanceRequest $request): IssuanceResult;

    /**
     * Devuelve el estado actual del trámite (lectura).
     *
     * Los proveedores que no soporten polling/consulta (e.g. correo)
     * deben devolver un `IssuanceResult` con `status='unsupported'`.
     */
    public function status(int $certificateRequestId): IssuanceResult;
}

