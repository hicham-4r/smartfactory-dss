SmartFactory Sage ERP Simulator Catalog API Fix
================================================

Target application:
C:\Users\OMEN\Herd\smartfactory-dss\sage-erp-simulator

Purpose:
- Add GET /api/product-families.
- Preserve token, throttle, failure-simulation, and data-quality middleware.
- Return product-family external IDs.
- Add product_family_external_id to product payloads.
- Add focused API regression tests.

No migration is included.
Do not run migrate:fresh.
