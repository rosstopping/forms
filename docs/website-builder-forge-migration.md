# Website Builder: Netlify to Laravel Forge Migration

## Objective

Replace the website builder's Netlify provisioning with Laravel Forge while retaining the existing seamless experience:

- create and populate a GitHub repository automatically
- provision hosting automatically
- provide an immediate HTTPS demo URL
- redeploy automatically when changes are pushed to the configured branch
- make provisioning and deployment failures visible in Sitewell

## Decision

Use the current Laravel Forge API v2, preferably through `laravel/forge-sdk` v4, against a pre-existing Forge server. Do not build against the legacy `/api/v1` API, which is scheduled to be discontinued on August 31, 2026.

Each generated website will be created as a static/HTML Forge site. Forge's free HTTPS `*.on-forge.com` domain will be used as the initial demo URL. A custom domain can be attached later without changing the builder workflow.

## Proposed workflow

1. Generate the initial Eleventy and Tailwind scaffold.
2. Create and populate the GitHub repository using the existing GitHub integration.
3. Create a static/HTML site on the configured Forge server.
4. Connect the Forge site to the new GitHub repository and its default branch.
5. Configure the site's web directory as `/_site`.
6. Install a deployment script that installs Node dependencies and builds the static site.
7. Enable Forge push-to-deploy for the configured branch.
8. Wait for site provisioning and the initial deployment to succeed.
9. Save the generated `*.on-forge.com` hostname as the website's primary domain.
10. Start the existing Copilot design task.
11. When the Copilot pull request is merged, Forge automatically deploys the new commit.
12. Receive Forge deployment webhooks and update the build/deployment status in Sitewell.

An initial standard deployment script should be validated with Forge, but is expected to resemble:

```bash
cd $FORGE_SITE_PATH

git pull origin $FORGE_SITE_BRANCH
npm ci
npm run build
```

## Prerequisites

- A provisioned Forge web server with sufficient capacity for the expected number of sites.
- Node.js and npm available to Forge deployment scripts.
- A Forge organization and server selected for generated sites.
- A Forge API token with only the permissions required to manage sites and deployments on that server.
- A Forge GitHub source-control connection that can access repositories created by the Sitewell GitHub App.
- A collision-safe naming strategy for Forge preview domains.
- A retention and removal policy for abandoned demo sites and repositories.

## Investigation spike

Before implementing the full migration, prove the following end to end with one disposable repository and Forge site:

1. Create a repository through the existing Sitewell GitHub App flow.
2. Confirm the Forge GitHub connection can immediately access that newly created repository.
3. Create a static Forge site through API v2 or SDK v4.
4. Confirm the API exposes or returns the allocated `on-forge.com` hostname.
5. Configure `/_site` as the web directory and run the Eleventy build successfully.
6. Confirm the preview URL is available over HTTPS.
7. Push another commit and verify push-to-deploy runs automatically.
8. Register a deployment webhook and verify its signature/authentication options and payload.

Repository access is the main integration risk. If Forge cannot automatically access newly created repositories, investigate these options in order:

1. Adjust the Forge GitHub connection so it can access all repositories created in the target account or organization.
2. Use repository-specific deploy keys, automating the Forge deploy-key and GitHub deploy-key exchange.
3. Use a custom Git repository connection and trigger Forge deployments explicitly after pushes, accepting that Forge cannot provide native push-to-deploy for custom providers.

## Application changes

### Forge integration

- Replace `App\Services\NetlifyClient` with a Forge-specific client/service.
- Prefer the official `laravel/forge-sdk` v4 if adding the dependency is approved.
- Otherwise use Laravel's HTTP client against API v2 with explicit timeouts, bounded retries, and actionable exceptions.
- Keep the API token in environment configuration; never persist or queue it.
- Configure the organization slug and server ID rather than resolving them for every build.

Expected environment configuration:

```dotenv
FORGE_API_TOKEN=
FORGE_ORGANIZATION=
FORGE_SERVER_ID=
```

### Generated repository

- Remove `netlify.toml` from `StaticWebsiteGenerator` output.
- Keep `_site/` ignored because Forge should build it during deployment.
- Do not add Forge API credentials or deployment secrets to generated repositories.

### Persistence

Persist enough external identifiers to make provisioning idempotent, observable, and cleanable. Likely fields include:

- Forge organization slug
- Forge server ID
- Forge site ID
- Forge preview domain
- latest Forge deployment ID
- provisioning status
- deployment status
- deployment error and timestamps

The exact table placement should be decided during implementation. Forge-specific hosting metadata may deserve a dedicated model rather than expanding `WebsiteBuild` if it must outlive the initial build and support later deployments or deletion.

### Build state

Retain the durable queued-job architecture. Extend the current broad `queued`, `running`, `completed`, and `failed` visibility so that the UI can distinguish at least:

- repository creation
- Forge site provisioning
- initial deployment
- Copilot task creation
- ready
- failed

A build should not be marked ready merely because Forge accepted the site-creation request. Provisioning and the initial deployment are asynchronous and must reach a successful terminal state.

### Webhooks

- Add a Forge deployment webhook endpoint.
- Authenticate webhook requests using the strongest mechanism Forge supports; if Forge does not sign outgoing webhooks, provision a high-entropy per-site callback secret in the webhook URL or an agreed header.
- Treat webhook payloads as untrusted and verify the referenced server, site, and deployment against persisted IDs.
- Make webhook processing idempotent.
- Update deployment status, commit metadata, completion time, and any user-visible error.

### Cleanup and recovery

- Make each provisioning step retry-safe by persisting external IDs immediately after creation.
- Avoid creating a second Forge site when a queued job is retried.
- Provide an explicit cleanup path for partially created builds.
- Decide whether deleting a Sitewell website should also delete its Forge site and GitHub repository; do not make that destructive behavior implicit.
- Surface orphaned-resource cleanup failures for administrative action.

## Security and operations

- Use a narrowly scoped Forge API token and rotate it independently of GitHub credentials.
- Never include API tokens in queued payloads, generated repositories, logs, or exception messages.
- Consider enabling a Forge security rule for draft sites if previews should not be public.
- Track disk usage, memory, inodes, Nginx site count, Node build concurrency, and deployment duration on the shared server.
- Limit concurrent website-build jobs so several npm builds cannot exhaust the server.
- Define site limits and cleanup rules before offering the feature at scale.
- Account for Forge's deployment timeout when choosing build dependencies and commands.

## Testing strategy

- Unit/feature-test the Forge client with `Http::fake()` or fake the official SDK boundary.
- Test successful site creation, asynchronous provisioning, deployment success, and returned preview-domain validation.
- Test missing configuration, authentication failure, rate limiting, timeouts, malformed responses, name collisions, inaccessible repositories, and failed deployments.
- Test retries do not create duplicate Forge sites.
- Test webhook authentication, idempotency, unrelated site IDs, success, and failure payloads.
- Update `WebsiteBuilderTest` to replace Netlify expectations with Forge expectations.
- Assert generated repositories no longer contain `netlify.toml`.
- Run only the affected Pest feature tests during implementation, followed by Pint for modified PHP files.

## Suggested implementation phases

### Phase 1: Forge access spike

Complete the disposable end-to-end experiment and record the confirmed API v2 payloads, response shapes, repository-access behavior, webhook authentication, and deployment script.

### Phase 2: Client and configuration

Add Forge configuration and implement a tested Forge client/service for site creation, provisioning status, deploy-script configuration, push-to-deploy, deployment triggering/status, webhooks, and deletion.

### Phase 3: Builder migration

Replace the Netlify call in `WebsiteBuilder`, remove `netlify.toml`, persist Forge identifiers, and retain the generated Forge domain as the website's primary domain.

### Phase 4: Asynchronous lifecycle

Model the provisioning/deployment states, add polling or follow-up jobs where webhooks are insufficient, process deployment callbacks, and expose useful progress and failures in the existing builder UI.

### Phase 5: Cleanup and rollout

Add administrative cleanup/retry controls, capacity safeguards, monitoring, and documentation for Forge setup. Test with several real builds before removing Netlify configuration and credentials.

## Acceptance criteria

- Submitting a website build does not make a Netlify API request.
- A GitHub repository and Forge static site are created without manual intervention.
- Sitewell displays and stores a working HTTPS `on-forge.com` demo URL.
- The initial Eleventy site deploys successfully from GitHub.
- Merging the Copilot pull request causes Forge to deploy the new commit automatically.
- Sitewell records deployment success or a useful failure reason.
- Retrying an interrupted build does not create duplicate Forge sites.
- No GitHub or Forge credentials are persisted in queued payloads, generated repositories, or logs.

## References

- [Forge API introduction](https://laravel.com/forge/docs/api-reference/introduction)
- [Official Forge SDK](https://laravel.com/forge/docs/sdk)
- [Creating and managing Forge sites](https://laravel.com/forge/docs/sites/the-basics)
- [Forge deployments](https://laravel.com/forge/docs/sites/deployments)
- [Forge domains](https://laravel.com/forge/docs/sites/domains)
- [Forge source control](https://laravel.com/forge/docs/source-control)
