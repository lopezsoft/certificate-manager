<?php

namespace App\Queries;

use Exception;

class TableList
{
    /**
     * @throws Exception
     */
    public static function getTable($prefix): string
    {
        if(!$prefix) {
            throw new Exception('No se ha especificado el prefijo de la tabla');
        }
        $tableList = [
            'T001' => 'price_list',
            'T002' => 'product_price_list',
            'T003' => 'sellers',
            'T004' => 'cost_centers',
            'T005' => 'document_reception_people',
            'T006' => 'accounting_groups',
            'T007' => 'automated_accounting_accounts',
            'T008' => 'automated_accounting_account_taxes',
            'T009' => 'payment_methods',
            'T010' => 'means_payment',
            'T011' => 'accounting_transaction_type',
            'T012' => 'taxes',
            'T013' => 'banks',
            'T014' => 'bank_account_type',
            'T015' => 'shipping_frequency',
            'T016' => 'destination_environme',
            'T017' => 'additional_document_reference',
            'T018' => 'accounting_documents',
            'T019' => 'correction_accounting_notes',
            'T020' => 'user_types',
            'T021' => 'identity_documents',
            'T022' => 'type_organization',
            'T023' => 'operation_types',
            'T024' => 'time_limit',
            'T025' => 'tax_level',
            'T026' => 'tax_regime',
            'T027' => 'branch_offices',
            'T028' => 'company_departments',
            'T029' => 'wineries_departments',
            'T030' => 'type_persons',
            'T031' => 'ep_contract_type',
            'T032' => 'ep_worker_type',
            'T033' => 'ep_worker_subtype',
            'T034' => 'ep_payroll_period',
            'T035' => 'standard_measurement_units',
            'T036' => 'trademarks',
            'T037' => 'categories',
            'T038' => 'product_class',
            'T039' => 'type_item_identifications',
            'T040' => 'natures_of_account',
            'T041' => 'discount_codes',
            'T042' => 'general_setting_companies',
            'T043' => 'software_information',
            'T044' => 'cash_registers',
            'T045' => 'cash_register_sessions',
            'T046' => 'cash_register_user_access',
            'T047' => 'points_of_sale',
            'T048' => 'point_of_sale_resolutions',
            'T049' => 'products',
            'T050' => 'customer_accounting_accounts',
            'T051' => 'products_accounting_account',
            'T052' => 'tax_accounting_account',
            'T053' => 'business_customers',
            'T054' => 'customers',
            'T055' => 'category_accounting_account',
            'T056' => 'person_references',
            'T057' => 'product_providers',
            'T058' => 'child_products',
            'T059' => 'product_other_taxes',
            'T060' => 'currency',
            'T061' => 'person_branch_offices',
            'T062' => 'mandates_items',
        ];
        return $tableList[$prefix];
    }
}
