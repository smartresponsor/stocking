# Stocking

Stocking is the SmartResponsor Symfony component that owns inventory availability, stock levels, reservations, incoming quantities, adjustments, and movements across stock locations.

It supports standalone Symfony execution and reusable bundle composition. The canonical product boundary is in `docs/architecture/001-boundary.adoc`.

The current RC capability pass implements inventory-owned StockItem identity, per-location StockLevel quantity facts, invariants, and baseline available-to-promise calculation. Generic CRUD controllers and routes remain absent.

