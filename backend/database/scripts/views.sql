/**
  @description: Certificate for years
  view name: certificate_requests_years_view
 */

SELECT CONCAT(UPPER(b.company_name),' NIT: ',b.dni) AS company_name, a.life, 
COUNT(a.id) total, a.company_id,
       YEAR(a.updated_at) AS nyear, a.request_status
FROM certificate_requests AS a
         JOIN companies AS b ON b.id = a.company_id
GROUP BY YEAR(a.updated_at), a.company_id, a.request_status, a.life
ORDER BY b.company_name ASC, YEAR(a.updated_at) DESC;

SELECT 
    CONCAT(UCASE(`b`.`company_name`), ' NIT: ', `b`.`dni`) AS `company_name`,
    `a`.`life` AS `life`,
    COUNT(`a`.`id`) AS `total`,
    `a`.`company_id` AS `company_id`,
    YEAR(`a`.`updated_at`) AS `nyear`,
    `a`.`request_status` AS `request_status` 
FROM 
    `certificate_requests` `a` 
JOIN 
    `companies` `b` ON `b`.`id` = `a`.`company_id`
GROUP BY 
    YEAR(`a`.`updated_at`),
    `a`.`company_id`,
    `a`.`request_status`,
    `a`.`life` 
ORDER BY 
    `b`.`company_name` ASC,
    YEAR(`a`.`updated_at`) DESC;

/**
  @description: Certificate for years
  view name: certificate_requests_months_view
 */

SELECT CONCAT(UPPER(b.company_name),' NIT: ',b.dni) AS company_name, a.life, 
COUNT(a.id) total, a.company_id, YEAR(a.updated_at) AS nyear,
       MONTH(a.updated_at) AS nmonth, monthname(a.updated_at) AS monthname, a.request_status
FROM certificate_requests AS a
JOIN companies AS b ON b.id = a.company_id
GROUP BY a.company_id, YEAR(a.updated_at), MONTH(a.updated_at), a.request_status, a.life
ORDER BY b.company_name, YEAR(a.updated_at), MONTH(a.updated_at);



---- 

SELECT UPPER(a.company_name) AS company_name, a.dni, a.legal_representative, a.document_number, a.life, 
a.updated_at fecha_solicitud, a.expiration_date, a.request_status
FROM certificate_requests AS a
JOIN companies AS b ON b.id = a.company_id
WHERE a.created_at BETWEEN '2026-06-01' AND '2026-06-26'
AND a.request_status IN ('PROCESSED', 'PROCESSING')
ORDER BY a.created_at;