# Architecture

Laravel Workflow Tools turns local JSON definitions into review output and immutable publications. It uses Laravel Workflow's definition contract and publisher. Laravel Flow supplies persisted step instances, transitions and assignments.

## Services

| Service | Responsibility |
| --- | --- |
| `DefinitionFiles` | Reads a local JSON object, enforces the 1 MiB limit and unique object keys, retains exact integer identities, and delegates graph validation to `Definition::fromArray()` |
| `DefinitionGraph` | Renders a validated graph as Mermaid source using fixed node identifiers and escaped labels |
| `DefinitionDiff` | Compares normalized snapshots and reports JSON Pointer paths with before/after values |
| `LegacyMigrationPlan` | Converts a supported task/transition handoff into a definition or returns mapping issues |
| Workflow `Definition` | Validates graph structure and assignment parameters; provides a canonical SHA-256 fingerprint |
| Workflow `DefinitionPublisher` | Persists immutable name/version snapshots and their Flow definitions transactionally |

## Review and publication flow

1. `DefinitionFiles` reads the file as JSON data and constructs a validated `Definition`.
2. Validation, diagram and diff commands produce console output from that definition.
3. `workflow:publish` prints the fingerprint for review. `--apply --expect=<fingerprint>` requires the reviewed content before invoking the publisher.
4. The publisher stores the definition snapshot and namespaced Flow step/transition definitions. A name/version identifies one immutable snapshot.
5. `workflow:export` reconstructs the stored definition, checks its fingerprint and prints JSON.

Definition identity includes the name, version, initial step, step labels, assignment parameters and action targets. The shared definition contract normalizes ordering. Diagrams retain cycles and fan-out; `END` is rendered as the completion node.

## Import planning

`workflow:legacy-plan` accepts `name`, `version`, `initial`, `tasks` and `transitions`. Each task supplies an `id` and optional `name`. Each transition supplies `task`, `action` and `next_task`.

The planner returns `ready`, a list of `issues` with JSON Pointer paths, and a normalized `definition` when the handoff is ready. Additional fields require application mapping, including false and zero values. Null/empty fields and disabled `email_notify`/`auto_load` flags are supported as omitted metadata. The planner delegates final graph validation to Workflow `Definition`. It does not publish the definition or import run records; those are separate explicit operations described in [the import guide](migration.md).

## Application integration

| Responsibility | Component |
| --- | --- |
| Local definition review and publication commands | Laravel Workflow Tools |
| Versioned definitions, scoped runs, request receipts and actor history | Laravel Workflow |
| Step instances, transitions and assignment records | Laravel Flow |
| Form schemas, validation and rendering | Optional Laravel Fields or application forms |
| Actor authorization, subject ownership and assignment membership | Application policies and Workflow authorization contracts |
| Domain state changes and compensation | Application transaction callbacks and services |
| Notifications and delivery retries | Application listeners and delivery infrastructure |

Tools use the application's configured database and console access. They add no HTTP routes or frontend runtime. The application supplies trusted definitions and chooses the operators authorized to publish or export them.

## Compatibility

Tools 1.0 requires Workflow 1.0 and Flow 2.2. Their stable tags must be available before Composer can resolve Tools. Fields is independent and optional. PHP 8.3+ and Laravel 12/13 are supported.
