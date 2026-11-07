# Definition and import guide

Definitions describe graph structure. History snapshots describe saved run facts. Review and publish a definition before binding an imported run to it.

## Definition JSON

A definition contains a stable name, positive integer version, initial step ID and a list of steps:

```json
{
    "name": "document-review",
    "version": 1,
    "initial": "review",
    "steps": [
        {
            "id": "review",
            "name": "Review document",
            "assignment_strategy": "single_user",
            "assignment_params": {"user_id": "17"},
            "transitions": {"approve": ["END"]}
        }
    ]
}
```

Step IDs identify targets within the definition. Each action maps to a nonempty target list. `END` completes the branch. Assignments support `single_user`, `multi_user` and `email_list`; omit the strategy for an unassigned step. Every step must be reachable from the initial step and have a path to `END`.

Validate and inspect the file, then publish its reviewed fingerprint:

```sh
php artisan workflow:validate workflows/review.json
php artisan workflow:graph workflows/review.json
php artisan workflow:publish workflows/review.json --apply --expect=FULL_SHA256_FINGERPRINT
```

Change the version when changing a published definition. Active runs remain bound to their published snapshot.

## Task/transition JSON handoff

The planning command accepts this table-shaped representation:

```json
{
    "name": "document-review",
    "version": 1,
    "initial": "review",
    "tasks": [{"id": "review", "name": "Review document"}],
    "transitions": [{"task": "review", "action": "approve", "next_task": "END"}]
}
```

```sh
php artisan workflow:legacy-plan exports/review-definition.json
```

The result has these fields:

| Field | Meaning |
| --- | --- |
| `ready` | Whether the handoff can produce a validated definition |
| `issues` | Objects containing `path` and `reason` for each required mapping |
| `definition` | Normalized definition object when `ready` is true; otherwise null |

Save the returned `definition` object to its own JSON file, validate it and publish it through the normal commands. Additional row properties or unknown top-level sections require an explicit application mapping, including false and zero values. Null/empty row fields and disabled `email_notify`/`auto_load` flags can be omitted from the definition. Cancellation targets require a separate policy; they are not converted to ordinary branch completion.

A definition plan contains graph structure. Run histories, subject bindings, assignments and messages use the Workflow snapshot API below.

## History snapshot

Construct `MrNewport\LaravelWorkflow\Migration\HistorySnapshot` from JSON data with this shape:

```json
{
    "source": "review-import-001",
    "subject": {"type": "document", "id": "42"},
    "status": "active",
    "tasks": [
        {
            "source_id": "task-001",
            "step": "review",
            "started_at": "2026-11-06T10:00:00Z",
            "finished_at": null,
            "action": null,
            "actor": null,
            "assignees": [{"type": "user", "value": "17"}],
            "messages": [
                {"author": "17", "body": "Ready for review", "created_at": "2026-11-06T10:05:00Z"}
            ]
        }
    ],
    "evidence": {"batch": "documents-2026-11"}
}
```

| Field | Contract |
| --- | --- |
| `source` | Stable source-run identifier, up to 191 characters |
| `subject` | Saved model's class or registered morph alias, plus its key as a string |
| `status` | `active`, `completed` or `cancelled`; must agree with pending tasks |
| `tasks` | 1–10,000 task objects with unique `source_id` values |
| `step` | A local step ID in the published definition |
| `started_at`, `finished_at` | ISO 8601 timestamp with an explicit timezone; pending tasks have null `finished_at` |
| `action`, `actor` | Completion attribution; pending tasks use null values |
| `assignees` | Up to 250 distinct `user` or `email` identities per task |
| `messages` | Up to 1,000 author/body/timestamp objects per task |
| `evidence` | Application-supplied JSON supporting the reviewed mapping |

Snapshots are bounded to 16 MiB. Preserve the real subject key type and map identities within the intended application scope. The subject must exist on the same database connection as Workflow and Flow records.

## Import a reviewed snapshot

The application must bind `ImportAuthorizer` and `WorkflowAuthorizer` policies. Resolve the subject through the intended scope, build the snapshot and review its fingerprint before calling the importer:

```php
use MrNewport\LaravelWorkflow\Migration\HistoryImporter;
use MrNewport\LaravelWorkflow\Migration\HistorySnapshot;

$snapshot = HistorySnapshot::fromArray($snapshotData);

$run = app(HistoryImporter::class)->import(
    $definition,
    $subject,
    $snapshot,
    $scope,
    $actor,
    $requestKey,
    $reviewedFingerprint,
);
```

Use `$snapshot->fingerprint()` during the review step to obtain `$reviewedFingerprint`. Import verifies that fingerprint and the saved subject binding, checks policies again under a subject lock, and persists the run, steps, assignments, messages and source-to-step mapping transactionally. It stores history directly without executing transition callbacks or sending notifications.

The source identifier and application scope identify a run import. Repeating the same reviewed binding returns the existing run. Conflicting evidence, definition or identity mappings fail. Keep the persisted import mapping for reconciliation, and verify pending steps and history against the reviewed source before enabling application commands for the imported run.

## Package availability

Tools 1.0 requires Workflow 1.0 and Flow 2.2. Confirm their public release tags and registry indexing, or use the VCS repository fallback in the [handbook](HANDBOOK.md#installation-and-independent-use). Fields can be adopted independently.
