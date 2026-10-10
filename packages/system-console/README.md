# System Console scaffold

Future reusable Laravel package, independent of all Sumaya business models.

System Console is reusable across unrelated Laravel projects. It is separate from the
Sumaya-specific Business Management Panel. Authentication, guards and user models for
the two areas remain undecided; neither panel is implemented in Phase 1.
Future extension points may include HealthCheck, MetricProvider, SettingDefinition and
SystemAction. These are documentation only; no interfaces or implementations exist yet.

`src/` contains empty Health, Metrics, Backup, Cache, Queue, Scheduler, Logs, Settings, Maintenance, Actions and Support locations. `resources/views`, `resources/lang/ar`, `database/migrations`, `routes`, `config` and `tests` reserve package resources.

There is no PHP implementation, ServiceProvider, package manifest or Composer integration yet. Package wiring belongs to its implementation phase. See [architecture](../../docs/architecture/README.md).
