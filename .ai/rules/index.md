# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/{Ai,Jobs,Mail,Models,Http/Controllers/Admin}/**/*Prospect*.php | .ai/rules/admin.md |
| app/{Services/Github*,Models/GithubUserAuthorization.php,Jobs/{StartCopilotRemediation,SyncCopilotRemediation}.php} | .ai/rules/app-services.md |
| app/{Services/OptimisationValueSanitizer.php,Services/PixelDeploymentDriver.php,Http/Requests/*Optimisation*.php,Http/Controllers/Admin/Optimisation*.php} | .ai/rules/controllers-admin.md |
| app/{Http/Controllers/FormSubmissionController.php,Services/SpamDetector.php} | .ai/rules/controllers.md |
| app/{Mail/FormSubmissionReceived.php,Http/Controllers/FormSubmissionSpamController.php} | .ai/rules/mail-controllers.md |
| app/{Models/PixelPageSighting.php,Services/PixelHeartbeatRecorder.php,Http/Controllers/PixelHeartbeatController.php}, public/pixel.js | .ai/rules/models-controllers.md |
| app/{Models/Optimisation*.php,Services/*Deployment*.php,Contracts/DeploymentDriver.php} | .ai/rules/models.md |
| public/pixel.js | .ai/rules/public.md |
| app/{Models/Website.php,Http/Controllers/Admin/**,Http/Requests/**} | .ai/rules/requests.md |
| app/Services/SeoIntelligence/** | .ai/rules/seo-intelligence.md |
| app/{Services/Github*,Http/Controllers/**/*Github*,Models/{GithubInstallation,WebsiteRepository,RemediationRun}.php}, app/{Services/PixelUrlNormalizer.php,Services/PixelPayloadBuilder.php,Http/Controllers/PixelPayloadController.php,Models/Optimisation.php} | .ai/rules/services-controllers.md |
| app/{Services,Jobs,Http/Controllers}/**/*Content*.php | .ai/rules/services-jobs-http-controllers.md |
| app/Services/WebsiteCrawler.php, app/Services/Pixel*.php | .ai/rules/services.md |
