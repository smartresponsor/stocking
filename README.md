# Stocking

Stocking is the Symfony component that owns inventory availability, stock levels, reservations, incoming quantities, adjustments, and movements across stock locations in the multi-domain SaaS platform.

It supports standalone Symfony execution and reusable bundle composition. The canonical product boundary is in `docs/architecture/001-boundary.adoc`.

The current RC capability pass implements inventory-owned StockItem identity, per-location StockLevel quantity facts, durable reservations, replay-safe idempotent mutations, optimistic concurrency protection, append-oriented movement audit, transfers, reconciliation, replenishment, strict ATP, and explicit incoming/backorder promise policy. Ordering/Shipping translation remains composition-side so Stocking does not own sibling lifecycle or SKU semantics. Generic CRUD controllers and routes remain absent.

