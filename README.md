# Laravel Workflow Tools

Validate, compare, diagram and publish versioned definitions for `mrnewport/laravel-workflow`.

**Compatibility:** PHP 8.3+, Laravel 12/13, Laravel Workflow 1.0 and Laravel Flow 2.2. Fields is optional; Tools does not require Filament or a frontend. Consult the [complete handbook](docs/HANDBOOK.md) for all commands, API contracts and installation fallback.

## Installation

If registry indexing is pending, use public stable release tags through VCS repositories for Flow, Workflow and Tools. The [installation reference](docs/HANDBOOK.md#installation-and-independent-use) gives the exact commands. GitHub releases and registry availability are separate states; verify the corresponding tags before installation.

## Commands

Start with [examples/review.json](examples/review.json).

```sh
php artisan workflow:validate workflows/review.json
php artisan workflow:graph workflows/review.json
php artisan workflow:diff workflows/review-v1.json workflows/review-v2.json --check
php artisan workflow:publish workflows/review.json
```

| Command | Result |
| --- | --- |
| `workflow:validate {file}` | Validates a JSON definition and prints its canonical SHA-256 fingerprint |
| `workflow:graph {file} {--format=mermaid}` | Prints accessible Mermaid source or a plain-text transition summary with `--format=text` |
| `workflow:diff {before} {after}` | Prints changed JSON Pointer paths and both fingerprints |
| `workflow:publish {file}` | Previews the definition's name, version and fingerprint |
| `workflow:export {fingerprint}` | Verifies and prints a published definition snapshot |
| `workflow:legacy-plan {file}` | Reviews a task/transition JSON handoff and prints a definition or mapping issues |

`diff --check` returns status 1 when definitions differ. Reordering normalized object keys preserves a definition's identity. Definition files must be readable local JSON objects of at most 1 MiB; URLs and stream wrappers are rejected. JSON object keys must be unique, including escaped equivalents. Integer identities outside the PHP integer range are retained as exact strings. The Workflow `Definition` parser validates graph reachability, assignment parameters, targets and terminal behavior.

## Publish a reviewed definition

`publish` previews by default. Supply `--apply` and the full fingerprint from your review to persist the same definition:

```sh
php artisan workflow:publish workflows/review.json --apply --expect=FULL_SHA256_FINGERPRINT
php artisan workflow:export FULL_SHA256_FINGERPRINT
```

Laravel Workflow's `DefinitionPublisher` publishes the snapshot and Flow definitions in a transaction. An identical publication can be repeated. A different snapshot with an existing name/version fails; assign a new version to publish a changed definition. Export verifies the persisted snapshot's fingerprint before producing JSON.

Commands return 0 on success and 1 for invalid input, mapping issues, publication conflicts or failed database operations. JSON, text and Mermaid output are emitted literally. Diagnostic errors escape console formatting, and database errors omit query and connection details.

A failed after-commit callback can leave a publication committed even when the command returns 1. Inspect the immutable definition fingerprint before retrying; failure output does not assert rollback. Unexpected failures and database errors include a diagnostic reference. The default `Contracts\FailureReporter` sends the exception chain and reference to Laravel's protected exception handler. Bind that contract to an application reporter when required. Public console output remains sanitized even at debug verbosity; protect the host's logs and error-reporting service.

Built-in diagnostics, graph metadata and command descriptions use Laravel translations. Publish language files with `php artisan vendor:publish --tag=workflow-tools-translations`, then override `lang/vendor/laravel-workflow-tools/{locale}/messages.php`. Developer-supplied identifiers and action keys remain exact data.

## Definition and history handoffs

For a JSON export with `tasks` and `transitions`, run:

```sh
php artisan workflow:legacy-plan exports/review-definition.json
```

The report contains `ready`, `issues` and `definition`. When `ready` is true, save the `definition` object as a definition file, validate it, then follow the publication steps above. Additional metadata requires an explicit application mapping, including false and zero values. Null/empty fields and disabled `email_notify`/`auto_load` flags can be omitted from the resulting definition. Completion uses the `END` sentinel; run cancellation needs an application-defined policy.

See [the definition and import guide](docs/migration.md) for concrete input schemas and the Workflow history import API. [Architecture](docs/architecture.md) describes the services, data flow and application responsibilities.

## PHP API

```php
use MrNewport\LaravelWorkflowTools\DefinitionFiles;
use MrNewport\LaravelWorkflowTools\DefinitionGraph;
use MrNewport\LaravelWorkflowTools\DefinitionDiff;

$files = new DefinitionFiles;
$definition = $files->read('/absolute/path/to/review.json');
$mermaid = (new DefinitionGraph)->mermaid($definition);
$report = (new DefinitionDiff)->between($definition, $nextDefinition);
```

`DefinitionFiles::encode($definition)` returns readable normalized JSON when it fits the 1 MiB file limit, and compact JSON for larger definitions within that same bound. Console export uses the identical encoder, so accepted current definitions can be exported and read again. An oversized historical snapshot fails explicitly; the PHP Workflow API remains available for application-specific archival handling.

Graph output includes `securityLevel: strict`, `htmlLabels: false`, an accessible title and a description. Retain those settings when rendering it with Mermaid. `DefinitionGraph::text($definition)` or `workflow:graph file.json --format=text` supplies an equivalent transition list for environments that do not display diagrams. Configure renderer resource limits for the graph size you admit, and offer the text alternative alongside complex diagrams.

## Tests

Run `composer test` after installing development dependencies. `composer lint` checks Laravel formatting, `composer format` applies it, and `composer analyse` runs Larastan at level 6; `composer check` runs all three gates. Tests cover file limits, shared-parser validation, diagrams, definition diffs, publication fingerprints, replay, version conflicts, snapshot verification and import planning.

The GitHub Actions matrix runs PHP 8.3/8.4/8.5 against Laravel 12/13 with the corresponding Testbench major, SQLite and MySQL 8.4, strict Composer validation, package tests and a production dependency advisory check. Additional PHP 8.3 lanes resolve the lowest compatible secure dependencies for each framework major. MySQL tests reset only the explicitly named disposable `workflow_tools_test` database when `WORKFLOW_TOOLS_MYSQL_DATABASE=workflow_tools_test` is set; connection settings use `MYSQL_TEST_HOST`, `MYSQL_TEST_PORT`, `MYSQL_TEST_SOCKET`, `MYSQL_TEST_USERNAME` and `MYSQL_TEST_PASSWORD`. Standalone CI requires resolvable Flow 2.2 and Workflow 1.0 releases. Local verification does not constitute a remote CI pass.

## References

- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases)
- [Laravel Flow](https://github.com/MrNewport/laravel-flow)
- [Mermaid flowchart syntax](https://mermaid.js.org/syntax/flowchart.html)

## License

MIT. See [LICENSE](LICENSE).

## Full documentation

Read the [complete handbook](docs/HANDBOOK.md), [feature coverage index](docs/FEATURES.md) and executable examples for installation, every public API/command, host policies, data compatibility, end-to-end review and recovery.
