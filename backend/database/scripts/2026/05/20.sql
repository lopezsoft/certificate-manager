-- ============================================================================
-- DML: certificate_quotas
-- Estructura final (tras migración 2026_05_21_000001)
-- ============================================================================

-- ── Ejemplo 1: Empresa con convenio POSTPAID, rango PARTNER_RANGE_2, cupo anual
INSERT INTO certificate_quotas
    (company_id, pricing_tier_id, allocated_quantity, used_quantity,
     period_start, period_end, status, billing_type, assigned_by, notes,
     created_at, updated_at)
VALUES
    (10,
     (SELECT id FROM pricing_tiers WHERE code = 'PARTNER_RANGE_2' LIMIT 1),
     200, 0,
     '2026-01-01', '2026-12-31',
     'ACTIVE', 'POSTPAID',
     1, -- assigned_by (users.id del admin)
     'Convenio anual 2026 — 200 certificados',
     NOW(), NOW());

-- ── Ejemplo 2: Empresa con convenio, rango Enterprise, cupo semestral
INSERT INTO certificate_quotas
    (company_id, pricing_tier_id, allocated_quantity, used_quantity,
     period_start, period_end, status, billing_type, assigned_by, notes,
     created_at, updated_at)
VALUES
    (15,
     (SELECT id FROM pricing_tiers WHERE code = 'PARTNER_RANGE_3' LIMIT 1),
     500, 0,
     '2026-01-01', '2026-06-30',
     'ACTIVE', 'POSTPAID',
     1,
     'Convenio semestral S1-2026 — 500 certs Enterprise',
     NOW(), NOW());

-- ── Ejemplo 3: Casa de software, cupo anual sin tope definido
INSERT INTO certificate_quotas
    (company_id, pricing_tier_id, allocated_quantity, used_quantity,
     period_start, period_end, status, billing_type, assigned_by, notes,
     created_at, updated_at)
VALUES
    (22,
     (SELECT id FROM pricing_tiers WHERE code = 'SOFTWARE_HOUSE' LIMIT 1),
     1000, 0,
     '2026-01-01', '2026-12-31',
     'ACTIVE', 'POSTPAID',
     1,
     'Casa de software — cupo anual 2026',
     NOW(), NOW());

-- ── Ejemplo 4: Sin pricing_tier (empresa legacy sin rango asignado aún)
INSERT INTO certificate_quotas
    (company_id, pricing_tier_id, allocated_quantity, used_quantity,
     period_start, period_end, status, billing_type, assigned_by, notes,
     created_at, updated_at)
VALUES
    (8,
     NULL,
     50, 0,
     '2026-05-01', '2027-04-30',
     'ACTIVE', 'POSTPAID',
     1,
     'Cupo provisional — pendiente asignar rango de precio',
     NOW(), NOW());
