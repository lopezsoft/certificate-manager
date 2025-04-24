<?php
namespace App\Modules\Memberships;
use Exception;

class MembershipService
{
    private ?object $membership = null;
    private array $hasElectronicDocumentsList = [4, 5, 7, 8, 9, 10, 12, 16, 19, 20, 22];

    /**
     * @throws \Exception
     */
    public function __construct($company)
    {
        $membership         = MembershipManager::getMembershipData($company);
        $this->membership   = $membership;
    }

    public function getAmountByAdditionalContentPlanId(int $contentPlanId): int
    {
        if (!$this->membership) {
            return 0;
        }
        $content = collect($this->membership->additionalContent)->firstWhere('content_plan_id', $contentPlanId);
        return $content->amount ?? 0;
    }
    /**
     * @throws Exception
     */
    public function hasUsersAvailable(): void
    {
        $this->hasDefinedMembership();
        $content    = collect($this->membership->membership->content)->firstWhere('content_plan_id', 3);
        $amount     = $content->amount ?? 0;
        $amount     += $this->getAmountByAdditionalContentPlanId(3);
        if ($this->membership->consumedUsers > $amount) {
            throw new Exception('No tiene usuarios disponibles. Ha alcanzado el límite de usuarios permitidos');
        };
    }

    /**
     * @throws Exception
     */
    public function hasProductsAvailable(): void
    {
        $this->hasDefinedMembership();
        $content    = collect($this->membership->membership->content)->firstWhere('content_plan_id', 6);
        $amount     = $content->amount ?? 0;
        $amount     += $this->getAmountByAdditionalContentPlanId(6);
        if($this->membership->consumedProducts > $amount) {
            throw new Exception('No tiene productos disponibles. Ha alcanzado el límite de productos permitidos');
        };
    }

    /**
     * @throws Exception
     */
    public function hasEmployeesAvailable(): void
    {
        $this->hasDefinedMembership();
        $content    = collect($this->membership->membership->content)->firstWhere('content_plan_id', 2);
        $amount     = $content->amount ?? 0;
        $amount     += $this->getAmountByAdditionalContentPlanId(2);
        if ($this->membership->consumedEmployees > $amount){
            throw new Exception('No tiene empleados disponibles. Ha alcanzado el límite de empleados permitidos');
        };
    }

    /**
     * @throws Exception
     */
    public function hasPosAvailable(): void
    {
        $this->hasDefinedMembership();
        $content    = collect($this->membership->membership->content)->firstWhere('content_plan_id', 4);
        $amount     = $content->amount ?? 0;
        $amount     += $this->getAmountByAdditionalContentPlanId(4);
        $consumedPos = collect($this->membership->consume)->firstWhere('id', 13);
        if (($consumedPos->total ?? 0) > $amount) {
            throw new Exception('No tiene documentos de punto de venta disponibles. Ha alcanzado el límite de documentos de punto de venta permitidos');
        };
    }

    /**
     * @throws Exception
     */
    public function hasElectronicDocumentsAvailable(): void
    {
        $this->hasDefinedMembership();
        $consume    = $this->getTotalElectronicDocuments();
        $content    = collect($this->membership->membership->content)->firstWhere('content_plan_id', 1);
        $amount     = $content->amount ?? 0;
        $amount     += $this->getAmountByAdditionalContentPlanId(1);
        if ($consume > $amount) {
            throw new Exception('No tiene documentos electrónicos disponibles. Ha alcanzado el límite de documentos electrónicos permitidos');
        };
    }
    public function getTotalElectronicDocuments(): int
    {
        if (!$this->membership) {
            return 0;
        }
        $total = 0;
        foreach ($this->membership->consume as $item) {
            if (in_array($item->id, $this->hasElectronicDocumentsList)) {
                $total += $item->total;
            }
        }
        return $total;
    }

    /**
     * @throws Exception
     */
    private function hasDefinedMembership(): void
    {
        if (!$this->membership) {
            throw new Exception('La membresía no está definida');
        }
    }
}
