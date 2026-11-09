SET TIME ZONE 'UTC';
SELECT coalesce(json_agg(row_to_json(x) ORDER BY x.firm, x.number), '[]'::json)
FROM (
  SELECT i.id, i.id_company AS firm, i.id_residential_company AS client, i.id_monthly_invoice AS monthly_template,
         i.invoice_number AS number, i.status::text AS status, i.invoice_name AS name,
         i.issue_date::date AS issue_date, i.due_date::date AS due_date, i.delivery_date,
         i.billing_month::int AS billing_month, i.payment_date::date AS payment_date,
         json_build_object('name', i.company_name, 'street', i.company_address, 'postal_code', i.postal_code, 'city', i.city,
                           'ico', i.company_ico, 'dic', i.company_dic, 'ic_dph', i.company_ic_dph, 'iban', i.company_iban) AS supplier,
         json_build_object('name', i.residential_company_name, 'street', i.residential_company_address,
                           'postal_code', i.residential_postal_code, 'city', i.residential_city,
                           'ico', i.residential_company_ico, 'dic', i.residential_company_dic, 'ic_dph', i.residential_company_ic_dph) AS buyer,
         i.header1, i.header2, i.header3, i.header4,
         i.description_above_services AS above, i.description_services AS services,
         (SELECT json_agg(json_build_object('name', s.name, 'quantity', s.quantity, 'price', s.price) ORDER BY s.id)
            FROM services s WHERE s.invoice_id = i.id) AS items
  FROM invoices i
  WHERE i.issue_date >= '2026-01-01' AND i.issue_date < '2027-01-01'
    AND i.id_company IN (2, 3, 23, 25, 100)
) x;
