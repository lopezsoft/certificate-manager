/**
  @description: Certificate for years
  view name: certificate_requests_years_view
 */

SELECT UPPER(b.company_name) AS company_name, COUNT(a.id) total, a.company_id, YEAR(a.updated_at) AS nyear
FROM certificate_requests AS a
JOIN companies AS b ON b.id = a.company_id
GROUP BY a.company_id
ORDER BY b.company_name ASC, YEAR(a.updated_at) DESC;

/**
  @description: Certificate for years
  view name: certificate_requests_months_view
 */

SELECT UPPER(b.company_name) AS company_name, COUNT(a.id) total, a.company_id, YEAR(a.updated_at) AS nyear,
       MONTH(a.updated_at) AS nmonth, monthname(a.updated_at) AS monthname
FROM certificate_requests AS a
JOIN companies AS b ON b.id = a.company_id
GROUP BY a.company_id, YEAR(a.updated_at), MONTH(a.updated_at)
ORDER BY b.company_name, YEAR(a.updated_at), MONTH(a.updated_at);

