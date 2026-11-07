# Feature coverage index

| Public feature | Contract, options, failures and examples |
| --- | --- |
| PHP/Laravel support, required Workflow/Flow, optional Fields, no UI prerequisite, VCS install fallback | [Installation](HANDBOOK.md#installation-and-independent-use) |
| `workflow:validate file` | [Command reference](HANDBOOK.md#complete-command-reference), `examples/review.json`, documentation test |
| `workflow:graph file --format=mermaid|text` | [Commands](HANDBOOK.md#complete-command-reference), [graph API and accessibility](HANDBOOK.md#graph-api-and-accessible-presentation) |
| `workflow:diff before after --check` and exit 1 on change | [Commands](HANDBOOK.md#complete-command-reference), [diff schema/pointers](HANDBOOK.md#diff-api-and-output) |
| `workflow:publish file --apply --expect=`; preview defaults and immutable collisions | [Commands](HANDBOOK.md#complete-command-reference), [full review/round-trip](HANDBOOK.md#review-publish-and-round-trip-recipe) |
| `workflow:export fingerprint`, stored verification and literal bounded output | [Commands](HANDBOOK.md#complete-command-reference), [adaptive encoder](HANDBOOK.md#json-files-and-limits) |
| `workflow:legacy-plan file`, ready/issues exit codes and every metadata exception | [Planning schema](HANDBOOK.md#definition-handoff-planning), `examples/legacy-review.json` |
| `DefinitionFiles::MAX_BYTES/read/readArray/encode` | [All signatures, local path policy, UTF-8/depth/size/duplicates/numeric IDs](HANDBOOK.md#json-files-and-limits) |
| `DefinitionDiff::between` | [Strict normalized values, JSON Pointers, missing-null caveat](HANDBOOK.md#diff-api-and-output) |
| `DefinitionGraph::mermaid/text` | [Security config, label escaping, fixed IDs, metadata, equivalent text](HANDBOOK.md#graph-api-and-accessible-presentation) |
| `LegacyMigrationPlan::review` | [Schema, all accepted fields, blockers, no side effects](HANDBOOK.md#definition-handoff-planning) |
| Shared Workflow `Definition` grammar and fingerprint | [Definition identity and every bound](HANDBOOK.md#definition-grammar-and-identity) |
| `Contracts\FailureReporter::report`, default Laravel reporter | [Errors, diagnostic reference, post-commit uncertainty and customization](HANDBOOK.md#diagnostics-translation-and-reporting) |
| Provider discovery and translations | [Install](HANDBOOK.md#installation-and-independent-use), [translation overrides](HANDBOOK.md#diagnostics-translation-and-reporting) |
| Standalone console use / integrated procurement / historical handoff | [End-to-end boundaries and runnable source](HANDBOOK.md#history-handoff-and-integrated-procurement) |
| Compatibility, stored data and test commands | [Upgrade and verification](HANDBOOK.md#upgrade-and-verification) |

[DocumentationExamplesTest](../tests/DocumentationExamplesTest.php) executes the published JSON examples, actual service methods and Artisan review/publication/export commands. Existing service/regression tests cover adversarial bounds and failure reporting.
