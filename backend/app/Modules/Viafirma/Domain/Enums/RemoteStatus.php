<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Enums;

/**
 * Estados remotos reportados por Viafirma RA Colombia.
 *
 * Cada caso mapea 1:1 al valor que retorna `GET /request/{cod}/status`.
 * Los métodos semánticos simplifican la lógica de la FSM y del job de polling.
 *
 * Categorías:
 *  - PROGRESSING: el trámite avanza, seguir polling
 *  - STOP_RECOVERABLE: el operador RA debe intervenir, pausar polling
 *  - READY: el certificado está listo para descarga
 *  - TERMINAL_OK: flujo completado exitosamente
 *  - TERMINAL_FAIL: flujo fallido irrecuperablemente
 */
enum RemoteStatus: string
{
    // ── Progressing (seguir polling) ──────────────────────────────────────
    case RUES_CHECK              = 'rues_check';
    case ACCREDITATION           = 'accreditation';
    case ACCREDITATION_CHECK     = 'accreditation_check';
    case ACCREDITATION_COMPLETED = 'accreditation_completed';
    case ACCREDITATION_VERIFIED  = 'accreditation_verified';
    case PROPOSE_FOR             = 'proposeFor';
    case PROPOSED_TO_ACCEPTANCE  = 'proposedToAcceptance';
    case IN_PROCESS              = 'inProcess';
    case ALL_OK                  = 'All_Ok';

    // ── Stop — requiere intervención de operador RA ──────────────────────
    case RUES_ERROR              = 'rues_error';
    case ACCREDITATION_REJECTED  = 'accreditation_rejected';
    case COLLATE_DATA            = 'collate_data';
    case CHECKING                = 'checking';
    case DOC_REQUIRED            = 'docRequired';
    case DOC_UPLOADED            = 'docUploaded';

    // ── Ready — certificado listo para descarga ──────────────────────────
    case GENERATED_NOT_DOWNLOADED = 'Generated_Not_Downloaded';
    case SIGNED_CONTRACT           = 'signedContract';
    // Sub-estados de signedContract (manual RA §2.3.4.1): procesan el contrato
    // ONAC en paralelo, no interfieren con la tramitación del certificado y
    // permiten (re)descargar el P7B igual que signedContract.
    case CITE_TO_FINISH            = 'Cite_To_Finish';
    case PROCESSING_CONTRACT       = 'processingContract';

    // ── Terminal OK — flujo completado ────────────────────────────────────
    case GENERATED_AND_DOWNLOADED = 'Generated_And_Downloaded';

    // ── Terminal FAIL ─────────────────────────────────────────────────────
    case FAIL = 'fail';

    /**
     * El polling debe continuar en este estado.
     */
    public function isProgressing(): bool
    {
        return in_array($this, [
            self::RUES_CHECK,
            self::ACCREDITATION,
            self::ACCREDITATION_CHECK,
            self::ACCREDITATION_COMPLETED,
            self::ACCREDITATION_VERIFIED,
            self::PROPOSE_FOR,
            self::PROPOSED_TO_ACCEPTANCE,
            self::IN_PROCESS,
            self::ALL_OK,
        ], true);
    }

    /**
     * El polling se detiene — requiere intervención del operador RA.
     * El internal_state pasa a FAILED_RECOVERABLE.
     */
    public function isStopRecoverable(): bool
    {
        return in_array($this, [
            self::RUES_ERROR,
            self::ACCREDITATION_REJECTED,
            self::COLLATE_DATA,
            self::CHECKING,
            self::DOC_REQUIRED,
            self::DOC_UPLOADED,
        ], true);
    }

    /**
     * El certificado está listo para descarga del P7B.
     * Cubre: GENERATED_NOT_DOWNLOADED (flujo normal), SIGNED_CONTRACT y sus
     * sub-estados CITE_TO_FINISH/PROCESSING_CONTRACT (contrato ONAC en
     * paralelo, no bloquea la descarga del P7B).
     */
    public function isReadyToDownload(): bool
    {
        return in_array($this, [
            self::GENERATED_NOT_DOWNLOADED,
            self::SIGNED_CONTRACT,
            self::CITE_TO_FINISH,
            self::PROCESSING_CONTRACT,
        ], true);
    }

    /**
     * Estado terminal exitoso — no requiere más acciones.
     */
    public function isTerminalOk(): bool
    {
        return $this === self::GENERATED_AND_DOWNLOADED;
    }

    /**
     * Fallo irrecuperable.
     */
    public function isTerminalFail(): bool
    {
        return $this === self::FAIL;
    }

    /**
     * Debe detenerse el polling (cualquier estado no progressing).
     */
    public function shouldStopPolling(): bool
    {
        return !$this->isProgressing();
    }

    /**
     * Mapea el estado remoto al InternalState correspondiente.
     */
    public function toInternalState(): InternalState
    {
        return match (true) {
            $this->isProgressing()       => InternalState::POLLING,
            $this->isReadyToDownload()   => InternalState::READY_TO_DOWNLOAD,
            $this->isTerminalOk()        => InternalState::COMPLETED,
            $this->isTerminalFail()      => InternalState::FAILED,
            $this->isStopRecoverable()   => InternalState::FAILED_RECOVERABLE,
        };
    }
}
