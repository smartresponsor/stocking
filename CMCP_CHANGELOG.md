# CMCP Change Journal

## Canonization read

Read current local Canonization `AGENTS.md`, `README.md`, `MANIFEST.json`, Canon000/007/009/018/020/022/023/024/025/026/029/031/032/033/034/039/043, and their current executable Gating mirrors.

## Target-to-canon mapping

- Composer: `stocking/stock`.
- Namespace: `App\\Stocking\\`.
- Subject: `Stock*`.
- Dual runtime: standalone Symfony plus reusable `StockingBundle`.
- Mandatory sibling dependencies: Objecting, Cruding, Collectioning, Tabling, Viewing, Interfacing via symlinked path repositories and exact `dev-master`.
- Production manifest contains package dependencies only.
- Generic CRUD remains in Cruding.

## Baseline repository state

The target repository did not exist before this materialization. Requested collision aliases returned no canonical repository-file evidence, and workspace creation succeeded without overwrite.

## Created

Composer manifests and lockfile, Symfony bootstrap/bundle surfaces, component-owned `.gating/profile.yaml`, quality tooling, smoke test, ignore baseline, boundary/canon/roadmap/benchmark docs, Git repository metadata on `master`, and this journal.

## Risks

- Composer install still depends on sibling package consistency and external resolution.
- No speculative product model is created in this wave.

## Gates to run

Composer validation/install, PHP lint, PHPUnit, coverage where driver permits, PHPStan, PHP-CS-Fixer dry-run, Gating, Symfony boot/container checks.

## Validation result

All requested component-local gates passed after repair: Composer validation/install, explicit PHP syntax lint, PHPUnit, Xdebug branch coverage with persistent summary, PHPStan, PHP-CS-Fixer dry-run, selected Gating rules (17/17; zero failures/warnings/skips), Symfony boot, YAML lint, and container lint.

## 2026-09-20 RC capability pass

### Reconnaissance

- Re-read the live Stocking README, Composer manifests, boundary/canon/roadmap/competitor docs, current source/tests/config, capability audit, and this journal.
- Re-read authoritative Canonization material including AGENTS.md, architecture guidance, Canon007, Canon008, Canon018, Canon019, Canon038, and Canon040; Gating remains the executable enforcement companion.
- Verified the mandatory Objecting, Cruding, Viewing, and Interfacing dependency contour declared by Stocking and re-read current package-facing material relevant to ownership.
- Re-read sibling responsibility surfaces for Cataloging, Carting, Ordering, Shipping, Locating, Addressing, and Pricing. The resulting boundary remains: Stocking owns inventory facts/reservations/movements; siblings retain catalog/SKU, cart, order, shipment, location/address, and pricing truth.
- Baseline was a product skeleton: only Symfony bootstrap/bundle code existed; all inventory business capabilities were recorded as missing.

### Target-to-canon mapping

- stocking/stock maps literally to App\\Stocking\\ and Stock* business types.
- Product code uses Symfony technical-role roots (Entity, Service, Tests) and introduces no Domain/Application/Infrastructure/Port/Adapter/Adaptor topology.
- Stock item identity is inventory-owned; Cataloging product identity remains an opaque external reference.
- Location identity remains an opaque external reference owned by Locating/Addressing; no location/address truth is duplicated.

### Selected RC-critical work

- Implement M1 stock item identity and per-location quantity facts.
- Enforce on-hand/reserved/available/incoming invariants and deterministic baseline ATP.
- Prove behavior with focused PHPUnit tests before advancing into M2 idempotency/concurrency.

### Material risks

- M2 reservation lifecycle still needs durable idempotency and an explicit persistence-backed concurrency strategy before checkout integration can be RC-safe.
- M3 ledger/reconciliation and M4 backorder/incoming projection plus Ordering/Shipping contracts remain outside this first slice.

### Gates

- Composer strict validation: PASS.
- Changed/untracked PHP lint: PASS.
- PHPUnit: PASS (10 tests, 17 assertions).
- Branch coverage run: PASS and persistent summary regenerated.
- PHPStan level 8: PASS.
- PHP-CS-Fixer check: PASS after applying repository fixer.
- Gating: PASS (17/17; 0 failures, 0 warnings, 0 skips) after completing Canon031 method documentation.
- Symfony YAML lint: PASS.
- Symfony container lint: PASS.

### Checkpoint

M1-M3 are implemented with direct Doctrine runtime dependencies, entity mapping, forward-only migration baseline, Stocking-owned repositories, durable reservation lifecycle, append-oriented bucket-delta movement ledger, transfer/reconciliation/supply persistence, and replay-safe mutation services. StockLevel consumes Objecting's canonical optimistic version field; a two-EntityManager SQLite integration test proves stale parallel writes fail with OptimisticLockException. M4 includes strict ATP plus explicit incoming/backorder promise policy. Ordering/Shipping integration is intentionally composition-side because those siblings own lifecycle and SKU/shipment semantics; Stocking does not import them.

### Final RC evidence

- PHPUnit: PASS, 49 tests / 141 assertions.
- Persistent Xdebug coverage: lines 93.0%, methods 80.2%, branches 85.7%.
- Canon040 is enabled in the Stocking Gating profile and passes its 80/80/70 line/method/branch thresholds.
- PHPStan: PASS.
- PHP-CS-Fixer dry-run: PASS.
- Symfony YAML and container lint: PASS.
- Doctrine mapping validation: PASS.
- Doctrine migrations status: PASS with one forward-only Stocking baseline available.
- Gating: PASS, 18/18 rules with zero failures, warnings, suppressions, or skips.
- Market baseline: Medusa v2, Vendure, and Sylius evidence recorded in `docs/product/002-competitor-baseline.adoc`; speculative growth features remain outside RC.
- Ordering/Shipping integration boundary is materialized in `docs/architecture/003-ordering-shipping-integration.adoc`; no sibling package dependency was introduced into Stocking.

