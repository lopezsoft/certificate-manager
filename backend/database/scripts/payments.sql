SELECT
  p.provider_reference,
  p.payment_method,
  p.quantity,
  p.unit_price,
  p.subtotal,
  p.tax_amount,
  p.total_amount,
  "CERTIFICADO FIRMA DIGITAL - FACTURACIÓN ELECTRÓNICA" description_payment,
  c.dni,
  id.`code`,
  c.type_organization_id,
  UPPER(c.company_name) company_name,
  c.trade_name,
  c.address,
  c.phone,
  c.email,
  ct.id city_code
FROM
  certificate_orders AS p
  JOIN companies c ON p.company_id = c.id
  JOIN identity_documents AS id ON c.identity_document_id = id.id
  JOIN cities AS ct ON c.city_id = ct.id
WHERE
  p.`status` = 'PAID'
  AND c.id > 1;