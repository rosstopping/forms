# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/{Models/User.php,Support/MembershipPlan.php,Http/Middleware/EnsureMembershipFeature.php,Http/Controllers/{Account/BillingController.php,StripeWebhookController.php}},config/memberships.php,routes/web.php | .ai/rules/account.md |
| app/{Actions,Http,Models,Jobs,Notifications}/**/*.php | .ai/rules/actions-http-models-jobs-notifications.md |
| app/Http/Controllers/Admin/ContentRequestController.php,tests/Feature/{ContentGenerationTest.php,AdminResourceEditingTest.php,SeoOpportunityContentRequestTest.php}, app/Http/Controllers/Admin/UserController.php,tests/Feature/AdminResourceEditingTest.php | .ai/rules/admin-feature.md |
| app/{Models/LeadTag.php,Models/FormSubmission.php,Http/Controllers/Admin/*FormSubmission*,Http/Requests/*FormSubmission*},resources/views/admin/form-submissions/** | .ai/rules/admin-form-submissions.md |
| {app/Models/WebsiteDomain.php,app/Actions/{ActivateWebsiteAuditTrial.php,VerifyWebsiteDomainFromSearchConsole.php},app/Services/{SourceWebsiteResolver.php,PixelSiteResolver.php,TurnstileVerifier.php},app/Http/Controllers/Admin/SearchConsoleController.php,database/migrations/**/*ownership*website*domain*.php,tests/Feature/{FreeSiteAuditTest.php,ContentGenerationTest.php,FormSubmissionTest.php,PixelPayloadApiTest.php}} | .ai/rules/admin-migrations-feature.md |
| app/Http/{Controllers/Admin/FormController.php,Requests/UpdateWebsiteAutoresponderRequest.php},database/migrations/**/*autoresponder*.php | .ai/rules/admin-migrations.md |
| app/{Jobs,Services/SeoIntelligence,Http/Controllers/Admin}/**/*Seo*.php,app/Models/Website.php | .ai/rules/admin-models.md |
| app/{Http/Controllers/{CalWebhookController.php,Admin/OnboardingLeadController.php},Models/{User.php,WebsiteAudit.php}},resources/views/admin/onboarding-leads/**,routes/web.php,tests/Feature/{CalWebhookTest.php,OnboardingLeadTest.php} | .ai/rules/admin-onboarding-leads-feature.md |
| app/{Mail,Models,Services,Http/Controllers}/**/*ProspectOutreach*.php,resources/views/admin/prospects/** | .ai/rules/admin-prospects.md |
| app/{Services/Content*,Jobs/StartContentGeneration.php,Console/Commands/*Content*,Http/Controllers/Admin/ContentPlanController.php,Http/Requests/UpdateContentPlanRequest.php} | .ai/rules/admin-requests.md |
| app/{Http/Controllers/Admin,Services,Jobs}/**/*Pixel*.php,resources/views/admin/{websites/**,website-health-reports/**},routes/web.php | .ai/rules/admin-services-jobs-views-adminwebsites.md |
| app/{Http/Controllers/Admin,Services,Jobs}/**/*SeoProspect*.php | .ai/rules/admin-services-jobs.md |
| {app/Support/MembershipPlan.php,app/Http/Middleware/EnsureMembershipFeature.php,app/Http/Controllers/Admin/{WebsiteController.php,WebsiteHealthReportController.php,SearchConsoleController.php},resources/views/admin/websites/**,routes/web.php} | .ai/rules/admin-views-admin-websites.md |
| resources/views/admin/websites/**,routes/web.php, resources/views/admin/websites/show.blade.php | .ai/rules/admin-websites.md |
| app/{Ai,Jobs,Mail,Models,Http/Controllers/Admin}/**/*Prospect*.php | .ai/rules/admin.md |
| app/{Http/Controllers/Admin/WebsiteAiQuestion*Controller.php,Mail/WebsiteAiQuestionReported.php,Models/WebsiteAiQuestion.php},resources/views/admin/{websites/show.blade.php,website-ai-question-report.blade.php} | .ai/rules/adminwebsites.md |
| app/{Ai/Agents/PixelOptimisationWriter.php,Services/PixelOptimisationGenerator.php,Http/Controllers/Admin/*PageOptimisationsController.php} | .ai/rules/agents-controllers-admin.md |
| app/{Ai/Agents/CompetitorAnalyst.php,Services/CompetitorBriefGenerator.php} | .ai/rules/agents.md |
| app/{Jobs/StartContentGeneration.php,Services/ContentGenerationPromptGenerator.php,Services/SeoTargetKeywordSelector.php,Models/ContentGeneration.php} | .ai/rules/app-jobs.md |
| app/{Services/Github*,Models/GithubUserAuthorization.php,Jobs/{StartCopilotRemediation,SyncCopilotRemediation}.php}, app/{Services/SearchConsoleHistoryStore.php,Services/WebsiteAiContext.php,Jobs/SyncSearchConsoleHistory.php} | .ai/rules/app-services.md |
| app/{Actions/ActivateWebsiteAuditTrial.php,Models/User.php,Http/Controllers/WebsiteAuditOnboardingController.php},resources/views/{auth/complete-website-audit-onboarding.blade.php,admin/dashboard.blade.php},tests/Feature/{FreeSiteAuditTest.php,DashboardInformationArchitectureTest.php} | .ai/rules/auth-feature.md |
| app/{Models/FormSubmissionFollowUpReminder.php,Jobs/SendLeadFollowUpReminder.php,Console/Commands/DispatchDueLeadFollowUpReminders.php,Http/Controllers/Admin/FormSubmissionController.php} | .ai/rules/commands-controllers-admin.md |
| app/{Jobs,Services,Models,Mail,Console/Commands}/**/*ProspectOutreach*.php,routes/console.php,config/outreach.php | .ai/rules/commands.md |
| app/{Models,Services,Jobs,Http/Controllers}/**/*SeoTargetKeyword*.php,app/Console/Commands/DispatchWeeklySeoSnapshots.php | .ai/rules/console-commands.md |
| app/{Http/Controllers/Admin/SearchConsoleController.php,Services/SearchConsolePropertyMatcher.php,Actions/VerifyWebsiteDomainFromSearchConsole.php},resources/views/admin/websites/search-console-property.blade.php | .ai/rules/controllers-admin-views-admin-websites.md |
| app/{Services/OptimisationValueSanitizer.php,Services/PixelDeploymentDriver.php,Http/Requests/*Optimisation*.php,Http/Controllers/Admin/Optimisation*.php} | .ai/rules/controllers-admin.md |
| app/{Models/Website.php,Http/Controllers/FormSubmissionController.php},resources/views/admin/websites/show.blade.php | .ai/rules/controllers-views-admin-websites.md |
| app/{Http/Controllers/FormSubmissionController.php,Services/SpamDetector.php}, app/{Http/Controllers/FormSubmissionController.php,Services/RedirectResolver.php} | .ai/rules/controllers.md |
| app/{Services/AutoresponderHtmlSanitizer.php,Services/FormSettingsResolver.php,Mail/FormSubmissionAcknowledgement.php},resources/{js/app.js,css/app.css,views/components/trix-editor.blade.php,views/admin/forms/show.blade.php,views/admin/websites/show.blade.php,views/emails/form-submission-acknowledgement*.blade.php} | .ai/rules/emails.md |
| app/{Http/Controllers/Admin/{WebsiteController.php,WebsiteMemberController.php},Models/Website.php},resources/views/admin/websites/show.blade.php,tests/Feature/{WebsiteMembershipTest.php,WebsiteCreationTest.php} | .ai/rules/feature.md |
| app/{Http/Controllers/Admin/*FormSubmission*,View/Composers/NavigationComposer.php,Support/WebsiteNavigation.php},resources/views/{layouts/app.blade.php,admin/form-submissions/**} | .ai/rules/form-submissions.md |
| app/{Jobs/SendFormSubmissionAcknowledgement.php,Services/FormSettingsResolver.php,Http/Controllers/FormSubmissionController.php},resources/views/{admin/forms/show.blade.php,admin/websites/show.blade.php,emails/form-submission-acknowledgement*.blade.php} | .ai/rules/forms-websites.md |
| app/{Models/Website.php,Support/MembershipPlan.php,Services/FormSettingsResolver.php,Http/Controllers/{FormSubmissionController.php,Admin/FormController.php,Admin/WebsiteAutoresponderController.php},Http/Requests/UpdateWebsiteAutoresponderRequest.php},resources/views/admin/{forms/show.blade.php,websites/show.blade.php},config/{forms.php,mail.php} | .ai/rules/forms.md |
| app/{Services/{GithubAppClient,WordPressStaticReleaseBuilder,WordPressStaticReleaseQueuer},Http/Controllers/GithubWebhookController,Models/WebsiteRepository}.php | .ai/rules/github-webhook-controller-models.md |
| app/{Services,Http/Controllers}/**/*ProspectOutreach*.php,app/Http/Controllers/ProspectReportController.php,config/outreach.php | .ai/rules/http-controllers.md |
| resources/views/{auth/**,layouts/auth.blade.php,components/auth/**},app/Http/Controllers/Auth/**,app/Http/Requests/{ResetPasswordRequest,SendPasswordResetLinkRequest}.php | .ai/rules/http-requests.md |
| app/{Jobs/GeneratePagePixelOptimisations.php,Http/Controllers/Admin/*ReportOptimisationsController.php}, app/{Jobs/BuildWebsite.php,Http/Controllers/Admin/WebsiteBuilderController.php,Models/WebsiteBuild.php} | .ai/rules/jobs-controllers-admin.md |
| app/Jobs/Sync*Copilot*.php,app/Jobs/SyncContentGeneration.php | .ai/rules/jobs.md |
| app/Http/Controllers/Admin/{WebsiteController.php,WebsiteHealthReportController.php},resources/views/admin/{websites/show.blade.php,website-health-reports/show.blade.php},resources/js/app.js | .ai/rules/js.md |
| app/{Http/Middleware/ResolveCurrentWebsite.php,Support/WebsiteNavigation.php,Models/User.php},resources/views/layouts/app.blade.php,routes/web.php | .ai/rules/layouts.md |
| app/{Mail/FormSubmissionReceived.php,Http/Controllers/FormSubmissionSpamController.php} | .ai/rules/mail-controllers.md |
| {app/Actions/StoreSitewellContactLead.php,app/Http/Controllers/OnboardingEnquiryController.php,resources/views/marketing/contact.blade.php,config/marketing.php,tests/Feature/MarketingPagesTest.php} | .ai/rules/marketing-feature.md |
| app/{Http/Controllers/FreeSiteAuditController.php,Http/Requests/StoreFreeSiteAuditRequest.php,Jobs/GenerateFreeSiteAudit.php,Mail/FreeSiteAuditResults.php},resources/views/{marketing/free-site-audit.blade.php,prospects/report.blade.php,mail/free-site-audit-results.blade.php} | .ai/rules/marketing.md |
| app/{Support/MembershipPlan.php,Http/Middleware/EnsureMembershipFeature.php,Http/Controllers/Admin/WebsiteController.php},resources/views/admin/websites/**,routes/web.php | .ai/rules/middleware-controllers-admin-views-admin-websites.md |
| app/{Models/User,Http/Controllers/Admin/{WebsiteController,UserController},Http/Middleware/ResolveCurrentWebsite}.php | .ai/rules/middleware.md |
| app/{Actions/ActivateWebsiteAuditTrial.php,Models/{WebsiteAudit.php,WebsiteDomain.php},Http/Controllers/WebsiteAuditOnboardingController.php},database/migrations/**/*website*claim*.php,tests/Feature/**/*WebsiteAudit*.php | .ai/rules/migrations-feature.md |
| app/{Enums,Models,Services,Jobs,Http/Controllers}/**/*Prospect*.php,config/outreach.php,database/migrations/**/*prospect*.php | .ai/rules/migrations.md |
| app/{Models/ContentRequest.php,Jobs/StartContentGeneration.php,Http/Controllers/Admin/{ContentRequestController.php,ContentRequestPixelController.php}},resources/views/admin/websites/show.blade.php | .ai/rules/models-controllers-admin-views-admin-websites.md |
| app/{Models/PixelPageSighting.php,Services/PixelHeartbeatRecorder.php,Http/Controllers/PixelHeartbeatController.php}, public/pixel.js | .ai/rules/models-controllers.md |
| app/{Jobs,Mail,Http/Controllers}/**/*FormSubmission*.php,app/Models/Website.php,resources/views/admin/websites/show.blade.php | .ai/rules/models-views-admin-websites.md |
| app/{Models/Optimisation*.php,Services/*Deployment*.php,Contracts/DeploymentDriver.php} | .ai/rules/models.md |
| app/{Notifications,Services}/**/*Prospect*.php | .ai/rules/notifications-services.md |
| resources/views/{emails/**,mail/**,vendor/mail/**,vendor/notifications/**,components/email-layout.blade.php} | .ai/rules/notifications.md |
| app/{Models/User.php,Http/Controllers/Admin/OnboardingLeadController.php},resources/views/{admin/onboarding-leads/**,layouts/app.blade.php},routes/web.php,tests/Feature/OnboardingLeadTest.php | .ai/rules/onboarding-leads-feature.md |
| app/{Http/Controllers/Admin/BusinessProfileController.php,Services/BusinessProfileClient.php},resources/views/admin/websites/partials/business-profile.blade.php | .ai/rules/partials.md |
| app/{Jobs,Services,Http/Controllers/Admin}/**/*SeoProspect*.php,resources/views/admin/prospect-discoveries/seo-show.blade.php | .ai/rules/prospect-discoveries.md |
| app/{Console/Commands,Jobs,Services,Models,Http/Controllers/Admin}/**/*Prospect*.php,database/migrations/**/*prospect*.php,resources/views/admin/prospecting-strategy/** | .ai/rules/prospecting-strategy.md |
| app/{Services,Jobs,Http/Controllers/Admin,Http/Requests}/**/*PersonalisedVideo*.php,resources/views/admin/prospects/**,app/Services/ProspectEngagementScorer.php | .ai/rules/prospects-services.md |
| app/{Mail,Models,Services,Http/Controllers}/**/*ProspectOutreach*.php,resources/views/mail/prospects/outreach.blade.php,resources/views/admin/prospects/**,routes/web.php | .ai/rules/prospects.md |
| public/pixel.js | .ai/rules/public.md |
| app/{Models/Website.php,Policies/WebsitePolicy.php,Http/Controllers/Admin/WebsiteMemberController.php,Http/Requests/*WebsiteMemberRequest.php},resources/views/admin/websites/**,routes/web.php | .ai/rules/requests-views-admin-websites.md |
| app/{Models/Website.php,Http/Controllers/Admin/**,Http/Requests/**} | .ai/rules/requests.md |
| app/Services/SeoIntelligence/**,app/Models/SeoOpportunity.php | .ai/rules/seo-intelligence-models.md |
| app/Services/SeoIntelligence/** | .ai/rules/seo-intelligence.md |
| app/{Services/Github*,Http/Controllers/**/*Github*,Models/{GithubInstallation,WebsiteRepository,RemediationRun}.php}, app/{Services/PixelUrlNormalizer.php,Services/PixelPayloadBuilder.php,Http/Controllers/PixelPayloadController.php,Models/Optimisation.php} | .ai/rules/services-controllers.md |
| app/Services/BacklinkAuditService.php,tests/Feature/BacklinkAuditTest.php | .ai/rules/services-feature.md |
| app/{Services,Jobs,Http/Controllers/Admin}/**/*SeoProspect*.php, app/{Services,Jobs,Http/Controllers/Admin}/**/*Competitor*.php | .ai/rules/services-jobs-http-controllers-admin.md |
| app/{Services,Jobs,Http/Controllers}/**/*Content*.php | .ai/rules/services-jobs-http-controllers.md |
| app/Services/WebsiteCrawler.php, app/Services/Pixel*.php, app/Services/{SitemapFetcher,ProspectWebsiteAnalyzer,WebsiteHealthAuditor}.php | .ai/rules/services.md |
| {app/Models/User.php,app/Http/Controllers/Admin/UserImpersonationController.php,resources/views/{admin/users/index.blade.php,layouts/app.blade.php},routes/web.php,tests/Feature/UserImpersonationTest.php} | .ai/rules/users-feature.md |
| app/{Models/User.php,Http/Controllers/Admin/{DashboardController.php,OnboardingCallController.php,UserOnboardingCallController.php,WebsiteHealthReportController.php},Http/Requests/UpdateUserOnboardingCallRequest.php},resources/views/{admin/dashboard.blade.php,admin/users/index.blade.php,layouts/app.blade.php},routes/web.php,database/migrations/**/*onboarding*progress*.php | .ai/rules/users-migrations.md |
| app/{Models/{FormSubmission,ReviewInvitation}.php,Jobs/Send*Invitation.php,Services/ReviewInvitationService.php,Http/Controllers/Admin/*ReviewInvitationController.php},resources/views/admin/form-submissions/** | .ai/rules/views-admin-form-submissions.md |
| app/{Actions/ActivateWebsiteAuditTrial.php,Enums/OnboardingLifecycleStep.php,Models/{User.php,OnboardingLifecycleMessage.php},Services/OnboardingLifecycleManager.php,Notifications/OnboardingLifecycleNotification.php,Listeners/MarkOnboardingLifecycleMessageSent.php,Console/Commands/DispatchDueOnboardingLifecycleMessages.php,Http/Controllers/OnboardingLifecycleClickController.php},database/migrations/**/*onboarding_lifecycle*.php,resources/views/admin/onboarding-leads/**,routes/{web,console}.php,tests/Feature/{OnboardingLifecycleTest.php,OnboardingLeadTest.php} | .ai/rules/views-admin-onboarding-leads-feature.md |
| app/{Services,Models}/**/*Prospect*.php,config/outreach.php,resources/views/admin/prospects/** | .ai/rules/views-admin-prospects.md |
| app/{Ai/Agents/WebsiteDataAssistant.php,Services/WebsiteAiContext.php,Http/Controllers/Admin/WebsiteAiChatController.php,Models/WebsiteAiQuestion.php},resources/views/admin/websites/show.blade.php,routes/web.php | .ai/rules/views-admin-websites.md |
| app/Http/Controllers/Admin/DashboardController.php,resources/views/admin/dashboard.blade.php | .ai/rules/views-admin.md |
| app/{Ai,Jobs,Services,Http/Controllers/Admin}/**/*Pixel*.php,resources/views/admin/{websites/**,website-health-reports/**} | .ai/rules/views-adminwebsites.md |
| app/{Jobs,Mail,Services,Console/Commands}/**/*.php,resources/views/emails/**, app/{Jobs,Mail,Services,Console/Commands}/**/*.php,resources/views/emails/**,routes/console.php | .ai/rules/views-emails.md |
| {app/Http/Controllers/MarketingController.php,config/marketing.php,resources/views/marketing/{landing,features}.blade.php,routes/web.php,tests/Feature/MarketingPagesTest.php} | .ai/rules/views-marketing-feature.md |
| app/{Http/Controllers/FreeSiteAuditController.php,Http/Requests/StoreFreeSiteAuditRequest.php,Jobs/GenerateWebsiteAudit.php,Models/WebsiteAudit.php,Services/MarketingTurnstileVerifier.php},resources/views/marketing/{free-site-audit,website-audit}.blade.php,routes/web.php | .ai/rules/views-marketing.md |
| app/{Console/Commands,Jobs,Mail,Models,Http/Controllers/Admin}/**/*Ranking*.php,resources/views/{admin/websites/**,emails/*ranking*},routes/web.php | .ai/rules/viewsadmin-websites.md |
| app/Models/Website.php,resources/views/admin/websites/**,resources/views/admin/website-health-reports/** | .ai/rules/website-health-reports.md |
| app/{Http/Controllers/Admin/WebsiteMemberController,Http/Requests/{StoreWebsiteMemberRequest,UpdateWebsiteMemberRequest}}.php | .ai/rules/website-member-controller-http-requests.md |
| app/{Services/AutoresponderHtmlSanitizer.php,Services/FormSettingsResolver.php,Mail/FormSubmissionAcknowledgement.php,Http/Controllers/Admin/{FormController.php,WebsiteAutoresponderController.php}},resources/{js/app.js,views/admin/forms/show.blade.php,views/admin/websites/show.blade.php,views/emails/form-submission-acknowledgement*.blade.php} | .ai/rules/websites-emails.md |
| {app/Http/Controllers/Admin/WebsiteController.php,resources/views/admin/websites/show.blade.php,tests/Feature/DashboardInformationArchitectureTest.php} | .ai/rules/websites-feature.md |
| app/{Models,Services}/SeoTargetKeyword*.php,resources/views/admin/websites/partials/seo-target-keywords.blade.php | .ai/rules/websites-partials.md |
| app/{Http/Controllers/Admin/WebsiteMemberController.php,Http/Requests/StoreWebsiteMemberRequest.php,Notifications/WebsiteInvitation.php},resources/views/admin/websites/show.blade.php,routes/web.php | .ai/rules/websites.md |
