# Customer-owned repositories with Copilot SDK

Status: fixture runner and customer GitHub connection flow implemented and tested locally; live external-account acceptance, real-model benchmark, isolated repository execution and PR publishing remain pending.
Date: 28 September 2026.
Branch: `feature/copilot-sdk-repositories`.

## Outcome

A customer connects a repository in their own GitHub account or Bitbucket Cloud workspace. Sitewell prepares a requested website change, runs the Copilot SDK in an isolated environment, and opens a pull request in that repository. Ross's personal account requires no repository access. The customer reviews and merges through their normal process.

Default proposal: Sitewell funds model usage through Copilot SDK BYOK. Customers need repository authorisation, not a Copilot subscription or model-provider key. This is a proposed implementation direction, not a tested production capability.

## Scope and boundaries

- First slice: one approved audit remediation against an existing customer-owned GitHub repository.
- Second provider: Bitbucket Cloud, using the same worker and task contract.
- Later: content generation and existing website-builder workflows after the remediation path proves reliable.
- Keep the current hosted Copilot Agent Tasks engine available for existing sites. Select an engine per run; never silently switch a failed run to another engine and create duplicate work.
- Existing review requirements remain: create pull requests, never automatically merge or deploy. Existing customer CI/deployment remains responsible for publishing.
- GitLab, self-hosted GitHub/Bitbucket, repository migration and new hosting integrations are outside the first release.

## What exists today

- `app/Services/CopilotAgentClient.php` starts and polls hosted GitHub Agent Tasks using a requesting user's GitHub authorisation.
- `app/Jobs/StartCopilotRemediation.php` and `SyncCopilotRemediation.php` track remediation through that API.
- `StartContentGeneration.php`, `SyncContentGeneration.php`, `GenerateWebsiteHealthReport.php` and `app/Services/WebsiteBuilder.php` also depend on that engine.
- `GithubAppClient.php` already supports installation tokens, repository discovery and pull-request inspection.
- `GithubConnectionController.php` and `WebsiteRepositoryController.php` provide installation and repository selection flows, but need a full customer authorisation and tenancy review.
- `WebsiteRepository` is coupled to a GitHub installation and GitHub-specific deployment settings.
- Existing tests include `GithubIntegrationTest`, `RemediationCompletionTest`, `ContentGenerationTest`, `WebsiteBuilderTest` and `GithubActionsDeploymentTest`.

Reuse prompt generation, audit evidence, approval flows and existing history. Add an execution layer rather than replacing those product workflows.

## Proposed architecture

1. Laravel authenticates the customer, checks website management permission and snapshots an approved task.
2. A repository adapter resolves the provider, connection, repository identity, base branch and exact commit SHA.
3. A durable job requests an isolated workspace and checks out that commit with access limited to the selected repository.
4. A Node/TypeScript worker invokes the Copilot SDK with Sitewell-funded model access and explicit file, test and command permissions.
5. The worker returns a patch, execution events, test results and usage measurements.
6. A controlled publisher rechecks connection permission, validates the patch, pushes a dedicated branch and creates a pull request through the provider adapter.
7. Provider events update PR state. Laravel displays progress, failures, test results and the review link.
8. Workspace and temporary credentials are destroyed according to a bounded retention policy.

The SDK is the coding engine, not the repository authorisation system or the tenant security boundary. Keep repository write credentials and model credentials away from arbitrary project commands where possible; use a credential broker/model proxy and a separate publisher.

## Contracts to settle before coding

### Repository connection

Provider, tenant/website ownership, external account and repository IDs, installation/authorisation reference, connection health and granted capabilities. Stable provider IDs are authoritative; names and clone URLs are metadata. Preserve existing GitHub fields through an additive migration and backfill.

Adapters cover repository discovery/verification, checkout access, branch publishing, PR creation/status, and webhook verification. Bitbucket access scopes differ from GitHub installation scopes and must be verified explicitly.

### Execution run

Run ID, website/repository ID, engine, approved task snapshot, base SHA, project path, branch name, worker version, session reference, timestamps, cancellation state, test results, PR identity, usage and redacted error details. Persist identifiers in queue payloads, never credentials.

Suggested stages: queued, preparing, running, validating, publishing, awaiting_review, completed, failed, cancelled. Keep execution completion separate from PR merge and deployment state.

### Worker boundary

Authenticated dispatch and result/event messages bound to run, attempt and tenant. Use leases/heartbeats, monotonic event sequence numbers and idempotency keys. Duplicate delivery or retry must not create another branch/PR. Reconcile unknown publish outcomes before retrying.

Bound wall time, model spend, output size, disk and concurrency per run/customer. Support cancellation and worker loss. Reject paths outside the checked-out repository/project path, including symlink escapes.

## Delivery milestones

### 0. Runtime and hosting spike

- Verify current SDK/runtime versions, BYOK model compatibility and deployment requirements; pin tested versions.
- Select one model/provider and isolated execution platform using a small coding task benchmark.
- Confirm supported commercial use and provider data handling before processing customer code.
- Prove local-file edits and test execution without personal GitHub login or ambient credentials.
- Choose worker packaging/location before adding a new base directory or dependency.

Exit: reproducible sandbox run, captured test output, cancellation, cleanup and measured usage. No production connections required.

### 1. GitHub connection independent of personal access

- Verify the GitHub App can be installed by external customers; check app visibility and minimal permissions.
- Separate repository installation consent from optional hosted-Copilot user authorisation.
- Bind callbacks to authenticated tenant, one-time state and expiry; verify the installing customer's right to select the repository.
- Issue short-lived repository-scoped tokens on demand; handle uninstall, suspension, revocation and repository removal.
- Ensure one installation used by multiple website members does not transfer ownership or expose other customers' repositories.

Exit: a customer can select a private repository Ross cannot access, and another customer cannot discover or use it.

### 2. First SDK remediation pull request

- Add the execution contract and SDK engine behind a disabled-by-default feature flag.
- Implement durable dispatch, workspace lifecycle, progress, timeout/cancellation and controlled publishing.
- Reuse `RemediationPromptGenerator`; snapshot approved scope and base SHA.
- Restrict the first task to one small audit fix with a known validation command.
- Return a real PR with diff summary and validation evidence; handle no-change and failed-test outcomes explicitly.

Exit: one end-to-end task on the external private repository, with no personal account access, no customer Copilot subscription and no automatic merge.

### 3. Bitbucket Cloud

- Add OAuth authorisation, encrypted/refreshable credentials, repository selection and permission checks.
- Implement Bitbucket checkout, branch/PR publishing and authenticated event handling.
- Reuse the same task and worker contract. Disable assumptions about GitHub URLs, numeric PR IDs, Actions and GitHub task polling.

Exit: the same remediation scenario creates a reviewable Bitbucket PR without mirroring the repository into GitHub.

### 4. Product rollout

- Display connection health, execution stages, failure recovery, validation and PR links in Sitewell.
- Add usage reporting and enforce measured plan budgets/concurrency limits.
- Migrate content generation next, preserving target selection, cooldowns, sitemap/internal-link requirements and review controls.
- Adapt builder/report integrations separately; preserve GitHub Actions artifact deployment behaviour on existing sites.
- Pilot with selected customers before wider enablement. Keep historical hosted-agent runs readable.

## Verification and release gates

- Laravel/Pest: tenant isolation, callback replay/expiry, provider selection, revoked permissions, run state transitions, idempotent publishing, retry reconciliation, cancellation and engine selection.
- Worker tests: filesystem/network boundaries, untrusted repository instructions, secret redaction, resource limits, cleanup after failure and tool permissions.
- Provider contract tests: fixtures for GitHub/Bitbucket APIs and webhooks, duplicate events, renamed repositories and inaccessible branches.
- Integration: disposable repositories supplied by an authorised customer/test account; prove personal-account independence on both providers.
- Regression: existing GitHub integration, remediation completion, content generation, builder and artifact deployment tests.
- Measure representative task success, duration and total model/compute cost before setting production quotas. Do not assume every framework can build without project-specific setup.
- Enable per website. Rollback disables new SDK dispatch, cancels/drains existing runs and preserves PR/history; it must not resubmit automatically to the hosted engine.

## Decisions still open

- Isolated worker host and lifecycle API.
- Initial model/provider and per-run spend/time budgets.
- Worker repository/package location and dependency approval.
- Build-environment support for the first pilot repositories.
- Retention period for code workspaces, logs and patch artifacts.
- Suitable external GitHub and Bitbucket test accounts/repositories for the live acceptance test.

Proceed with mock/provider-independent scaffolding before these live credentials are available. Do not begin customer-code execution until isolation and authorisation gates pass.

## Sources checked during exploration

These describe upstream capabilities, not proof of a working Sitewell integration. Revalidate against the versions chosen in milestone 0.

- [Copilot SDK architecture and supported SDKs](https://github.com/github/copilot-sdk)
- [SDK authentication](https://docs.github.com/en/copilot/how-tos/copilot-sdk/auth/authenticate)
- [BYOK configuration and limitations](https://docs.github.com/en/copilot/how-tos/copilot-sdk/auth/byok)
- [Multi-tenancy and server deployments](https://docs.github.com/en/copilot/how-tos/copilot-sdk/setup/multi-tenancy)
- [Organisation server-to-server authentication](https://docs.github.com/en/copilot/how-tos/copilot-sdk/auth/server-to-server-tokens) — optional future engine authentication, not the default proposal.
- [GitHub App installation authentication](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app-installation)
- [Bitbucket Cloud OAuth](https://support.atlassian.com/bitbucket-cloud/docs/use-oauth-on-bitbucket-cloud/)
- [Bitbucket pull requests](https://developer.atlassian.com/cloud/bitbucket/rest/api-group-pullrequests/)

## Implementation progress — first slice

Implemented in this branch:

- Independent Node worker package at `resources/copilot-worker`, pinned to `@github/copilot-sdk` 1.0.14 with its runtime dependencies locked. It is not included in the browser bundle.
- Versioned stdin/stdout request/result contract with run identity, mode, fixture, bounded limits, verification, synthetic changes and reported usage.
- `copilot-sdk:verify` Artisan command and default-off `config/copilot_sdk.php` gate for SDK execution. The first slice did not change production routes, repository selection or hosted task dispatch. The second slice below adds an independently gated repository connection flow.
- A synthetic title-change fixture stored entirely in memory. Three explicitly allowed, uniquely named tools read/edit/check that fixture. No filesystem tool, shell command, GitHub tool or arbitrary repository input is exposed.
- Isolated temporary runtime state, no logged-in-user fallback, no inherited GitHub credentials in the runtime environment, denied ambient permissions, and cleanup on success/failure/cancellation.
- Wall-time, tool-call and reported-token limits. The token limit is a reactive guard after usage events, not a guaranteed monetary cap; a provider/proxy spend limit is still required for customer execution.
- A real SDK integration test against a scripted loopback model endpoint. This validates SDK startup, tool registration/dispatch, edits, independent verification and usage events without a paid model call. This is not a model-quality benchmark.

The fixture runs locally because it cannot execute repository code. This does not establish a production sandbox boundary. Docker/Podman was not available on the development machine during this slice; selecting and testing the isolated repository execution host remains a release gate.

### Run the checks

Use the project's PHP 8.4 runtime and Node 22.12+.

```sh
npm ci --prefix resources/copilot-worker --ignore-scripts
npm test --prefix resources/copilot-worker
npm run test:runtime --prefix resources/copilot-worker
php artisan test --compact tests/Feature/CopilotSdkFixtureTest.php
php artisan copilot-sdk:verify
COPILOT_SDK_ENABLED=true php artisan copilot-sdk:verify --probe
```

The runtime integration test opens a loopback-only server. The default Artisan command performs deterministic simulation and makes no SDK/model call. `--probe` starts and pings the real bundled runtime without requesting a model completion.

For a real model fixture run, configure `COPILOT_SDK_ENABLED=true`, `COPILOT_SDK_PROVIDER=anthropic` or `openai`, `COPILOT_SDK_MODEL`, and a model API key securely, then run. OpenAI reuses `OPENAI_API_KEY` when `COPILOT_SDK_API_KEY` is unset or empty; an explicit SDK key takes precedence:

```sh
php artisan copilot-sdk:verify --live
```

This last command incurs provider usage and edits only the synthetic fixture. No key has been provisioned or paid model benchmark run as part of this implementation. API keys are supplied in the worker environment, never CLI arguments or JSON output; the fixture exposes no tool capable of reading that environment. Production repository execution will need the separate credential broker/model proxy described above.

## Implementation progress — customer GitHub connections

Implemented for all Sitewell administrators automatically; ordinary customers retain the existing flow:

- Customer GitHub authorisation verifies identity and repository access without starting a hosted Copilot task or checking a Copilot subscription. Model billing remains separate. This uses the existing GitHub App and OAuth configuration; a new minimal-permission App has not been provisioned.
- Repository discovery uses GitHub's user-to-installation APIs, intersecting App access with the signed-in customer's current access. Only writable repositories from active installations of this App with contents/PR write permission are selectable.
- Saving a selection rechecks live GitHub permissions. A shared installation retains its original installer; access by another authorised member does not transfer ownership. Revocation for one user does not globally retire that installation.
- Installation and OAuth callback states are single-use, expire after 15 minutes, and bind to the authenticated user and browser session. Website management access is checked again on return. Installation IDs are verified through GitHub before being persisted.
- Repository pagination, revoked-access recovery, foreign/suspended installations, read-only repositories, callback replay/expiry, changed website ownership and shared installations have automated coverage.

No schema migration is required. The new connection flow is admin-only, and the existing hosted task engine remains unchanged. Enabling repository connections does not enable SDK remediation execution. Production needs a persistent cache shared across application instances, supporting atomic locks, for connection state.

Verification: 67 PHP tests passed (255 assertions), covering the new flow, existing GitHub integration, fixture runner and remediation completion. Pint and diff checks are also required before review.

```sh
php artisan test --compact tests/Feature/GithubCustomerConnectionTest.php tests/Feature/GithubIntegrationTest.php tests/Feature/CopilotSdkFixtureTest.php tests/Feature/RemediationCompletionTest.php
```

GitHub responses are faked in these tests. Live installation settings, external-account consent and a private repository Ross cannot access still require acceptance testing. Bitbucket support is not implemented.

### Next implementation slice

1. Verify the GitHub App settings and consent flow with an authorised external test account and private repository.
2. Choose a disposable execution host and prove isolation for actual file editing and project test commands.
3. Run the small real-model benchmark with an explicitly configured provider and budget.
4. Add persisted remediation execution and controlled PR publishing after those boundaries are verified.

## Forge admin connection pilot

The customer repository flow is enabled automatically for every Sitewell admin. No connection feature flag, environment variable or user-ID allowlist is required. Non-admin users remain on the existing connection flow. All new-flow callbacks and repository listing/saving use the same role check; losing the admin role during consent rejects the callback. This supersedes the original opt-in flag and allowlist design.

Deploy the merged code using the normal Forge deployment process. No database migration or worker-package installation is required for this connection-only test. SDK execution remains disabled by default and needs no environment entry to stay disabled. Existing hosted task execution has not been replaced. Do not launch remediation/content tasks as part of this connection test: those actions still use the existing hosted engine.

Test target: private `sitewellross/test`, using DigizuAudit. The App's public API now reports contents and pull_requests write permission; an existing installation must also accept the permission update. The `rosstopping` CLI login received 404 for this repository, which is consistent with lack of access but does not establish private-repository existence.

Acceptance steps:

1. Sign into Sitewell as an admin and use a dedicated test website's Content repository connection controls.
2. Complete GitHub consent as `sitewellross`, selecting only the private `test` repository for the App. Start from Sitewell so the callback includes its bound state; do not start this test from a bare App installation link.
3. Confirm `sitewellross/test` appears and can be saved. Confirm it does not need to be shared with `rosstopping`.
4. Reconnect or refresh permissions to verify the shared installation retains its original installer. Verify read-only or inaccessible repositories cannot be selected.
5. Confirm an ordinary customer still uses the original flow. Admin testing does not replace the later normal-customer acceptance test.

Rollback uses a code deployment restoring the previous connection behaviour; there is no connection environment toggle. Already saved repository connections remain unless explicitly disconnected.

Isolated customer-code execution, paid model benchmarking and SDK pull-request publishing remain future milestones. The agency marketing work on main should be revisited after this Copilot milestone.

## Implementation progress — constrained live repository test

The first repository test is now available as an operator command, `copilot-sdk:test-repository`. It does not change the engine used by the Content workspace or audit buttons. The approved live target is `sitewellross/test`, with `index.html`'s title changed to `Sitewell SDK test`.

This slice deliberately does not execute repository code. It reads one existing regular `.html` Git blob (at most 8 KB) at a captured commit, exposes that document through three in-memory SDK tools, and independently validates the exact title-only replacement in PHP before publishing. Symlinks, submodules, traversal, incomplete trees and files outside the connected project directory are rejected. The pinned SDK starts in empty mode without ambient GitHub authentication, built-in tools or repository instructions. Docker is not needed for this constrained test; this is **not** proof of an OS sandbox or support for arbitrary coding/build tasks.

A separate GitHub publisher rechecks the requesting admin's live repository access and connection identity, obtains a repository-scoped installation token, writes a dedicated branch and opens a **draft PR**. It never merges or updates the default branch. The base tree is preserved; only the approved file is replaced. Model credentials and GitHub credentials are not provided to repository tools. Each run records the snapshot, exact change, reported token usage, branch, commit and PR URL in `copilot_sdk_test_runs`; file snapshots are encrypted. Token counts are reactive limits, not a guaranteed monetary cap. Set an appropriate spend limit with the chosen model provider.

Publication can be resumed by run UUID after network errors without another SDK/model call. Existing branch heads must match the persisted commit; changed branches are never overwritten. An advanced base branch blocks new publication. A previously created PR can still be reconciled. If the SDK run itself fails, it is not automatically retried or sent to the hosted engine.

### Forge preparation

The production connection lives on Forge; no live repository/model acceptance test has been run from the local workspace. Run these in the deployed Sitewell application directory, with Node 22.12+ and PHP 8.4:

```sh
php artisan migrate --force --no-interaction
npm ci --prefix resources/copilot-worker --ignore-scripts
```

Configure the existing SDK settings securely in Forge: `COPILOT_SDK_ENABLED=true`, `COPILOT_SDK_PROVIDER` (`anthropic` or `openai`), `COPILOT_SDK_MODEL`, and the provider API key. When the provider is `openai`, the worker reuses the existing `OPENAI_API_KEY`; no duplicate key is needed. `COPILOT_SDK_API_KEY` remains an optional override and is required for Anthropic. These are worker/model settings, not per-user or per-website rollout flags. Never paste the API key into a command or commit it. Refresh cached configuration using the site's normal deployment process.

Verify the runtime without a paid model request:

```sh
php artisan copilot-sdk:verify --probe
```

Replace `WEBSITE_ID` and `ADMIN_ID` with the connected test website and the Sitewell admin who completed GitHub consent. The readiness command verifies access and the file without a paid SDK call or repository write:

```sh
php artisan copilot-sdk:test-repository WEBSITE_ID --user=ADMIN_ID
```

The defaults are `--path=index.html` and `--title='Sitewell SDK test'`. Once ready, the explicit publish option runs the paid SDK test and opens the draft PR:

```sh
php artisan copilot-sdk:test-repository WEBSITE_ID --user=ADMIN_ID --publish
```

Review the printed PR URL and confirm the diff contains only the title. No merge is required to verify the SDK integration. If publishing is interrupted, use the printed run UUID:

```sh
php artisan copilot-sdk:test-repository WEBSITE_ID --user=ADMIN_ID --resume=RUN_UUID --publish
```

Automated verification covers the real bundled SDK against a local model stub, restricted tools, independent output validation, access revocation, stale bases, scoped tokens and unknown PR outcomes. A successful model-stub test is not a paid model benchmark. Disposable execution hosting, shell/build support, website UI controls and general remediation/content integration remain subsequent work.
