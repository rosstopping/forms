# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| resources/{css/app.css,js/app.js,views/admin/**,views/account/**,views/components/**} | .ai/rules/account-components.md |
| app/{Models/User.php,Support/MembershipPlan.php,Http/Middleware/EnsureMembershipFeature.php,Http/Controllers/{Account/BillingController.php,StripeWebhookController.php}},config/memberships.php,routes/web.php | .ai/rules/account.md |
| app/{Actions,Http,Models,Jobs,Notifications}/**/*.php | .ai/rules/actions-http-models-jobs-notifications.md |
| app/Http/Controllers/*Audit*Controller.php,app/Actions/ActivateWebsiteAuditTrial.php,resources/views/marketing/**,config/marketing.php | .ai/rules/actions-views-marketing.md |
| app/Http/Controllers/Admin/ContentRequestController.php,tests/Feature/{ContentGenerationTest.php,AdminResourceEditingTest.php,SeoOpportunityContentRequestTest.php}, app/Http/Controllers/Admin/UserController.php,tests/Feature/AdminResourceEditingTest.php | .ai/rules/admin-feature.md |
| app/{Models/LeadTag.php,Models/FormSubmission.php,Http/Controllers/Admin/*FormSubmission*,Http/Requests/*FormSubmission*},resources/views/admin/form-submissions/** | .ai/rules/admin-form-submissions.md |
| {app/Models/WebsiteDomain.php,app/Actions/{ActivateWebsiteAuditTrial.php,VerifyWebsiteDomainFromSearchConsole.php},app/Services/{SourceWebsiteResolver.php,PixelSiteResolver.php,TurnstileVerifier.php},app/Http/Controllers/Admin/SearchConsoleController.php,database/migrations/**/*ownership*website*domain*.php,tests/Feature/{FreeSiteAuditTest.php,ContentGenerationTest.php,FormSubmissionTest.php,PixelPayloadApiTest.php}} | .ai/rules/admin-migrations-feature.md |
| app/Http/{Controllers/Admin/FormController.php,Requests/UpdateWebsiteAutoresponderRequest.php},database/migrations/**/*autoresponder*.php | .ai/rules/admin-migrations.md |
| app/{Jobs,Services/SeoIntelligence,Http/Controllers/Admin}/**/*Seo*.php,app/Models/Website.php | .ai/rules/admin-models.md |
| app/{Http/Controllers/{CalWebhookController.php,Admin/OnboardingLeadController.php},Models/{User.php,WebsiteAudit.php}},resources/views/admin/onboarding-leads/**,routes/web.php,tests/Feature/{CalWebhookTest.php,OnboardingLeadTest.php} | .ai/rules/admin-onboarding-leads-feature.md |
| {app/Http/Controllers/FreeSiteAuditController.php,resources/views/admin/onboarding-leads/index.blade.php} | .ai/rules/admin-onboarding-leads.md |
| app/{Mail,Models,Services,Http/Controllers}/**/*ProspectOutreach*.php,resources/views/admin/prospects/** | .ai/rules/admin-prospects.md |
| app/{Services/GoogleAds*,Http/Controllers/Admin/GoogleAdsController.php,Http/Requests/StoreGoogleAdsCampaignDraftRequest.php},resources/views/admin/websites/google-ads.blade.php | .ai/rules/admin-requests-views-admin-websites.md |
| app/{Services/Content*,Jobs/StartContentGeneration.php,Console/Commands/*Content*,Http/Controllers/Admin/ContentPlanController.php,Http/Requests/UpdateContentPlanRequest.php} | .ai/rules/admin-requests.md |
| app/{Http/Controllers/Admin,Services,Jobs}/**/*Pixel*.php,resources/views/admin/{websites/**,website-health-reports/**},routes/web.php | .ai/rules/admin-services-jobs-views-adminwebsites.md |
| app/{Http/Controllers/Admin,Services,Jobs}/**/*SeoProspect*.php | .ai/rules/admin-services-jobs.md |
| app/Http/Controllers/Admin/ProspectController.php,resources/views/admin/prospects/** | .ai/rules/admin-views-admin-prospects.md |
| app/Http/Controllers/Admin/CopilotSdkRunController.php,app/Http/Controllers/Admin/WebsiteController.php,resources/views/admin/websites/partials/content.blade.php,config/copilot_sdk.php | .ai/rules/admin-views-admin-websites-partials.md |
| {app/Support/MembershipPlan.php,app/Http/Middleware/EnsureMembershipFeature.php,app/Http/Controllers/Admin/{WebsiteController.php,WebsiteHealthReportController.php,SearchConsoleController.php},resources/views/admin/websites/**,routes/web.php} | .ai/rules/admin-views-admin-websites.md |
| {app/{Models/WebsiteSetup.php,Services/WebsiteSetupService.php,Http/Controllers/Admin/WebsiteSetupController.php,Http/Requests/*WebsiteSetupRequest.php},resources/views/admin/websites/{setup.blade.php,partials/setup-*.blade.php},tests/Feature/WebsiteSetupTest.php} | .ai/rules/admin-websites-feature.md |
| app/{Services,Jobs,Console/Commands,Http/Controllers/Admin}/**/*BusinessProfile*.php,resources/views/admin/websites/partials/business-profile*.blade.php | .ai/rules/admin-websites-partials.md |
| resources/views/admin/websites/**,routes/web.php, resources/views/admin/websites/show.blade.php, resources/views/admin/websites/google-ads.blade.php, resources/views/admin/websites/google-ads*.blade.php | .ai/rules/admin-websites.md |
| app/{Ai,Jobs,Mail,Models,Http/Controllers/Admin}/**/*Prospect*.php | .ai/rules/admin.md |
| app/{Http/Controllers/Admin/WebsiteAiQuestion*Controller.php,Mail/WebsiteAiQuestionReported.php,Models/WebsiteAiQuestion.php},resources/views/admin/{websites/show.blade.php,website-ai-question-report.blade.php} | .ai/rules/adminwebsites.md |
| {config/agencies.php,app/Http/Controllers/AgencyMarketingController.php,app/Http/Requests/StoreAgencyBetaRequest.php,resources/views/marketing/agencies/**} | .ai/rules/agencies.md |
| app/{Ai/Agents/PixelOptimisationWriter.php,Services/PixelOptimisationGenerator.php,Http/Controllers/Admin/*PageOptimisationsController.php} | .ai/rules/agents-controllers-admin.md |
| app/{Ai/Agents/CompetitorAnalyst.php,Services/CompetitorBriefGenerator.php} | .ai/rules/agents.md |
| app/Http/Requests/StoreFreeSiteAuditRequest.php | .ai/rules/app-http-requests.md |
| app/{Jobs/Send*RankingReport.php,Services/GoogleAdsEmailSummary.php,Services/GoogleAdsClient.php,Mail/*RankingReport.php},resources/views/emails/*ranking-report.blade.php | .ai/rules/app-jobs-views-emails.md |
| app/{Jobs/StartContentGeneration.php,Services/ContentGenerationPromptGenerator.php,Services/SeoTargetKeywordSelector.php,Models/ContentGeneration.php} | .ai/rules/app-jobs.md |
| {app/Services/MarketingAuditScreenshot.php,package.json,.npmrc} | .ai/rules/app-services-2.md |
| {app/Services/MarketingAuditResearch.php,resources/views/marketing/audit-email-gate.blade.php} | .ai/rules/app-services-views-marketing-2.md |
| app/{Services/MarketingAudit*.php,Services/WebsiteHealthAuditor.php,Services/AiVisibility*.php},resources/views/marketing/** | .ai/rules/app-services-views-marketing.md |
| app/{Services/Github*,Models/GithubUserAuthorization.php,Jobs/{StartCopilotRemediation,SyncCopilotRemediation}.php}, app/{Services/SearchConsoleHistoryStore.php,Services/WebsiteAiContext.php,Jobs/SyncSearchConsoleHistory.php}, app/{Services/Content*,Jobs/StartContentGeneration.php}, app/{Services/ProspectPersonalisedVideo.php,Models/ProspectOutreachState.php,Mail/ProspectOutreach.php} | .ai/rules/app-services.md |
| app/** | .ai/rules/app.md |
| app/{Actions/ActivateWebsiteAuditTrial.php,Models/User.php,Http/Controllers/WebsiteAuditOnboardingController.php},resources/views/{auth/complete-website-audit-onboarding.blade.php,admin/dashboard.blade.php},tests/Feature/{FreeSiteAuditTest.php,DashboardInformationArchitectureTest.php} | .ai/rules/auth-feature.md |
| app/{Models/FormSubmissionFollowUpReminder.php,Jobs/SendLeadFollowUpReminder.php,Console/Commands/DispatchDueLeadFollowUpReminders.php,Http/Controllers/Admin/FormSubmissionController.php} | .ai/rules/commands-controllers-admin.md |
| app/Services/Jev*.php,app/Console/Commands/*Jev.php,app/Models/Jev*.php | .ai/rules/commands-models.md |
| app/{Jobs,Services,Models,Mail,Console/Commands}/**/*ProspectOutreach*.php,routes/console.php,config/outreach.php | .ai/rules/commands.md |
| resources/views/marketing/**,resources/views/components/marketing/**,config/{marketing,agencies,seo_library,ppc}.php | .ai/rules/components-marketing.md |
| app/Services/WeeklyReport*.php,app/Jobs/SendWeeklyRankingReport.php,resources/views/components/weekly-overview.blade.php | .ai/rules/components.md |
| app/{Models,Services,Jobs,Http/Controllers}/**/*SeoTargetKeyword*.php,app/Console/Commands/DispatchWeeklySeoSnapshots.php | .ai/rules/console-commands.md |
| app/{Ai/Agents/GoogleAdsCopyWriter.php,Services/GoogleAdsSuggestionGenerator.php,Http/Controllers/Admin/GoogleAdsController.php,Http/Requests/GenerateGoogleAdsSuggestionsRequest.php},resources/views/admin/websites/google-ads.blade.php | .ai/rules/controllers-admin-requests-views-admin-websites.md |
| app/{Http/Controllers/Admin/SearchConsoleController.php,Services/SearchConsolePropertyMatcher.php,Actions/VerifyWebsiteDomainFromSearchConsole.php},resources/views/admin/websites/search-console-property.blade.php | .ai/rules/controllers-admin-views-admin-websites.md |
| app/{Services/OptimisationValueSanitizer.php,Services/PixelDeploymentDriver.php,Http/Requests/*Optimisation*.php,Http/Controllers/Admin/Optimisation*.php} | .ai/rules/controllers-admin.md |
| {app/Http/Controllers/FreeSiteAuditController.php,app/Mail/WebsiteAudit*.php,resources/views/marketing/website-audit.blade.php} | .ai/rules/controllers-mail-views-marketing.md |
| {app/Http/Controllers/MarketingController.php,wordpress-plugin/sitewell-by-digizu/**} | .ai/rules/controllers-sitewell-by-digizu.md |
| app/{Models,Services,Jobs,Http/Controllers}/**/*Prospect*.php,resources/views/admin/prospects/** | .ai/rules/controllers-views-admin-prospects.md |
| app/{Models/Website.php,Http/Controllers/FormSubmissionController.php},resources/views/admin/websites/show.blade.php | .ai/rules/controllers-views-admin-websites.md |
| {config/ppc.php,app/Http/Controllers/PpcLandingController.php,resources/views/marketing/ppc.blade.php,tests/Feature/PpcLandingTest.php} | .ai/rules/controllers-views-marketing-feature.md |
| {config/seo_library.php,config/marketing.php,app/Http/Controllers/MarketingController.php,resources/views/marketing/**} | .ai/rules/controllers-views-marketing.md |
| app/{Http/Controllers/FormSubmissionController.php,Services/SpamDetector.php}, app/{Http/Controllers/FormSubmissionController.php,Services/RedirectResolver.php} | .ai/rules/controllers.md |
| app/{Services/{CopilotSdkTitleRunner,GithubSdkPublisher},Console/Commands/TestCopilotSdkRepository,Models/CopilotSdkTestRun}.php,resources/copilot-worker/** | .ai/rules/copilot-worker.md |
| resources/views/{marketing/**,layouts/marketing.blade.php,layouts/ppc.blade.php,components/marketing/**},resources/css/app.css | .ai/rules/css.md |
| app/{Services/AutoresponderHtmlSanitizer.php,Services/FormSettingsResolver.php,Mail/FormSubmissionAcknowledgement.php},resources/{js/app.js,css/app.css,views/components/trix-editor.blade.php,views/admin/forms/show.blade.php,views/admin/websites/show.blade.php,views/emails/form-submission-acknowledgement*.blade.php} | .ai/rules/emails.md |
| app/{Http/Controllers/Admin/{WebsiteController.php,WebsiteMemberController.php},Models/Website.php},resources/views/admin/websites/show.blade.php,tests/Feature/{WebsiteMembershipTest.php,WebsiteCreationTest.php} | .ai/rules/feature.md |
| app/{Http/Controllers/Admin/*FormSubmission*,View/Composers/NavigationComposer.php,Support/WebsiteNavigation.php},resources/views/{layouts/app.blade.php,admin/form-submissions/**} | .ai/rules/form-submissions.md |
| app/{Services/FormSetupChecker.php,Http/Controllers/Admin/FormSetupCheckController.php},resources/views/admin/forms/show.blade.php,tests/Feature/FormSetupCheckTest.php | .ai/rules/forms-feature.md |
| app/{Jobs/SendFormSubmissionAcknowledgement.php,Services/FormSettingsResolver.php,Http/Controllers/FormSubmissionController.php},resources/views/{admin/forms/show.blade.php,admin/websites/show.blade.php,emails/form-submission-acknowledgement*.blade.php} | .ai/rules/forms-websites.md |
| app/{Models/Website.php,Support/MembershipPlan.php,Services/FormSettingsResolver.php,Http/Controllers/{FormSubmissionController.php,Admin/FormController.php,Admin/WebsiteAutoresponderController.php},Http/Requests/UpdateWebsiteAutoresponderRequest.php},resources/views/admin/{forms/show.blade.php,websites/show.blade.php},config/{forms.php,mail.php} | .ai/rules/forms.md |
| app/{Services/{GithubAppClient,WordPressStaticReleaseBuilder,WordPressStaticReleaseQueuer},Http/Controllers/GithubWebhookController,Models/WebsiteRepository}.php | .ai/rules/github-webhook-controller-models.md |
| resources/views/{marketing/home,layouts/marketing}.blade.php | .ai/rules/homelayouts.md |
| resources/views/{emails/customer-review-invitation*.blade.php,components/email-layout.blade.php,vendor/mail/html/**} | .ai/rules/html.md |
| app/Http/Controllers/Admin/**,resources/views/admin/websites/**, app/Http/Controllers/Admin/{WebsiteController,SeoImpactController}.php,resources/views/admin/websites/** | .ai/rules/http-controllers-admin-views-admin-websites.md |
| app/Http/Controllers/Admin/ProspectController.php, app/Http/Controllers/Admin/OnboardingCallController.php, app/Http/Controllers/Admin/ContentPlanController.php, app/Http/Controllers/Admin/WebsiteController.php | .ai/rules/http-controllers-admin.md |
| app/{Services,Http/Controllers}/**/*ProspectOutreach*.php,app/Http/Controllers/ProspectReportController.php,config/outreach.php | .ai/rules/http-controllers.md |
| app/Http/Controllers/Admin/DashboardController.php,resources/views/admin/overview.blade.php,app/Http/Middleware/ResolveCurrentWebsite.php | .ai/rules/http-middleware.md |
| resources/views/{auth/**,layouts/auth.blade.php,components/auth/**},app/Http/Controllers/Auth/**,app/Http/Requests/{ResetPasswordRequest,SendPasswordResetLinkRequest}.php | .ai/rules/http-requests.md |
| {app/Http/Controllers/{FreeSiteAuditController,Admin/WebsiteAuditReviewController}.php,app/Models/WebsiteAudit.php,app/Mail/WebsiteAudit*.php,resources/views/{marketing/website-audit,admin/onboarding-leads/index,mail/website-audit*}.blade.php} | .ai/rules/indexmail.md |
| app/{Jobs/CreateGoogleAdsCampaign.php,Services/GoogleAdsCampaignCreator.php,Http/Controllers/Admin/GoogleAdsController.php,Models/GoogleAdsCampaignDraft.php},resources/views/admin/websites/google-ads.blade.php | .ai/rules/jobs-controllers-admin-views-admin-websites.md |
| app/{Jobs/GeneratePagePixelOptimisations.php,Http/Controllers/Admin/*ReportOptimisationsController.php}, app/{Jobs/BuildWebsite.php,Http/Controllers/Admin/WebsiteBuilderController.php,Models/WebsiteBuild.php} | .ai/rules/jobs-controllers-admin.md |
| app/{Jobs/GenerateWebsiteAudit.php,Services/MarketingAuditScreenshot.php,Http/Controllers/FreeSiteAuditController.php},resources/views/marketing/website-audit.blade.php | .ai/rules/jobs-controllers-views-marketing.md |
| {app/Services/{ManualContentRequest,ContentGenerationPromptGenerator,ContentWorkSelector}.php,app/Models/ContentRequest.php,app/Jobs/GenerateContentRequestPixelOptimisations.php,resources/views/admin/websites/partials/content*.blade.php} | .ai/rules/jobs-views-admin-websites-partials.md |
| app/Jobs/SendWeeklyRankingReport.php,resources/views/emails/weekly-ranking-report.blade.php | .ai/rules/jobs-views-emails.md |
| app/{Services,Jobs}/MarketingAudit*.php,app/Jobs/CheckMarketingAuditAiVisibility.php,resources/views/marketing/website-audit.blade.php | .ai/rules/jobs-views-marketing.md |
| app/Jobs/Sync*Copilot*.php,app/Jobs/SyncContentGeneration.php | .ai/rules/jobs.md |
| {resources/views/marketing/home.blade.php,resources/js/home-reveal.js,resources/css/app.css} | .ai/rules/js-css.md |
| {resources/js/cal-booking.js,resources/js/marketing-events.js,app/Http/Controllers/PpcLandingController.php} | .ai/rules/js-http-controllers.md |
| {resources/js/audit-review.js,resources/views/marketing/website-audit.blade.php} | .ai/rules/js-views-marketing.md |
| app/Http/Controllers/Admin/{WebsiteController.php,WebsiteHealthReportController.php},resources/views/admin/{websites/show.blade.php,website-health-reports/show.blade.php},resources/js/app.js | .ai/rules/js.md |
| routes/web.php,app/Http/Controllers/Admin/GoogleAdsController.php,resources/views/layouts/app.blade.php,tests/Feature/GoogleAdsConnectionTest.php | .ai/rules/layouts-feature.md |
| app/{Models/FormSubmission.php,Http/Controllers/Admin/FormSubmissionController.php,Http/Requests/*LeadRequest.php},resources/views/{layouts/app.blade.php,admin/form-submissions/**} | .ai/rules/layouts-form-submissions.md |
| app/{Http/Middleware/ResolveCurrentWebsite.php,Support/WebsiteNavigation.php,Models/User.php},resources/views/layouts/app.blade.php,routes/web.php | .ai/rules/layouts.md |
| app/{Mail/FormSubmissionReceived.php,Http/Controllers/FormSubmissionSpamController.php} | .ai/rules/mail-controllers.md |
| resources/views/mail/prospects/** | .ai/rules/mail-prospects.md |
| app/{Mail,Services}/**/*ProspectOutreach*.php | .ai/rules/mail-services.md |
| resources/views/{vendor/mail/**,emails/**,mail/**,components/email-layout.blade.php,components/prospect-audit-block.blade.php} | .ai/rules/mail.md |
| {app/Actions/StoreSitewellContactLead.php,app/Http/Controllers/OnboardingEnquiryController.php,resources/views/marketing/contact.blade.php,config/marketing.php,tests/Feature/MarketingPagesTest.php} | .ai/rules/marketing-feature.md |
| {app/Ai/Agents/Audit{BusinessProfiler,OpportunitySelector}.php,app/Services/{MarketingAudit{Opportunity,Research},ProspectWebsiteAnalyzer}.php,resources/views/marketing/website-audit.blade.php,app/Http/Controllers/FreeSiteAuditController.php} | .ai/rules/marketing-http-controllers.md |
| resources/views/marketing/website-audit.blade.php,app/{Jobs/GenerateWebsiteAudit.php,Services/MarketingAudit*.php,Models/WebsiteAudit.php} | .ai/rules/marketing-jobs.md |
| {config/ppc.php,resources/views/marketing/ppc.blade.php,resources/js/marketing-events.js} | .ai/rules/marketing-js.md |
| {config/ppc.php,app/Support/MarketingJourney.php,app/Models/MarketingConversion.php,app/Events/MarketingConversionRecorded.php,app/Http/Controllers/{PpcLandingController,CalWebhookController,FreeSiteAuditController}.php,resources/{js/marketing-events.js,views/marketing/ppc.blade.php,views/layouts/ppc.blade.php}} | .ai/rules/marketing-layouts.md |
| resources/views/{marketing/**,layouts/marketing.blade.php,components/marketing/cta.blade.php,mail/website-audit*} | .ai/rules/marketing-marketing.md |
| app/{Http/Controllers/FreeSiteAuditController.php,Http/Requests/StoreFreeSiteAuditRequest.php,Jobs/GenerateFreeSiteAudit.php,Mail/FreeSiteAuditResults.php},resources/views/{marketing/free-site-audit.blade.php,prospects/report.blade.php,mail/free-site-audit-results.blade.php} | .ai/rules/marketing.md |
| app/{Support/MembershipPlan.php,Http/Middleware/EnsureMembershipFeature.php,Http/Controllers/Admin/WebsiteController.php},resources/views/admin/websites/**,routes/web.php | .ai/rules/middleware-controllers-admin-views-admin-websites.md |
| app/{Models/User,Http/Controllers/Admin/{WebsiteController,UserController},Http/Middleware/ResolveCurrentWebsite}.php | .ai/rules/middleware.md |
| app/{Actions/ActivateWebsiteAuditTrial.php,Models/{WebsiteAudit.php,WebsiteDomain.php},Http/Controllers/WebsiteAuditOnboardingController.php},database/migrations/**/*website*claim*.php,tests/Feature/**/*WebsiteAudit*.php | .ai/rules/migrations-feature.md |
| {app/Models/GoogleAdsCampaignDraft.php,app/Services/GoogleAds{Client,CampaignCreator}.php,database/migrations/*google_ads_campaign_drafts*.php,database/migrations/*sitewell*service*campaign*.php} | .ai/rules/migrations-migrations.md |
| app/{Enums,Models,Services,Jobs,Http/Controllers}/**/*Prospect*.php,config/outreach.php,database/migrations/**/*prospect*.php | .ai/rules/migrations.md |
| app/{Models/ContentRequest.php,Jobs/StartContentGeneration.php,Http/Controllers/Admin/{ContentRequestController.php,ContentRequestPixelController.php}},resources/views/admin/websites/show.blade.php | .ai/rules/models-controllers-admin-views-admin-websites.md |
| app/{Models/PixelPageSighting.php,Services/PixelHeartbeatRecorder.php,Http/Controllers/PixelHeartbeatController.php}, public/pixel.js | .ai/rules/models-controllers.md |
| {app/Http/Controllers/GithubWebhookController.php,app/Models/WebsiteRepository.php,database/migrations/*website_repositories*.php} | .ai/rules/models-migrations.md |
| app/{Jobs,Mail,Http/Controllers}/**/*FormSubmission*.php,app/Models/Website.php,resources/views/admin/websites/show.blade.php | .ai/rules/models-views-admin-websites.md |
| app/{Models/Optimisation*.php,Services/*Deployment*.php,Contracts/DeploymentDriver.php} | .ai/rules/models.md |
| app/{Notifications,Services}/**/*Prospect*.php | .ai/rules/notifications-services.md |
| resources/views/{emails/**,mail/**,vendor/mail/**,vendor/notifications/**,components/email-layout.blade.php} | .ai/rules/notifications.md |
| app/{Models/User.php,Http/Controllers/Admin/OnboardingLeadController.php},resources/views/{admin/onboarding-leads/**,layouts/app.blade.php},routes/web.php,tests/Feature/OnboardingLeadTest.php | .ai/rules/onboarding-leads-feature.md |
| {app/Http/Requests/SendWebsiteAuditReviewRequest.php,app/Http/Controllers/Admin/WebsiteAuditReviewController.php,app/Mail/WebsiteAuditPersonalReview.php,resources/views/{admin/onboarding-leads/index,mail/website-audit*,marketing/website-audit}.blade.php} | .ai/rules/onboarding-leads-indexmail.md |
| app/{Models/WebsiteAudit*,Http/Controllers/{FreeSiteAuditController,WebsiteAuditEngagementController,PpcLandingController,CalWebhookController,Admin/OnboardingLeadController}.php},resources/{views/{marketing/website-audit,admin/onboarding-leads/index}.blade.php,js/{audit-engagement,audit-review,cal-booking}.js} | .ai/rules/onboarding-leads.md |
| resources/views/admin/websites/partials/content*.blade.php,app/Http/Controllers/Admin/ContentPlanController.php | .ai/rules/partials-http-controllers-admin.md |
| app/{Http/Controllers/Admin/BusinessProfileController.php,Services/BusinessProfileClient.php},resources/views/admin/websites/partials/business-profile.blade.php | .ai/rules/partials.md |
| app/{Jobs,Services,Http/Controllers/Admin}/**/*SeoProspect*.php,resources/views/admin/prospect-discoveries/seo-show.blade.php | .ai/rules/prospect-discoveries.md |
| app/{Console/Commands,Jobs,Services,Models,Http/Controllers/Admin}/**/*Prospect*.php,database/migrations/**/*prospect*.php,resources/views/admin/prospecting-strategy/** | .ai/rules/prospecting-strategy.md |
| app/{Services,Jobs,Http/Controllers/Admin,Http/Requests}/**/*PersonalisedVideo*.php,resources/views/admin/prospects/**,app/Services/ProspectEngagementScorer.php | .ai/rules/prospects-services.md |
| app/{Mail,Models,Services,Http/Controllers}/**/*ProspectOutreach*.php,resources/views/mail/prospects/outreach.blade.php,resources/views/admin/prospects/**,routes/web.php | .ai/rules/prospects.md |
| app/Providers/AppServiceProvider.php | .ai/rules/providers.md |
| public/favicon.*,public/apple-touch-icon.png,resources/views/layouts/{marketing,ppc,auth,app}.blade.php | .ai/rules/public-views-layouts.md |
| public/pixel.js | .ai/rules/public.md |
| app/{Models,Services,Jobs,Http/Controllers/Admin,Http/Requests}/**/*SeoImpact*.php,app/Services/{ContentWorkSelector,ContentGenerationPromptGenerator,ContentOpportunityQueuer}.php | .ai/rules/requests-services.md |
| app/{Jobs/RunCopilotSdkTitle.php,Http/Controllers/Admin/CopilotSdkRunController.php,Http/Requests/StoreCopilotSdkRunRequest.php},resources/views/admin/websites/partials/content-sdk*.blade.php | .ai/rules/requests-views-admin-websites-partials.md |
| app/{Models/Website.php,Policies/WebsitePolicy.php,Http/Controllers/Admin/WebsiteMemberController.php,Http/Requests/*WebsiteMemberRequest.php},resources/views/admin/websites/**,routes/web.php | .ai/rules/requests-views-admin-websites.md |
| app/{Models/Website.php,Http/Controllers/Admin/**,Http/Requests/**} | .ai/rules/requests.md |
| resources/views/layouts/marketing.blade.php | .ai/rules/resources-views-layouts.md |
| resources/views/marketing/free-site-audit.blade.php, resources/views/marketing/website-audit.blade.php, resources/views/marketing/**, resources/views/marketing/audit-email-gate.blade.php, resources/views/marketing/{website-audit,audit-email-gate}.blade.php | .ai/rules/resources-views-marketing.md |
| app/Services/SeoIntelligence/**,app/Models/SeoOpportunity.php | .ai/rules/seo-intelligence-models.md |
| app/Services/SeoIntelligence/** | .ai/rules/seo-intelligence.md |
| app/{Services,Console/Commands}/**/*Prospect*.php,routes/console.php | .ai/rules/services-console-commands.md |
| app/{Services/Github*,Http/Controllers/**/*Github*,Models/{GithubInstallation,WebsiteRepository,RemediationRun}.php}, app/{Services/PixelUrlNormalizer.php,Services/PixelPayloadBuilder.php,Http/Controllers/PixelPayloadController.php,Models/Optimisation.php} | .ai/rules/services-controllers.md |
| app/Services/BacklinkAuditService.php,tests/Feature/BacklinkAuditTest.php | .ai/rules/services-feature.md |
| app/Services/GoogleAdsClient.php,app/Http/Controllers/Admin/GoogleAdsController.php,resources/views/admin/websites/google-ads.blade.php | .ai/rules/services-http-controllers-admin-views-admin-websites.md |
| {app/Services/GithubRepositoryPilot.php,app/Http/Controllers/Admin/{GithubConnectionController,WebsiteRepositoryController}.php,config/copilot_sdk.php} | .ai/rules/services-http-controllers-admin.md |
| app/{Services,Jobs,Console/Commands}/**/*Competitor*.php | .ai/rules/services-jobs-console-commands.md |
| app/{Services,Jobs,Http/Controllers/Admin}/**/*SeoProspect*.php, app/{Services,Jobs,Http/Controllers/Admin}/**/*Competitor*.php, app/{Services,Jobs,Http/Controllers/Admin}/**/*AiVisibility*.php | .ai/rules/services-jobs-http-controllers-admin.md |
| app/{Services,Jobs,Http/Controllers}/**/*Content*.php | .ai/rules/services-jobs-http-controllers.md |
| app/Services/WebsiteActionCenter.php,database/migrations/*seo_opportunities*.php | .ai/rules/services-migrations.md |
| {app/Services/WordPressStaticReleaseBuilder.php,wordpress-plugin/sitewell-by-digizu/**} | .ai/rules/services-sitewell-by-digizu.md |
| app/Services/Prospect*.php,resources/views/admin/prospects/** | .ai/rules/services-views-admin-prospects.md |
| app/Services/ContentQueueOverview.php,resources/views/admin/overview.blade.php, app/Services/DashboardWorkActivity.php,resources/views/admin/overview.blade.php | .ai/rules/services-views-admin.md |
| app/Services/MarketingAudit*.php,resources/views/marketing/website-audit.blade.php, app/Services/MarketingAuditResearch.php,resources/views/marketing/website-audit.blade.php | .ai/rules/services-views-marketing.md |
| app/Services/WebsiteCrawler.php, app/Services/Pixel*.php, app/Services/{SitemapFetcher,ProspectWebsiteAnalyzer,WebsiteHealthAuditor}.php, app/Services/WeeklyReport*.php, app/Services/SearchConsoleClient.php, app/Services/GoogleAds*, app/Services/GoogleAdsClient.php, app/Services/{WebsiteHealthAuditor,WebsiteCrawler,ProspectWebsiteAnalyzer}.php, app/Services/{SeoImpactTracker,ContentWorkSelector,ContentGenerationPromptGenerator}.php, app/Services/ContentGenerationPromptGenerator.php, app/Services/ProspectWebsiteAnalyzer.php | .ai/rules/services.md |
| {app/Services/WordPressStaticReleaseBuilder.php,app/Http/Controllers/WordPress*Release*Controller.php,wordpress-plugin/sitewell-by-digizu/**} | .ai/rules/sitewell-by-digizu.md |
| app/{Support/WebsiteNavigation.php,Http/Controllers/Admin/CurrentWebsiteController.php} | .ai/rules/support-controllers-admin.md |
| app/Support/WebsiteNavigation.php | .ai/rules/support.md |
| {app/Models/User.php,app/Http/Controllers/Admin/UserImpersonationController.php,resources/views/{admin/users/index.blade.php,layouts/app.blade.php},routes/web.php,tests/Feature/UserImpersonationTest.php} | .ai/rules/users-feature.md |
| app/{Models/User.php,Http/Controllers/Admin/{DashboardController.php,OnboardingCallController.php,UserOnboardingCallController.php,WebsiteHealthReportController.php},Http/Requests/UpdateUserOnboardingCallRequest.php},resources/views/{admin/dashboard.blade.php,admin/users/index.blade.php,layouts/app.blade.php},routes/web.php,database/migrations/**/*onboarding*progress*.php | .ai/rules/users-migrations.md |
| app/{Models/{FormSubmission,ReviewInvitation}.php,Jobs/Send*Invitation.php,Services/ReviewInvitationService.php,Http/Controllers/Admin/*ReviewInvitationController.php},resources/views/admin/form-submissions/** | .ai/rules/views-admin-form-submissions.md |
| app/{Actions/ActivateWebsiteAuditTrial.php,Enums/OnboardingLifecycleStep.php,Models/{User.php,OnboardingLifecycleMessage.php},Services/OnboardingLifecycleManager.php,Notifications/OnboardingLifecycleNotification.php,Listeners/MarkOnboardingLifecycleMessageSent.php,Console/Commands/DispatchDueOnboardingLifecycleMessages.php,Http/Controllers/OnboardingLifecycleClickController.php},database/migrations/**/*onboarding_lifecycle*.php,resources/views/admin/onboarding-leads/**,routes/{web,console}.php,tests/Feature/{OnboardingLifecycleTest.php,OnboardingLeadTest.php} | .ai/rules/views-admin-onboarding-leads-feature.md |
| app/{Services,Models}/**/*Prospect*.php,config/outreach.php,resources/views/admin/prospects/** | .ai/rules/views-admin-prospects.md |
| app/Services/GoogleAdsClient.php,app/Http/Controllers/Admin/GoogleAdsController.php,resources/views/admin/websites/google-ads.blade.php,tests/Feature/GoogleAdsConnectionTest.php | .ai/rules/views-admin-websites-feature.md |
| resources/views/admin/websites/partials/content*.blade.php | .ai/rules/views-admin-websites-partials.md |
| app/{Ai/Agents/WebsiteDataAssistant.php,Services/WebsiteAiContext.php,Http/Controllers/Admin/WebsiteAiChatController.php,Models/WebsiteAiQuestion.php},resources/views/admin/websites/show.blade.php,routes/web.php | .ai/rules/views-admin-websites.md |
| app/Http/Controllers/Admin/DashboardController.php,resources/views/admin/dashboard.blade.php | .ai/rules/views-admin.md |
| app/{Ai,Jobs,Services,Http/Controllers/Admin}/**/*Pixel*.php,resources/views/admin/{websites/**,website-health-reports/**} | .ai/rules/views-adminwebsites.md |
| {app/Models/MarketingConversion.php,app/Http/Controllers/FreeSiteAuditController.php,resources/js/marketing-events.js,resources/views/layouts/{marketing,ppc}.blade.php,resources/views/components/marketing/tag-manager.blade.php} | .ai/rules/views-components-marketing.md |
| app/{Jobs,Mail,Services,Console/Commands}/**/*.php,resources/views/emails/**, app/{Jobs,Mail,Services,Console/Commands}/**/*.php,resources/views/emails/**,routes/console.php | .ai/rules/views-emails.md |
| app/Http/Controllers/Admin/DashboardController.php,resources/views/admin/{dashboard,overview}.blade.php,resources/views/layouts/app.blade.php | .ai/rules/views-layouts.md |
| app/Services/Prospect*.php,resources/views/mail/prospects/** | .ai/rules/views-mail-prospects.md |
| {app/Http/Controllers/FreeSiteAuditController.php,app/Mail/WebsiteAuditReport.php,resources/views/marketing/{website-audit,audit-email-gate}.blade.php,resources/views/mail/website-audit-report.blade.php} | .ai/rules/views-mail.md |
| {app/Http/Controllers/MarketingController.php,config/marketing.php,resources/views/marketing/{landing,features}.blade.php,routes/web.php,tests/Feature/MarketingPagesTest.php} | .ai/rules/views-marketing-feature.md |
| resources/views/marketing/website-audit.blade.php,resources/js/{audit-review,marketing-events}.js | .ai/rules/views-marketing-js.md |
| app/{Http/Controllers/FreeSiteAuditController.php,Http/Requests/StoreFreeSiteAuditRequest.php,Jobs/GenerateWebsiteAudit.php,Models/WebsiteAudit.php,Services/MarketingTurnstileVerifier.php},resources/views/marketing/{free-site-audit,website-audit}.blade.php,routes/web.php | .ai/rules/views-marketing.md |
| resources/views/** | .ai/rules/views.md |
| app/{Console/Commands,Jobs,Mail,Models,Http/Controllers/Admin}/**/*Ranking*.php,resources/views/{admin/websites/**,emails/*ranking*},routes/web.php | .ai/rules/viewsadmin-websites.md |
| resources/views/{layouts/marketing.blade.php,marketing/**} | .ai/rules/viewslayouts.md |
| {app/Jobs/{GenerateWebsiteAudit,GenerateWebsiteAuditFullReport,CheckMarketingAuditAiVisibility}.php,app/Services/MarketingAuditResearch.php,app/Http/Controllers/FreeSiteAuditController.php,resources/views/{marketing/website-audit,admin/onboarding-leads/index}.blade.php} | .ai/rules/website-auditadmin-onboarding-leads.md |
| {app/Http/Controllers/FreeSiteAuditController.php,app/Http/Requests/UpdateWebsiteAuditGoalRequest.php,app/Mail/WebsiteAuditReport.php,resources/views/{marketing/website-audit,mail/website-audit*}.blade.php} | .ai/rules/website-auditmail.md |
| app/Models/Website.php,resources/views/admin/websites/**,resources/views/admin/website-health-reports/** | .ai/rules/website-health-reports.md |
| app/{Http/Controllers/Admin/WebsiteMemberController,Http/Requests/{StoreWebsiteMemberRequest,UpdateWebsiteMemberRequest}}.php | .ai/rules/website-member-controller-http-requests.md |
| app/{Services/AutoresponderHtmlSanitizer.php,Services/FormSettingsResolver.php,Mail/FormSubmissionAcknowledgement.php,Http/Controllers/Admin/{FormController.php,WebsiteAutoresponderController.php}},resources/{js/app.js,views/admin/forms/show.blade.php,views/admin/websites/show.blade.php,views/emails/form-submission-acknowledgement*.blade.php} | .ai/rules/websites-emails.md |
| {app/Http/Controllers/Admin/WebsiteController.php,resources/views/admin/websites/show.blade.php,tests/Feature/DashboardInformationArchitectureTest.php} | .ai/rules/websites-feature.md |
| app/{Models,Services}/SeoTargetKeyword*.php,resources/views/admin/websites/partials/seo-target-keywords.blade.php | .ai/rules/websites-partials.md |
| app/{Http/Controllers/Admin/WebsiteMemberController.php,Http/Requests/StoreWebsiteMemberRequest.php,Notifications/WebsiteInvitation.php},resources/views/admin/websites/show.blade.php,routes/web.php | .ai/rules/websites.md |
