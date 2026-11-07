# Laravel Workflow Tools handbook

Tools 1.0 reviews local JSON workflow definitions and publishes an explicitly approved immutable version through Laravel Workflow. It provides six Artisan commands and four public services. It adds no routes, visual editor, network graph service or separate workflow engine. Definitions execute through Workflow 1.0 and native Flow 2.2; user authorization, domain actions and history import remain explicit host services.

## Contents

- [Installation and independent use](#installation-and-independent-use)
- [Complete command reference](#complete-command-reference)
- [Review, publish and round-trip recipe](#review-publish-and-round-trip-recipe)
- [JSON files and limits](#json-files-and-limits)
- [Definition grammar and identity](#definition-grammar-and-identity)
- [Diff API and output](#diff-api-and-output)
- [Graph API and accessible presentation](#graph-api-and-accessible-presentation)
- [Definition handoff planning](#definition-handoff-planning)
- [History handoff and integrated procurement](#history-handoff-and-integrated-procurement)
- [Diagnostics, translation and reporting](#diagnostics-translation-and-reporting)
- [Upgrade and verification](#upgrade-and-verification)

The [feature index](FEATURES.md) maps every command, service and flag to this guide and executable examples.

## Installation and independent use

Use PHP 8.3+ and Laravel 12/13. Install in an existing Laravel application:

```sh
composer require mrnewport/laravel-workflow-tools:^1.0
php artisan migrate
php artisan list workflow
```

The actual mandatory production dependencies are Illuminate Console/Support `^12 || ^13`, `mrnewport/laravel-workflow:^1.0`, and transitively Workflow's Illuminate database/contracts/validation and `mrnewport/laravel-flow:^2.2`. Tools can be used independently of any application UI and independently of Fields/Filament/Livewire, but **not** independently of Workflow and Flow. Fields is optional. Laravel's and each dependency's platform floors still apply.

When stable tags exist but Composer's registry has not indexed them, configure VCS repositories for the dependency chain, then repeat the install:

```sh
composer config repositories.mrnewport-flow vcs https://github.com/MrNewport/laravel-flow
composer config repositories.mrnewport-workflow vcs https://github.com/MrNewport/laravel-workflow
composer config repositories.mrnewport-workflow-tools vcs https://github.com/MrNewport/laravel-workflow-tools
composer require mrnewport/laravel-workflow-tools:^1.0
```

Verify actual public tags before installation. GitHub release availability and registry indexing are separate; this fallback cannot supply a stable version before its tag exists. Do not lower project stability simply to hide a missing release.

The discovered provider registers commands only for console runs. It binds a default `FailureReporter` and loads package translations. There is no Tools database migration; Workflow/Flow providers load their migrations. Commands run with the host's configuration/database privileges. Restrict console/deployment access appropriately; `--expect` establishes content review, not operator authentication. File-only review commands do not need migrated package tables, although they boot the Laravel application and use its configured definition budgets.

## Complete command reference

All successful commands return exit status **0**. Handled invalid input, review blockers, publication conflicts and operation failures return **1**. `diff --check` also returns **1** for an otherwise valid difference; inspect output to distinguish a change report from a failure. Laravel/Symfony handles missing arguments, unknown options and global console behavior. Package commands do not define extra exit-status meanings.

| Command and exact arguments/options | Writes | Output and behavior |
| --- | --- | --- |
| `workflow:validate {file}` | No | JSON `{valid:true,name,version,fingerprint}` after shared definition validation |
| `workflow:graph {file} {--format=mermaid}` | No | Literal Mermaid source; `--format=text` gives a plain transition list; other values fail |
| `workflow:diff {before} {after} {--check}` | No | JSON `{changed,before,after,changes}`; normal mode returns 0 even when changed; `--check` returns 1 when changed |
| `workflow:publish {file} {--apply} {--expect=}` | Only with `--apply` | JSON `{published,name,version,fingerprint}`. Preview is default. `--expect` compares the exact full fingerprint in preview too. Apply requires `--expect`. |
| `workflow:export {fingerprint}` | No database writes | Literal normalized JSON for a stored definition. Requires exactly 64 lowercase hex characters. Reads/verifies saved snapshot. |
| `workflow:legacy-plan {file}` | No | JSON `{ready,issues,definition}`. Ready returns 0; blockers return 1 and null definition. Never imports historical records or executes stored behavior. |

`{file}`, `{before}` and `{after}` mean readable **local JSON** paths, either absolute or relative to the current console directory. Quote paths containing spaces. `workflow:export` accepts a fingerprint, not a name/version or filename. It writes to stdout; shell redirection is the caller's file write. The commands have no `--force`, `--tenant`, `--actor`, `--output`, `--dry-run` or remote-file option. Use the host's ordinary global options (`--help`, `--no-ansi`, verbosity) as needed. Public JSON/text output is emitted raw so console markup such as `<error>` inside a valid label is preserved literally.

Examples from the package root of an installed development checkout (copy examples into your application's `workflows` directory for a real app):

```sh
php artisan workflow:validate workflows/review.json
php artisan workflow:graph workflows/review.json --format=mermaid
php artisan workflow:graph workflows/review.json --format=text
php artisan workflow:diff workflows/review.json workflows/review-v2.json
php artisan workflow:diff workflows/review.json workflows/review-v2.json --check
php artisan workflow:publish workflows/review.json
php artisan workflow:publish workflows/review.json --apply --expect=FULL_REVIEWED_SHA256
php artisan workflow:export FULL_REVIEWED_SHA256
php artisan workflow:legacy-plan exports/legacy-review.json
```

The uppercase fingerprint text is a placeholder to replace with the returned 64-character value. A command that returns 1 may have encountered an **after-commit** callback failure after publication committed; inspect saved state before retrying. See [diagnostics](#diagnostics-translation-and-reporting).

## Review, publish and round-trip recipe

1. Save a definition in version control. Use [examples/review.json](../examples/review.json) as a minimal serial revision cycle or [examples/procurement.json](../examples/procurement.json) for parallel review. Review the graph data and host policies together. Static assignment identities must be valid for the environment that will start runs.
2. Run `workflow:validate` and retain the fingerprint in your review record. It is computed from canonical normalized **content**, not raw whitespace. Produce both Mermaid and text output and inspect all transitions, including cycles/cancellation.
3. Compare the proposal to the previously reviewed/exported definition with `workflow:diff`. A graph must use a new integer version when any published content changes. The diff's hashes include that version.
4. Preview with `workflow:publish file.json`. It does not persist. If passing `--expect`, the preview itself fails when content changed after review.
5. Apply only the approved value: `workflow:publish file.json --apply --expect=<reviewed-value>`. The publisher atomically creates the immutable snapshot and native Flow definitions. Exact repeated publication returns the existing definition. Another snapshot under the same name/version fails; no overwrite flag exists.
6. Export by that fingerprint and read it again. This verifies stored snapshot integrity and the file encoder's round-trip boundary. For example, use `workflow:export <hash> > workflows/review-exported.json`, then `workflow:diff workflows/review.json workflows/review-exported.json --check`; unchanged output is `changed:false` and exit 0.
7. Start/resume actual runs only through authorized Workflow application services. Tools publication does not create runs, grant permissions, move active runs to a new version, deliver notifications or modify subject state.

The [documentation regression test](../tests/DocumentationExamplesTest.php) reads the shipped JSON examples, generates both graph forms, publishes with the reviewed hash, exports/re-reads and checks identity. This keeps the runnable review recipe attached to actual APIs.

## JSON files and limits

```php
use MrNewport\LaravelWorkflowTools\DefinitionFiles;

$files = new DefinitionFiles;
$data = $files->readArray('/absolute/path/to/review.json'); // array, JSON parsing only
$definition = $files->read('/absolute/path/to/review.json'); // Definition, full graph validation
$json = $files->encode($definition); // string, normalized JSON plus newline
```

| Public API | Contract |
| --- | --- |
| `DefinitionFiles::MAX_BYTES` | 1,048,576 bytes |
| `readArray(string $path): array` | Rejects empty/NUL paths and any `://` wrapper/URL; resolves real path, requires readable regular file; reads at most limit+1 bytes; requires UTF-8 JSON object root, maximum decoder depth 128; rejects duplicate object keys recursively |
| `read(string $path): Definition` | Calls `readArray`, then Workflow `Definition::fromArray`, so graph/admission errors also fail |
| `encode(Definition $definition): string` | Pretty JSON when that representation fits; otherwise compact JSON, always with trailing newline; throws if compact export exceeds 1 MiB |

`readArray` is intentionally not a definition validator; it is used for the different legacy handoff grammar. JSON syntax failures are reported as input errors without evaluating file contents. A `.php` file is never required or executed. Local symlinks resolve to their target; host file-access policy controls which local paths an operator can read.

Duplicate keys are rejected even if written with escaped-equivalent names such as `"name"` and `"n\u0061me"`, at any nesting level. JSON decoding alone would silently discard one, so key uniqueness is checked separately. Large integer JSON values outside the PHP integer range become exact strings (`JSON_BIGINT_AS_STRING`), preserving user IDs such as `"18446744073709551615"`. Definition version must still be a real in-range integer; oversized numeric graph IDs do not bypass identifier rules. Input strings must be UTF-8; do not change an identifier through lossy transcoding.

Canonical normalized definitions accepted today fit the compact export limit, but pretty indentation can push them beyond 1 MiB. The adaptive encoder compacts only when needed so exported files remain readable by `read`. Do not implement a different unbounded `json_encode` path for application exports. A trusted historical definition admitted under older/larger limits can still hydrate in Workflow; an oversized Tools file export fails explicitly. Use the PHP Workflow API for reviewed archival handling of that case. The local file decoder's depth 128 is also distinct from Workflow's finite-JSON depth bound of 512.

## Definition grammar and identity

The authoritative contract is Workflow's `Definition`; Tools deliberately reuses it instead of maintaining a second grammar. Root fields are `name`, integer `version`, `initial`, `steps`. Each step has `id`, optional `name`, optional `assignment_strategy`/`assignment_params`, and `transitions`. Unknown properties fail. A complete assignment example is:

```json
{
  "name": "procurement-review",
  "version": 1,
  "initial": "review",
  "steps": [{
    "id": "review",
    "name": "Review purchase",
    "assignment_strategy": "single_user",
    "assignment_params": {"user_id": "17"},
    "transitions": {"approve": ["END"], "cancel": ["END"]}
  }]
}
```

Names are lowercase portable identifiers up to 80 bytes; initial/local IDs are case-sensitive portable identifiers up to 64 bytes. Actions are lowercase portable identifiers up to 100 bytes. Identifiers start with an ASCII letter and continue with ASCII letters/digits/dots/underscores/hyphens. `END` cannot be a step ID. Coordination action keys `start`, `comment`, `reassign`, `cancel-run`, `import` are reserved.

The graph needs 1–250 steps, 1–50 actions per step and 1–50 distinct targets per action. Targets reference defined steps or exactly `['END']`; `END` cannot be mixed with other targets. Every step is reachable from initial and can reach `END`. Cycles and fan-out are supported. Default total edge budget is 10,000, configurable through `workflow.max_definition_edges` in range 1–625,000. Normalized compact content plus newline must fit 1 MiB.

Assignments are null with empty params, `single_user` with `user_id`, `multi_user` with `user_ids`, or `email_list` with `emails`. Lists contain 1–250 distinct valid identities and obey Flow's current assignment budget. Identity values are nonblank integer/string values up to 255 bytes without control/DEL characters; emails also pass email syntax validation. Operators and host start/import policy must establish actual membership and ownership. A valid graph does not prove those domain facts.

Canonical identity includes name/version, labels, assignments and action targets. Steps, action keys and targets normalize deterministically; assignment-list order remains data. Formatting/object ordering changes do not create a new identity. PHP input must be finite JSON data, detached from references. Numeric objects whose keys would become a contiguous list are refused rather than reinterpreted; lists retain order, and numeric types such as `1` and `1.0` remain distinct in Workflow fingerprints. See Workflow's [handbook](https://github.com/MrNewport/laravel-workflow/blob/main/docs/HANDBOOK.md) for exact APIs, stored-definition admission compatibility and command semantics.

## Diff API and output

```php
use MrNewport\LaravelWorkflowTools\DefinitionDiff;

$report = (new DefinitionDiff)->between($before, $after);
```

`between(Definition $before, Definition $after): array` returns:

```json
{
  "changed": true,
  "before": "<before fingerprint>",
  "after": "<after fingerprint>",
  "changes": [{"path": "/version", "before": 1, "after": 2}]
}
```

Equal fingerprints yield no changes. Changes recursively compare normalized `toArray()` values with strict scalar comparison. Paths follow JSON Pointer escaping: `~` becomes `~0`, `/` becomes `~1`; array indices use zero-based positions. A path such as `/steps/1/transitions/approve/0` refers to a normalized step list position, not a local step ID. Adding/removing a key reports the missing side as null, so consult the full snapshots when distinguishing absence from an explicit null. The report is review evidence, not an executable JSON Patch; do not apply it as one. A change to step ordering in source may normalize away, while changing a label is real content drift.

The command always emits the same literal JSON report. Default exit 0 means a valid comparison completed; `--check` adds a CI drift gate. Invalid definitions fail before any report. The service does no database reads/writes and never mutates its arguments.

## Graph API and accessible presentation

```php
use MrNewport\LaravelWorkflowTools\DefinitionGraph;

$graph = new DefinitionGraph;
$mermaid = $graph->mermaid($definition); // string
$text = $graph->text($definition); // string
```

`mermaid(Definition): string` emits a `flowchart TD` with fixed synthetic node IDs (`s0`, `s1`, …), a start node, terminal `END`, every action edge, cycles/fan-out and escaped display labels. It includes frontmatter `securityLevel: strict` and `htmlLabels: false`, plus `accTitle` and `accDescr` with definition/version, node/edge counts and initial step. Developer labels cannot introduce directives, links or executable HTML. This produces source only; no graph leaves the host and no Mermaid renderer is installed by Composer.

`text(Definition): string` emits the title/version, initial step and complete named transitions. Serve that equivalent text beside complex diagrams or where scripts are unavailable. `workflow:graph file --format=text` is useful in terminal review and accessible exports.

When embedding in your own UI, use a maintained Mermaid renderer, keep strict security and HTML labels disabled, render source as text rather than interpolated HTML, and set resource limits appropriate to your allowed graph size. Preserve generated accessibility metadata in the resulting SVG. Provide keyboard-accessible controls and the text transition alternative; do not require readers to infer policy from diagram position or color. Translation overrides change metadata labels without changing identifiers/edges. Mermaid's rendered label escaping may show literal entity-safe text; machine identity remains the definition fingerprint.

## Definition handoff planning

`LegacyMigrationPlan::review(array $export): array` examines one explicit table-shaped definition export. It executes no stored callback names, class strings, SQL or migration script. Its supported JSON schema is:

```json
{
  "name": "document-review",
  "version": 1,
  "initial": "draft",
  "tasks": [
    {"id": "draft", "name": "Prepare document", "email_notify": 0},
    {"id": "review", "name": "Review document"},
    {"id": "END", "name": "Complete"}
  ],
  "transitions": [
    {"task": "draft", "action": "submit", "next_task": "review"},
    {"task": "review", "action": "approve", "next_task": "END"},
    {"task": "review", "action": "revise", "next_task": "draft"}
  ]
}
```

`tasks` and `transitions` must be lists or input fails. Each task needs a **string** `id`; duplicate IDs are issues. `name` is optional. `END` may appear as a reference task but creates no runtime task; `CANCEL` requires explicit host cancellation mapping and is blocked. Numeric-only IDs are not valid final Definition IDs; map them to portable local names explicitly.

Every transition is an object with string `task`, `action`, `next_task`. Source task must exist and not be reserved. `next_task: 'CANCEL'` is blocked, not silently converted to `END`. Undefined targets, duplicate targets, unreachable nodes and all other graph errors are reported through the reused Definition validator.

Allowed top-level sections are exactly `name`, `version`, `initial`, `tasks`, `transitions`. Unknown sections produce issues even if empty. Extra row columns require explicit mapping unless the value is null or `''`; only `email_notify` and `auto_load` may also be omitted when exactly `false`, `0` or `'0'`. Other zero/false values remain meaningful and block conversion. A stored `callback`, role, condition, queue, due date, organization or assignment field must be reviewed in application code, not silently discarded.

The result is `{ready:bool, issues:[{path,reason}], definition:array|null}`. Row paths use escaped JSON Pointers; a final graph validation problem uses `/`. Ready output is already normalized and has default assignments; it is not published. Save only its `definition` into a separate JSON file, validate, graph, diff and publish through the normal review procedure. Resolve source assignment/authorization policy before enabling starts. Re-running a review is safe and has no database effects.

## History handoff and integrated procurement

A definition plan contains graph structure. Historical facts use Workflow's **different** `Migration\HistorySnapshot` and `Migration\HistoryImporter`; `workflow:legacy-plan` does not adopt tasks/messages/runs. Follow Workflow's [history migration reference](https://github.com/MrNewport/laravel-workflow/blob/main/docs/migration.md) for every field, mapper callback and guard.

A reviewed active snapshot contains `source`, exact saved `subject:{type,id}`, `status`, a 1–10,000 task list and JSON `evidence`. Tasks have unique source IDs, local step, timezone-qualified start/finish dates, nullable completion action/actor, at most 250 distinct user/email assignments and at most 1,000 messages. Pending tasks have null completion date/action/actor; terminal snapshots have no pending tasks. Snapshot encoded size is at most 16 MiB. Bind both import policy and normal start policy, review the exact snapshot fingerprint, and import through one same-connection transaction. No transition callbacks/notifications are replayed. Compare `WorkflowImport.task_mapping` and preserved evidence before resumed actions.

`LegacySnapshotMapper` accepts task columns `id`, `task`, `date_queued`, `date_done`, `action_done`, `user_done`; message columns `workflow_task`, `author_role`, `message_text`, `create_date`; and rejection links `current_task_id`, optional `next_task_id`. Explicit `step`, `actor`, `assignments`, `timestamp` closures resolve local step IDs, current identities and source timezone; they must throw when unresolved. The helper retains raw rows in evidence and rejects references outside the supplied run. It does no source queries and cannot prove source consistency itself.

For procurement, the integrated sequence is:

1. Author the trusted Fields schema or pure Laravel validator for purchase data and the host policies for requester/reviewer/admin roles, scope, destination membership and budget authority.
2. Use Tools to validate, graph and diff `procurement.json`, then publish the exact reviewed fingerprint. Publication is graph administration, not purchase approval.
3. Use Workflow `start` on a saved purchase. Submit a payload through `act`; the selected validator returns allowed data and a host transaction callback locks/updates the purchase. Native Flow creates assigned review tasks.
4. Reviewer comments/reassignment/rejection/approval use Workflow version/replay semantics. Native Flow owns actual task transitions/assignment rows. The host callback owns budget rules, purchase state and the outbox for external notifications/order creation.
5. Read query-backed Workflow history and full message pages. Tools export can show the immutable definition still pinned to that run; a new graph version does not rewrite the audit trail.
6. For prior saved work, review a separate history snapshot and import without replaying past effects, then resume through the same policies/validator.

The Workflow [runnable procurement source](https://github.com/MrNewport/laravel-workflow/tree/main/examples/procurement) executes this host/engine boundary without Fields or Filament. To add Fields, bind `FieldsActionValidator` with `Schema::make([Field::text('title')->required()])` registered by definition name, version, local step and action; keep the schema trusted and final domain validation in host code. Tools JSON files do not contain executable schemas. The playground's connected procurement lab exercises Tools definition services, Workflow manager, Fields validator and native Flow together, with explicit actor/key/version history.

## Diagnostics, translation and reporting

Invalid inputs and invariant failures (`InvalidArgumentException`, `LogicException`) show their escaped diagnostic message and exit 1. Unexpected failures are replaced by a generic operation message. Database `QueryException` messages omit query/connection details and explicitly instruct checking published state. Runtime/database/unexpected failures receive a random 16-character diagnostic reference, and the reporter receives the original exception plus reference. Console markup in errors is escaped, including at verbose/debug verbosity.

An exception after commit can cause exit 1 with the definition already published. Export/query the reviewed fingerprint before retrying; exact publication is idempotent. Do not interpret failure output as a blanket rollback promise. A different name/version publication remains a conflict.

```php
use MrNewport\LaravelWorkflowTools\Contracts\FailureReporter;

final class ProtectedWorkflowReporter implements FailureReporter
{
    public function report(\Throwable $exception, string $reference): void
    {
        logger()->error('Workflow definition operation failed', [
            'reference' => $reference,
            'exception' => $exception,
        ]);
    }
}
// In an application provider:
$this->app->bind(FailureReporter::class, ProtectedWorkflowReporter::class);
```

The default `Diagnostics\LaravelFailureReporter` delegates to Laravel's `ExceptionHandler`, wrapping the original as the previous exception with the same reference. Protect host logs/error services. A reporter failure is caught and does not replace the original console failure. Do not write exception chains into public graph JSON or user-facing pages.

Publish translations with `php artisan vendor:publish --tag=workflow-tools-translations`, then override `lang/vendor/laravel-workflow-tools/{locale}/messages.php`. Diagnostics, graph title/description/start/initial text and command descriptions use these strings. Exact identifiers, action keys, fingerprints and user labels remain data.

| Failure | Check |
| --- | --- |
| Missing package version | Verify dependency tags and registry indexing; configure the whole VCS chain if needed. |
| File unreadable/path error | Use a regular local readable JSON file, no wrapper/URL/NUL, quote spaces and check working directory. |
| Invalid UTF-8/JSON or duplicate key | Correct the original file; escaped-equivalent duplicates are also refused. |
| File/export too large | Reduce graph size or use reviewed archival PHP API for historical data; pretty export automatically compacts within the same bound. |
| `--expect` mismatch | Review the changed canonical definition; never replace a hash merely to force apply. |
| Existing name/version conflict | Publish a new integer version after review. |
| Engine drift | Restore the reviewed namespaced Flow definition from trusted evidence; do not bypass the integrity check. |
| Legacy plan blockers | Follow exact pointer and explicitly map behavior/metadata, including false/zero values. |
| Exit 1 with diagnostic reference | Check protected diagnostics and saved fingerprint/receipt before retrying. |
| Graph has poor screen-reader output | Preserve strict config and accessible title/description; provide `--format=text` output alongside it. |

## Upgrade and verification

Tools is an interface to Workflow's contracts, not a separate saved-state format. Keep Workflow/Flow migrations and saved-fingerprint/timezone compatibility requirements intact. Definitions published under older admission budgets remain readable in Workflow, while Tools export keeps its explicit file-size boundary. Historical v1 command receipts and import evidence require the reviewed recovery rules documented by Workflow; a Tools diff does not authorize rewriting them.

Install development dependencies and run `composer check` for formatting, Larastan and tests; `composer test` runs the suite alone. The package tests use disposable databases. The configured CI matrix covers PHP 8.3/8.4/8.5 and Laravel 12/13 with SQLite/MySQL, plus secure minimum dependencies. Configuration of a CI job is not evidence that a remote run has completed; inspect actual checks for a release. See [README test setup](../README.md#tests) for explicit disposable MySQL variables.
