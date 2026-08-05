Build a simple, production-ready Laravel application that acts as a central form submission service for all of my static client websites.

The application must support:

* Multiple unrelated client websites.
* Multiple different forms on each website.
* The exact same generic submission URL across every website and every form.
* Automatic discovery of new websites and new forms.
* Email notifications.
* Webhook notifications, including Zapier.
* Different notification settings for individual forms when required.
* Zero setup before adding a form to a new website.

The main goal is that every form can use the same form action:

<form method="POST" action="https://forms.example.com/submit">

I do not want to generate API keys, website IDs, form IDs or custom endpoint URLs for each website.

The Laravel application should automatically determine:

1. Which website sent the submission.
2. Which form on that website was submitted.
3. Which email recipients and webhook settings should be used.

Keep the application as simple as possible.

Do not add unnecessary abstractions, packages, queues, events, repositories, APIs or enterprise architecture unless they provide a clear practical benefit.

Core workflow

When a form is submitted:

1. Accept the submission at:

POST /submit

2. Determine the submitting website from the request’s Origin header.
3. If Origin is unavailable, fall back to the Referer header.
4. Extract and normalise the domain.

For example:

https://www.example.com/contact?source=footer

Should become:

example.com

Treat these as equivalent by default:

example.com
www.example.com

5. Find the website record associated with the detected domain.
6. If the domain is not already registered and automatic website discovery is enabled, automatically create the website using safe default settings.
7. Determine which form was submitted using the hidden _form_name field.

For example:

<input type="hidden" name="_form_name" value="Contact form">

8. Normalise the form name and find the corresponding form record belonging to that website.
9. If that form does not already exist, automatically create it using the website’s default settings.
10. Save the complete form submission in the database.
11. Depending on the resolved form and website settings, do one or both of the following:

* Send an email containing the submitted form data.
* Send the submitted form data to a configured webhook URL, such as Zapier.

12. Redirect the visitor back to an appropriate success or error page.

The same website must be able to contain forms such as:

Contact form
Request a callback
Quote request
Newsletter signup
Job application
Brochure download
Booking enquiry

Each of these forms must be stored and managed separately.

Zero-setup form discovery

The zero-setup workflow is essential.

A brand-new website should only need this:

<form method="POST" action="https://forms.example.com/submit">
    <input type="hidden" name="_form_name" value="Contact form">
    <input type="text" name="name">
    <input type="email" name="email">
    <textarea name="message"></textarea>
    <button type="submit">Send</button>
</form>

No website record, form record, API key, form ID or endpoint configuration should be required beforehand.

On the first submission from a new website and new form, the Laravel application should:

1. Detect the website domain.
2. Automatically create the website.
3. Automatically create the form belonging to that website.
4. Apply the globally configured default email recipient.
5. Save the submission.
6. Send the email notification.
7. Display the website and form in the administration area.

If the same website later submits a different form name, create a second form record beneath the existing website.

For example:

Website: example.com
Forms:
- Contact form
- Quote request
- Newsletter signup

The website should only be created once.

The forms should be created separately.

Form identification

Support this hidden field:

<input type="hidden" name="_form_name" value="Contact form">

The _form_name value identifies the form within the detected website.

The combination of website and form name must be unique.

For example, these are different forms:

example.com / Contact form
example.com / Quote request
anotherwebsite.com / Contact form

Do not treat form names as globally unique.

Normalise form names before matching them:

* Trim surrounding whitespace.
* Collapse repeated whitespace.
* Compare names case-insensitively.
* Preserve a clean display name.
* Limit names to a sensible maximum length.
* Reject malformed or excessively long names.

Generate a slug for internal use.

For example:

Request a Callback

Could become:

request-a-callback

If _form_name is missing, use:

Website form

This means a form still works without the hidden field, but using _form_name should be recommended whenever a website contains more than one form.

Do not trust a hidden website ID, domain or account ID to determine the submitting website.

The website must always be resolved from Origin or Referer.

Configuration hierarchy

Notification and redirect settings should follow a simple inheritance model.

Each website has default settings.

Each individual form may optionally override those settings.

The resolved configuration priority should be:

1. Form-specific override.
2. Website default.
3. Global application default.

For example, a website might have:

Default email recipient:
hello@example.com

Its forms could then behave as follows:

Contact form:
Uses hello@example.com
Quote request:
Overrides recipient with sales@example.com
Job application:
Overrides recipient with careers@example.com
Newsletter signup:
Email disabled
Webhook enabled

Do not duplicate website defaults into every form unless necessary.

Use nullable override fields or a clear inheritance mechanism.

Keep the implementation understandable.

Technology

Use:

* The latest stable Laravel version supported by PHP 8.4.
* MySQL.
* Blade.
* Laravel’s built-in authentication.
* Laravel’s built-in mail system.
* Laravel’s built-in HTTP client.
* Laravel’s built-in rate limiting.
* Tailwind CSS for the administration interface.

This is a private application with one or a small number of administrator users.

Do not build public registration.

Do not add a SPA framework unless it is genuinely necessary.

Database structure

Create a websites table with fields similar to:

id
name
is_active
auto_discovered
email_enabled
email_recipients
webhook_enabled
webhook_url
webhook_secret
success_redirect_url
failure_redirect_url
turnstile_enabled
turnstile_secret_key
first_seen_at
created_at
updated_at

Store email_recipients as JSON so multiple email addresses can be configured.

Create a website_domains table with fields similar to:

id
website_id
domain
is_primary
created_at
updated_at

The domain column must be unique.

This allows a website to accept submissions from domains such as:

example.com
www.example.com
example.netlify.app
staging.example.com
preview.example.com

Create a forms table with fields similar to:

id
website_id
name
slug
is_active
auto_discovered
email_enabled_override
email_recipients_override
email_subject_override
webhook_enabled_override
webhook_url_override
webhook_secret_override
success_redirect_url_override
failure_redirect_url_override
first_seen_at
last_submission_at
created_at
updated_at

The combination of website_id and slug must be unique.

Override fields should be nullable.

A null override means:

Use the website setting

For boolean overrides, use a nullable boolean so the application can distinguish between:

null = inherit website setting
true = explicitly enabled
false = explicitly disabled

Store recipient overrides as JSON.

Create a form_submissions table with fields similar to:

id
website_id
form_id
source_url
source_domain
data
ip_address
user_agent
is_spam
email_sent_at
email_failed_at
email_error
webhook_sent_at
webhook_failed_at
webhook_status_code
webhook_response
webhook_error
created_at
updated_at

Store submitted form data in a JSON column.

Do not create database columns for individual submitted fields.

Forms may contain arbitrary fields and the service must accept them dynamically.

Use proper Eloquent relationships:

Website has many domains.
Website has many forms.
Website has many submissions.
Form belongs to a website.
Form has many submissions.
Submission belongs to a website.
Submission belongs to a form.

Automatic website defaults

Unknown domains should be automatically created with:

name: detected domain
is_active: true
auto_discovered: true
email_enabled: true
email_recipients: globally configured default recipient
webhook_enabled: false
first_seen_at: current time

Automatic discovery must be controlled globally by:

FORMS_AUTO_REGISTER_WEBSITES=true

If automatic website registration is disabled, reject submissions from unknown domains.

Automatic form defaults

When a previously unknown form name is submitted from a known website, automatically create it with:

website_id: resolved website
name: submitted form name
slug: generated form slug
is_active: true
auto_discovered: true
all override fields: null
first_seen_at: current time

With all override fields set to null, the new form inherits the website’s configuration.

Automatic form discovery must be controlled globally by:

FORMS_AUTO_REGISTER_FORMS=true

If automatic form discovery is disabled, reject submissions for unknown forms.

Global configuration

Add settings to .env and config/forms.php, including:

FORMS_DEFAULT_RECIPIENT=
FORMS_FROM_ADDRESS=
FORMS_FROM_NAME=
FORMS_AUTO_REGISTER_WEBSITES=true
FORMS_AUTO_REGISTER_FORMS=true
FORMS_RATE_LIMIT_PER_MINUTE=10
FORMS_MAX_PAYLOAD_KB=256
FORMS_MAX_FIELD_LENGTH=10000
FORMS_WEBHOOK_TIMEOUT=10
FORMS_WEBHOOK_RESPONSE_MAX_LENGTH=2000

Read these settings through config/forms.php.

Do not call env() directly throughout the application.

Form submission formats

Accept:

application/x-www-form-urlencoded
multipart/form-data
application/json

Support normal HTML form posts and JavaScript fetch() requests.

Ignore internal fields when generating emails or webhook form data.

Internal fields include:

_token
_form_name
_form_success_url
_form_error_url
_honeypot
g-recaptcha-response
cf-turnstile-response

Keep internal metadata separate from the visitor’s submitted form data.

Form validation

Because forms can contain arbitrary fields, do not require predefined field schemas.

Apply general validation rules:

* At least one valid user-supplied field must be present.
* Field names must have a sensible maximum length.
* String values must have a configurable maximum length.
* Reject excessively large request payloads.
* Reject excessive numbers of fields.
* Remove null bytes.
* Remove unsafe control characters.
* Preserve normal line breaks.
* Support array values.
* Convert arrays into readable output in emails.
* Do not execute, interpret or render submitted HTML.
* Escape all output in the administration interface.
* Escape all output in notification emails.

Do not remove ordinary punctuation or legitimate Unicode characters.

Email field detection

Recognise common visitor email field names, including:

email
email_address
email-address
your_email
your-email
contact_email
contact-email

Field-name matching should be case-insensitive.

When a valid visitor email address is found, use it as the email’s Reply-To address.

Never use a visitor-supplied address as the sender address.

The sender must always use the configured application address.

Email notifications

Resolve whether email is enabled using:

1. form.email_enabled_override when not null.
2. Otherwise website.email_enabled.
3. Otherwise the global default.

Resolve recipients using:

1. form.email_recipients_override when present.
2. Otherwise website.email_recipients.
3. Otherwise FORMS_DEFAULT_RECIPIENT.

When email notifications are enabled, send a clean HTML email containing:

* Website name.
* Website domain.
* Form name.
* Submission date and time.
* Source page URL.
* Every submitted field and value.
* Visitor IP address.
* A link to view the submission in the administration area.

Use a simple, responsive email layout suitable for common email clients.

The default email subject should be:

New {form name} submission from {website domain}

Allow an individual form to override the email subject.

Support simple placeholders in a form-specific subject:

{form_name}
{website_name}
{website_domain}
{submission_id}

Do not allow submitted form values to be inserted into the subject in the first version.

Send the email to every valid resolved recipient.

Record whether the email succeeded or failed.

If email delivery fails:

* Preserve the submission.
* Record the error.
* Continue processing any webhook.
* Show the failure in the administration area.

For the first version, send email synchronously.

Keep the notification code isolated enough that it can be moved to a queued job later without rewriting the submission controller.

Webhook notifications

Resolve whether a webhook is enabled using:

1. form.webhook_enabled_override when not null.
2. Otherwise website.webhook_enabled.

Resolve the webhook URL using:

1. form.webhook_url_override when present.
2. Otherwise website.webhook_url.

Resolve the webhook secret using:

1. form.webhook_secret_override when present.
2. Otherwise website.webhook_secret.

This should allow one website to send different forms to different Zapier webhooks.

For example:

Contact form:
https://hooks.zapier.com/hooks/catch/contact
Quote request:
https://hooks.zapier.com/hooks/catch/quotes
Newsletter signup:
https://hooks.zapier.com/hooks/catch/newsletter

Send a JSON POST request with a payload similar to:

{
  "submission_id": 123,
  "website": {
    "id": 1,
    "name": "Example Website",
    "domain": "example.com"
  },
  "form": {
    "id": 4,
    "name": "Quote request",
    "slug": "quote-request"
  },
  "source_url": "https://example.com/request-a-quote",
  "submitted_at": "2026-08-05T12:00:00Z",
  "data": {
    "name": "John Smith",
    "email": "john@example.com",
    "message": "Please provide a quote"
  }
}

Include these headers:

Content-Type: application/json
User-Agent: CentralForms/1.0
X-Form-Submission-ID: {submission ID}
X-Website-ID: {website ID}
X-Form-ID: {form ID}
X-Form-Slug: {form slug}

When a webhook secret is configured, sign the exact raw JSON body using HMAC SHA-256.

Send the signature in:

X-Form-Signature

Treat HTTP status codes from 200 to 299 as successful.

Record:

* Success or failure.
* HTTP status code.
* Safely truncated response body.
* Error message.
* Attempt timestamp.

A webhook failure must never roll back or delete the stored submission.

For the first version, send webhooks synchronously.

Keep webhook delivery inside a focused service class so it can be queued later.

Redirect handling

Support optional hidden redirect fields:

<input
    type="hidden"
    name="_form_success_url"
    value="https://example.com/thank-you"
>
<input
    type="hidden"
    name="_form_error_url"
    value="https://example.com/contact?form=error"
>

Only allow redirect URLs that belong to one of the resolved website’s registered domains.

Never allow arbitrary external redirects.

The success redirect priority should be:

1. Valid _form_success_url submitted with the form.
2. Form-specific success redirect override.
3. Website success redirect.
4. Referring page with form=success.
5. A success page hosted by the Laravel application.

The error redirect priority should be:

1. Valid _form_error_url submitted with the form.
2. Form-specific failure redirect override.
3. Website failure redirect.
4. Referring page with form=error.
5. An error page hosted by the Laravel application.

When adding form=success or form=error, preserve existing query parameters safely.

Also include the form slug where appropriate:

?form=success&form_name=contact-form

Do not expose sensitive information in redirect query strings.

JSON responses

When the request includes:

Accept: application/json

Return an appropriate JSON response instead of redirecting.

A successful response should resemble:

{
  "success": true,
  "message": "Your form was submitted successfully.",
  "submission_id": 123,
  "form": {
    "name": "Contact form",
    "slug": "contact-form"
  }
}

Validation failures should return a suitable 422 response.

Rate-limit responses should use HTTP 429.

Unknown or disabled websites and forms should use suitable 4xx responses.

Do not include internal exceptions or sensitive configuration in JSON responses.

Spam protection

Add basic spam protection that requires no per-site setup.

Honeypot

Support a honeypot field named:

_honeypot

If it contains a value:

* Save the submission as spam if useful.
* Set is_spam to true.
* Do not send email.
* Do not send a webhook.
* Return a normal-looking success response to avoid helping bots understand the protection.

Provide an accessible example implementation.

Example:

<div
    style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"
    aria-hidden="true"
>
    <label>
        Leave this field empty
        <input
            type="text"
            name="_honeypot"
            tabindex="-1"
            autocomplete="off"
        >
    </label>
</div>

Rate limiting

Rate-limit submissions using a combination of:

* Normalised source domain.
* Visitor IP address.

Use:

FORMS_RATE_LIMIT_PER_MINUTE=10

Do not allow submissions from one website to consume the rate limit for another unrelated website.

Optional Cloudflare Turnstile

Add optional Cloudflare Turnstile support.

Turnstile should be configured at website level initially.

Use fields such as:

turnstile_enabled
turnstile_secret_key

When Turnstile is enabled, validate:

cf-turnstile-response

Do not require Turnstile for automatically discovered websites.

A future form-specific Turnstile override is not necessary for the first version.

Domain security

Do not trust submitted hidden fields to identify the website.

Resolve the website using:

1. Origin.
2. Referer fallback.

Normalise domains consistently:

* Convert to lowercase.
* Remove scheme.
* Remove paths.
* Remove query strings.
* Remove fragments.
* Remove ports.
* Remove a leading www. for matching where appropriate.
* Handle international domain names safely.
* Only allow HTTP and HTTPS origins.

Store the original source URL where available.

Reject submissions when:

* Neither a valid Origin nor Referer can be determined.
* The source URL is malformed.
* The protocol is not HTTP or HTTPS.
* The website is disabled.
* The resolved form is disabled.
* The payload is too large.
* Automatic discovery is disabled and the website is unknown.
* Automatic form discovery is disabled and the form is unknown.

Webhook URL security

Validate configured webhook URLs.

Only allow HTTPS webhook URLs by default.

Provide a configuration option to allow HTTP webhooks in local development.

Protect against server-side request forgery.

Do not permit webhook requests to:

* localhost
* loopback addresses
* private network ranges
* link-local addresses
* cloud metadata endpoints
* malformed hosts

Resolve and validate the target safely before sending.

Document this behaviour.

CORS and CSRF

The public /submit endpoint must work from static websites hosted on other domains.

Exclude only the public submission endpoint from Laravel CSRF validation.

Do not disable CSRF globally.

Keep administration routes fully protected.

Configure CORS to support JavaScript submissions to /submit.

Also support ordinary HTML form posts, which may not require the same CORS handling as fetch().

Do not use wildcard credentialed CORS.

The public endpoint does not need cookies or authenticated browser credentials.

Administration area

Build a small, practical administration interface.

Keep it straightforward rather than visually elaborate.

Authentication

Use Laravel authentication.

Do not allow public registration.

Provide a documented way to create the first administrator.

Dashboard

Show:

* Total websites.
* Total forms.
* Total submissions.
* Submissions today.
* Spam submissions.
* Email failures.
* Webhook failures.
* Recently discovered websites.
* Recently discovered forms.
* Recent submissions.

Websites

Allow the administrator to:

* View all websites.
* Create a website manually.
* Edit the website name.
* Enable or disable the website.
* See whether it was automatically discovered.
* Manage domains and aliases.
* Choose a primary domain.
* Enable or disable default email notifications.
* Add one or more default email recipients.
* Enable or disable the default webhook.
* Configure the default webhook URL.
* Generate, update or remove the default webhook secret.
* Configure default success and failure redirects.
* Enable or disable Turnstile.
* Configure the Turnstile secret.
* View all forms belonging to the website.
* View all submissions belonging to the website.

Forms

Each website must have a separate forms section.

Allow the administrator to:

* View all forms belonging to a website.
* Create a form manually.
* Rename a form.
* Change its slug safely.
* Enable or disable the form.
* See whether it was automatically discovered.
* See first-seen and last-submission dates.
* View submission counts.
* Choose whether email settings inherit from the website.
* Explicitly enable or disable email for the form.
* Override email recipients.
* Override the email subject.
* Choose whether webhook settings inherit from the website.
* Explicitly enable or disable the webhook for the form.
* Override the webhook URL.
* Override the webhook secret.
* Override success and failure redirects.
* Reset any override back to the website default.
* View submissions belonging to the form.

Clearly label inherited settings.

For example:

Email notifications:
Inherit from website — Enabled

And:

Email recipients:
Inherit from website — hello@example.com

The interface should make it obvious whether a value is inherited or overridden.

Submissions

Allow the administrator to:

* View all submissions.
* Filter by website.
* Filter by form.
* Filter by date.
* Filter by delivery status.
* Filter spam submissions.
* Search submitted form data.
* View the full submission.
* View the source domain and source URL.
* View email delivery status.
* View webhook delivery status.
* Manually resend the email.
* Manually retry the webhook.
* Mark or unmark a submission as spam.
* Delete a submission.

When resending an email or retrying a webhook, use the current form and website settings unless there is a strong reason to preserve the historical destination.

Document this behaviour.

Email preview and webhook testing

Within the administration area, add simple tools to:

* Send a test email using a website’s resolved settings.
* Send a test email using a form’s resolved settings.
* Send a test webhook using a website’s resolved settings.
* Send a test webhook using a form’s resolved settings.

Clearly label test payloads so they cannot be mistaken for real enquiries.

Do not require these test tools for the first submission to work.

Failure handling

The submission must be saved before attempting notifications.

Email and webhook delivery must be independent.

For example:

* If email succeeds and webhook fails, record both outcomes.
* If webhook succeeds and email fails, record both outcomes.
* If both fail, preserve the submission.
* If one notification method is disabled, do not treat it as a failure.

Log operational failures through Laravel logging.

Do not expose exception messages to visitors.

Code organisation

Prefer standard Laravel conventions.

A reasonable structure would include:

app/Http/Controllers/FormSubmissionController.php
app/Http/Controllers/Admin/DashboardController.php
app/Http/Controllers/Admin/WebsiteController.php
app/Http/Controllers/Admin/FormController.php
app/Http/Controllers/Admin/FormSubmissionController.php
app/Http/Requests/StoreFormSubmissionRequest.php
app/Models/Website.php
app/Models/WebsiteDomain.php
app/Models/Form.php
app/Models/FormSubmission.php
app/Services/SourceWebsiteResolver.php
app/Services/FormResolver.php
app/Services/FormSettingsResolver.php
app/Services/FormDataSanitiser.php
app/Services/RedirectResolver.php
app/Services/WebhookSender.php
app/Mail/FormSubmissionReceived.php

Do not create interfaces and implementations for every class unless genuinely useful.

Use:

* Form requests.
* Eloquent relationships.
* Mailables.
* Small focused services.
* Standard controllers.
* Laravel policies or middleware where appropriate.
* Database transactions where appropriate.
* Laravel logging.

Avoid putting the entire workflow into one large controller method.

At the same time, do not over-engineer the application.

Tests

Add feature and unit tests covering at least the following.

Website resolution

* Valid submission from a known domain.
* Automatic registration of a new website.
* Rejection of an unknown website when auto-registration is disabled.
* Domain normalisation.
* Leading www handling.
* Domain aliases.
* Origin resolution.
* Referer fallback.
* Rejection when no source can be determined.
* Disabled website rejection.

Multiple forms

* Two different forms submitted from the same website.
* Automatic creation of both forms.
* Same form name used on two different websites.
* Case-insensitive form-name matching.
* Missing _form_name using Website form.
* Form slug generation.
* Duplicate form prevention.
* Disabled form rejection.
* Unknown form rejection when automatic form discovery is disabled.
* Updating last_submission_at.

Configuration inheritance

* Form inherits website email settings.
* Form overrides website email recipients.
* Form explicitly disables inherited email.
* Form inherits website webhook settings.
* Form overrides the webhook URL.
* Form explicitly disables inherited webhook.
* Form inherits redirect settings.
* Form overrides redirect settings.
* Resetting an override restores inherited behaviour.

Email delivery

* Email notification delivery.
* Correct resolved recipient.
* Multiple recipients.
* Form-specific recipient override.
* Form-specific subject override.
* Reply-To detection.
* Email failure handling.
* Manual resend.

Webhook delivery

* Website-level webhook delivery.
* Form-level webhook override.
* Different forms posting to different webhook URLs.
* Combined email and webhook delivery.
* Webhook failure handling.
* Correct webhook payload.
* Correct form metadata.
* Webhook signature generation.
* Manual webhook retry.
* SSRF protections.

Submission handling

* Standard HTML form submission.
* Multipart form submission.
* JSON submission.
* Arbitrary dynamic fields.
* Array fields.
* Payload size validation.
* Field-length validation.
* Internal field removal.
* Output escaping.
* Submission saved before notification attempts.

Redirects and responses

* Normal browser success redirect.
* Normal browser error redirect.
* Form-specific redirect.
* Website-level redirect.
* Hidden redirect field.
* Rejection of an external redirect.
* Rejection of an alias belonging to another website.
* JSON success response.
* JSON validation error.
* Existing query parameter preservation.

Spam and rate limiting

* Honeypot spam detection.
* Spam submission does not send notifications.
* Rate limiting by website and IP.
* Turnstile success.
* Turnstile failure.

Use Laravel’s built-in mail and HTTP fakes.

Documentation

Create a clear README containing:

* Application purpose.
* Local installation.
* Production deployment.
* PHP and database requirements.
* Environment variables.
* Mail configuration.
* Creating the first administrator.
* How website auto-discovery works.
* How form auto-discovery works.
* How multiple forms on one website are identified.
* How website settings and form overrides work.
* How to configure email recipients.
* How to configure Zapier webhooks.
* How webhook signatures work.
* How redirects work.
* How to retry failed notifications.
* Security considerations.
* CORS and CSRF behaviour.
* Spam protection.
* Webhook SSRF protection.

Include a clear explanation that _form_name should remain stable after launch.

Changing:

<input type="hidden" name="_form_name" value="Quote request">

To:

<input type="hidden" name="_form_name" value="Request a quote">

May create a new automatically discovered form unless the administrator renames the existing form or manages its slug appropriately.

Example: contact form

Include this example:

<form method="POST" action="https://forms.example.com/submit">
    <input type="hidden" name="_form_name" value="Contact form">
    <div
        style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"
        aria-hidden="true"
    >
        <label>
            Leave this field empty
            <input
                type="text"
                name="_honeypot"
                tabindex="-1"
                autocomplete="off"
            >
        </label>
    </div>
    <label>
        Name
        <input type="text" name="name" required>
    </label>
    <label>
        Email
        <input type="email" name="email" required>
    </label>
    <label>
        Message
        <textarea name="message" required></textarea>
    </label>
    <button type="submit">Send enquiry</button>
</form>

Example: quote form on the same website

Include this second example to demonstrate multiple forms:

<form method="POST" action="https://forms.example.com/submit">
    <input type="hidden" name="_form_name" value="Quote request">
    <div
        style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"
        aria-hidden="true"
    >
        <label>
            Leave this field empty
            <input
                type="text"
                name="_honeypot"
                tabindex="-1"
                autocomplete="off"
            >
        </label>
    </div>
    <label>
        Name
        <input type="text" name="name" required>
    </label>
    <label>
        Email
        <input type="email" name="email" required>
    </label>
    <label>
        Estimated budget
        <select name="budget">
            <option value="Under £1,000">Under £1,000</option>
            <option value="£1,000–£5,000">£1,000–£5,000</option>
            <option value="Over £5,000">Over £5,000</option>
        </select>
    </label>
    <label>
        Project details
        <textarea name="project_details" required></textarea>
    </label>
    <button type="submit">Request a quote</button>
</form>

Both forms must use:

https://forms.example.com/submit

The service should identify them separately using their _form_name values.

JavaScript example

Include a fetch() example:

<form id="contact-form">
    <input type="hidden" name="_form_name" value="Contact form">
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <textarea name="message" required></textarea>
    <button type="submit">Send</button>
</form>
<script>
    const form = document.getElementById('contact-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch('https://forms.example.com/submit', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                },
                body: new FormData(form),
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message || 'Unable to submit the form.');
            }
            form.reset();
            alert('Thank you. Your form has been submitted.');
        } catch (error) {
            alert(error.message || 'Something went wrong.');
        } finally {
            button.disabled = false;
        }
    });
</script>

Deliverables

Implement the complete working application, including:

* Migrations.
* Models.
* Relationships.
* Controllers.
* Form requests.
* Services.
* Mailables.
* Email templates.
* Authentication.
* Administration pages.
* Website management.
* Form management.
* Submission management.
* Routes.
* Configuration.
* Tests.
* Seeders where useful.
* README documentation.
* HTML examples.
* JavaScript submission example.

Before considering the implementation complete:

1. Run the full test suite.
2. Run Laravel Pint.
3. Fix all failing tests.
4. Review the implementation for open redirects.
5. Review webhook delivery for SSRF vulnerabilities.
6. Review all submitted content for cross-site scripting risks.
7. Confirm submitted values are escaped in the administration area.
8. Confirm submitted values are escaped in emails.
9. Confirm unknown websites can be automatically discovered.
10. Confirm unknown forms can be automatically discovered.
11. Confirm multiple forms can exist on one website.
12. Confirm different forms can use different email recipients.
13. Confirm different forms can use different Zapier webhook URLs.
14. Confirm every website and every form still uses the same generic /submit endpoint.
15. Confirm email or webhook failures never cause the original submission to be lost.

Prioritise:

* Simplicity.
* Reliability.
* Security.
* Low running costs.
* Minimal setup for new websites.
* Minimal setup for new forms.
* Standard Laravel conventions.

Do not over-engineer the application.