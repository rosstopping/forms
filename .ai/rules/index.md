# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/{Services/Github*,Models/GithubUserAuthorization.php,Jobs/{StartCopilotRemediation,SyncCopilotRemediation}.php} | .ai/rules/app-services.md |
| app/{Http/Controllers/FormSubmissionController.php,Services/SpamDetector.php} | .ai/rules/controllers.md |
| app/{Mail/FormSubmissionReceived.php,Http/Controllers/FormSubmissionSpamController.php} | .ai/rules/mail-controllers.md |
| app/{Services/Github*,Http/Controllers/**/*Github*,Models/{GithubInstallation,WebsiteRepository,RemediationRun}.php} | .ai/rules/services-controllers.md |
| app/{Services,Jobs,Http/Controllers}/**/*Content*.php | .ai/rules/services-jobs-http-controllers.md |
| app/Services/WebsiteCrawler.php | .ai/rules/services.md |
