SMARTFACTORY DSS — Production synchronization mapping fix
=========================================================

This package aligns the DSS production-execution mapper and persister with the
separate Simulated Sage ERP payloads.

Files replaced:
- app/Services/ERP/Mapping/ErpPayloadReader.php
- app/Services/ERP/Mapping/SimulatedSageRecordMapper.php
- app/Services/ERP/Sync/ErpSyncTargetRegistry.php
- app/Services/ERP/Sync/ErpMappedEntityPersister.php
- tests/Feature/ERP/ErpOperationalDataMappingTest.php

Files added:
- app/DTOs/ERP/Mapped/ProductionRecordErpData.php
- tests/Feature/ERP/SimulatedSageProductionPayloadContractTest.php

Key behavior:
- Supports nested paths such as product.external_id.
- Maps production orders and batches from nested simulator relationships.
- Derives deterministic batch sequence numbers from the shift.
- Maps simulator production records to DSS production_records.
- Imported production records are locked and validated.
- Machine is no longer required for ERP production records.
- Successful imports receive import_status=imported.
- Keeps full source traceability and idempotent synchronization.

Run after extraction:
php artisan optimize:clear
php artisan test .\tests\Feature\ERP\ErpOperationalDataMappingTest.php .\tests\Feature\ERP\SimulatedSageProductionPayloadContractTest.php --stop-on-failure

Then refresh current-source prerequisites:
php artisan erp:sync:validate catalog --from-start --per-page=100 --max-pages=10
php artisan erp:sync:validate factory-master --from-start --per-page=100 --max-pages=10

Only after both prerequisites succeed:
php artisan erp:sync:validate production-execution --from-start --per-page=100 --max-pages=50
