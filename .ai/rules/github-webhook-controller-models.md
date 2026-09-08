---
paths:
  - 'app/{Services/{GithubAppClient,WordPressStaticReleaseBuilder,WordPressStaticReleaseQueuer},Http/Controllers/GithubWebhookController,Models/WebsiteRepository}.php'
---

# Github Webhook Controller Models

## Deploy Actions artifacts separately from editable source
When wordpress_workflow_path and wordpress_artifact_name are configured, project_path remains the source directory for Copilot. Skip source-push deployment; accept only the configured successful push/manual workflow on the publishing branch and verify run metadata plus current branch SHA via GitHub. Package artifact-root files without stripping a repository directory or applying project_path, retain static ZIP validation, and never forward installation credentials to artifact storage. Raw committed-static deployments remain supported when both Actions settings are blank.
