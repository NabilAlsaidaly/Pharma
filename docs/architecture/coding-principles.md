# Coding principles

- KISS: prefer the simplest solution that meets the requirement.
- DRY in moderation: consolidate stable duplication; do not abstract coincidental similarity.
- Apply SOLID where useful rather than mechanically introducing interfaces and layers.
- Single Responsibility: keep classes and actions small and focused. Avoid God Classes.
- Separation of Concerns: keep transport, application orchestration, business rules and external integrations distinct where their responsibilities warrant it.
- Explicit over Magic: make dependencies, ownership and control flow easy to follow.
- Favor low coupling and high cohesion; keep related behavior within its owning module.
- Use clear, consistent naming that communicates intent.
- Avoid premature abstractions, empty placeholder classes, deep inheritance and unnecessary repositories or factories.
- Avoid premature optimization. Measure performance and fix demonstrated bottlenecks.
- Keep simple CRUD simple and work naturally with Laravel.

Phase 0 contains no custom implementation. Introduce code and deeper directory structure only when a real use case requires them.
