SELECT
  a.`uuid`,
  a.company_name AS 'Empresa',
  a.updated_at AS 'F. Generación',
  a.expiration_date AS 'F. Vencimiento'
FROM
  certificate_requests AS a
  JOIN companies AS co ON co.id = a.company_id
WHERE
  co.dni = '900568846'
ORDER BY
  a.expiration_date;