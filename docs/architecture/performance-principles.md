# Performance principles

Apply these rules during future implementation. Phase 0 performs no optimization, schema changes or custom migrations.

- Avoid accidental O(n²) / O(n³) hot paths and unnecessary nested loops. Select algorithms and data structures appropriate to workload size.
- Prevent N+1 queries; use bulk reads and justified eager loading.
- Use efficient queries and proper indexes, informed by actual access patterns.
- Paginate collections; avoid unbounded data retrieval.
- Use bulk writes where appropriate and minimize database round trips.
- Select needed columns; avoid unnecessary `SELECT *`.
- Avoid database queries inside loops when a batch query can serve the operation.
- Queue heavy asynchronous work when appropriate.
- Cache only where justified, with a considered invalidation strategy.
- Use `EXPLAIN` for important queries.
- Profile and measure before speculative optimization.
- Filter growing datasets in the database rather than loading everything into PHP.
- Avoid arbitrary cache flushes; cache only justified read-heavy data.
- Consider network round trips, disk I/O, Redis, queue latency, memory and CPU as well as algorithmic complexity. Do not optimize tiny method calls while ignoring database/network I/O.

Orders will require particular care around transactions, idempotency and concurrency. Concrete schema, indexes, queues and cache policies are deferred to implementation.
