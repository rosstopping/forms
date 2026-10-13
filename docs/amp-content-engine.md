# Amp content engine

Status: opt-in pilot, added October 2026. Hosted GitHub Copilot remains the default engine for every website.

## What it does

An admin can choose the content engine for each website under **Content → Automation → Content engine**, next to the Copilot task limit. When a site uses Amp, all of these stay the same: scheduling, queue and target selection, the 14-day cooldown, repository preflight, budgets, prompts, the GitHub webhook and SEO impact tracking. Only the step that hands the prompt to an agent changes.

```
content:dispatch / Generate now
        │
        ▼
StartContentGeneration ── selection, budgets, prompt (unchanged)
        │  snapshots engine = plan.content_engine
        ├── copilot → CopilotAgentClient::startTask → SyncContentGeneration (unchanged)
        └── amp     → adds the delivery contract to the prompt, sets head_branch=sitewell/content-{id}
                      → RunAmpContentGeneration (queue connection "amp-content")
                          → resources/amp-worker (Amp SDK, executor "orb", project = repository full_name)
                              Amp clones the repo, pushes sitewell/content-{id}, runs gh pr create --draft
                          → Sitewell finds that PR through its own GitHub App (same repository ID,
                            same default branch, not a fork) → pull_request_open + ready email
                          → GitHub webhook → merge → SEO impact tracking (unchanged)
```

- Each run keeps the engine it started with, so changing the setting only affects new runs.
- Amp never falls back to Copilot. If Amp is selected but unavailable on the server, scheduled runs pause and display the reason, and manual runs are skipped with a visible reason.
- Amp, not Sitewell, pushes the branch and opens the draft pull request, using the Amp account's GitHub connection. A person reviews and merges every PR in GitHub.
- Sitewell records the Amp thread ID as soon as the worker reports it. Admins see a **View Amp thread** link in Activity.
- Retry rules for a failed Amp run:
  - If no Amp thread was recorded, Amp never started, so retrying starts a new Amp run.
  - Once a thread exists, retrying only checks GitHub for the run's pull request. It never starts a second paid run on the same branch.
- If Amp decides nothing is eligible, for example because a page is in its cooldown after a change made outside Sitewell, it ends its reply with `SITEWELL_RESULT: NO_ELIGIBLE_WORK` and opens no PR. Sitewell then:
  - marks the run **skipped**, using Amp's explanation as the reason;
  - returns the run's requests to the queue;
  - releases the run's budget reservation and cooldown.

  Any other finish without a PR is marked **failed** and keeps the requests, because Sitewell can't tell whether work was partly delivered.
- The monthly "Copilot tasks" limit counts Amp runs too, because both count as automation task starts.

## Deployment prerequisites

1. Run `php artisan migrate`. This adds `content_plans.content_engine` (default `copilot`) and the Amp fields on `content_generations`.
2. Amp account:
   - Create a dedicated `AMP_API_KEY` (an Amp workspace or service account with spend limits is recommended).
   - Connect that account to GitHub and install the Amp GitHub App on the owner of each pilot repository.
   - Limit the installation to the pilot repositories only.
3. Create an Amp project for each Amp-enabled repository, for example `amp projects create rosstopping/digizu`. The run passes the repository's `owner/repo` as the Amp project. Without a project it fails with "No accessible Amp project matches …".
   - Check that the project's base branch matches the Sitewell repository's default branch.
   - Add any build tools the site needs (Digizu's build uses a `static` executable) to the project's orb setup, so Amp can run the site's checks.
4. On the server that runs the Amp queue, install Node.js 22.12 or newer, then run `npm ci` in `resources/amp-worker`. This installs the pinned `@ampcode/sdk` and its Amp CLI.
5. Set the following in `.env`:
   ```
   AMP_CONTENT_ENABLED=true
   AMP_API_KEY=...
   AMP_CONTENT_MODE=medium             # low | medium | high | ultra
   AMP_CONTENT_TIMEOUT_SECONDS=3600
   AMP_CONTENT_NODE_BINARY=node        # absolute path if node is not on the worker's PATH
   ```
6. Run a dedicated worker for the long-running Amp jobs:
   ```
   php artisan queue:work amp-content --queue=amp-content --timeout=3900 --tries=30
   ```
   - The `amp-content` connection uses the database driver. Its `retry_after` is set to `AMP_CONTENT_TIMEOUT_SECONDS + 900`, so a running job is not handed to another worker.
   - Keep `--timeout` above `AMP_CONTENT_TIMEOUT_SECONDS + 300`.
   - Default workers do not consume this queue.

## Piloting on Digizu

1. Complete the prerequisites above for `rosstopping/digizu`.
2. In Sitewell, open the Digizu website, go to **Content → Automation**, set **Content engine** to **Amp**, and save. Other sites stay on Copilot.
3. Use **Generate now** for one queued request and watch the run in Activity:
   - Open the Amp thread link while it runs.
   - When the draft PR appears, review it in GitHub.
4. To switch back, select **GitHub Copilot**. Runs already in progress finish on Amp.

## Security constraints and open issues

- **GitHub identity and reach.** Pushes and PRs come from the GitHub account connected to the Amp API key, not from the Sitewell GitHub App. Inside an orb, Amp's Git credential helper can reach every repository that this connection can reach, not only the site's repository. The following limit this, but none of them is a hard boundary:
  - the explicit Amp project;
  - the delivery contract in the prompt;
  - Sitewell's check that the PR is in the site's repository against its default branch.

  Keep the Amp GitHub App installation limited to pilot repositories, and do not enable Amp for customer-owned repositories until this is resolved.
- **Self-approval.** GitHub does not let a PR author approve their own PR. If the Amp key belongs to the same account that reviews the PRs, branch protection that requires approvals will block merging.
- **No Sitewell-side diff validation.** Sitewell does not inspect the diff before the PR opens. The prompt forbids changes to `.github/`, CI, credentials and environment files, but only human review enforces this.
- **CI on pushed branches.** Workflows triggered by pushes or PRs on the branch can run agent-authored code with repository secrets. Check each repository's workflow triggers and environment protections before enabling Amp.
- **Data sharing.** Prompts include site content requests, Search Console rows and competitor context. That data, plus the repository, is processed in private Amp threads.
- **Cost.** Sitewell records the token usage Amp reports, but it does not cap spend. Use Amp workspace spend limits. A run occupies one `amp-content` worker for up to the configured timeout.
- **Secrets on the server.** The worker process receives only `PATH`, `HOME`, `LANG` and `AMP_API_KEY`; other application environment variables are removed. `AMP_API_KEY` is still stored in the application's `.env`.
- **Live verification.** No live Amp orb run against Digizu has been performed from Sitewell yet. Automated tests fake the worker and GitHub.
