@extends('layouts.marketing')

@section('title', 'Privacy policy')
@section('meta_description', 'How Sitewell collects, uses, shares, and protects personal information, including data received through Google APIs.')

@section('content')
    <article class="py-16 sm:py-24">
        <div class="mx-auto max-w-4xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">Legal</p>
            <h1 class="mt-5 font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Privacy policy</h1>
            <p class="mt-6 max-w-2xl text-pretty text-lg text-ink/65">How Sitewell uses personal information when you visit our website, use our services, contact us, or connect a third-party service.</p>
            <p class="mt-4 text-sm text-ink/50">Effective date: 2 September 2026</p>

            <div class="mt-14 space-y-12 text-base leading-7 text-ink/70">
                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">1. Who we are</h2>
                    <p>Sitewell provides website care, form and lead management, website health monitoring, search insights, content workflows, and connected business-profile tools. Sitewell is the controller of personal information used to operate our website, customer accounts, billing, and direct customer relationships.</p>
                    <p>When a customer uses Sitewell to process information submitted through the customer’s website or otherwise controls the purpose of that processing, the customer is the controller and Sitewell acts as its processor.</p>
                    <p>Questions or data-protection requests can be sent through our <a href="{{ route('marketing.contact') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">contact page</a>.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">2. Information we collect</h2>
                    <ul class="list-disc space-y-3 pl-6 marker:text-garden">
                        <li><strong class="font-semibold text-ink">Account and contact details:</strong> names, email addresses, login credentials, organisation details, preferences, support messages, and membership information.</li>
                        <li><strong class="font-semibold text-ink">Billing information:</strong> subscription status, transaction references, and limited payment metadata. Payment-card details are handled by our payment provider rather than stored by Sitewell.</li>
                        <li><strong class="font-semibold text-ink">Website and service data:</strong> websites, domains, forms, submissions, leads, email-delivery records, reports, content, configuration, repository information, and actions taken in the service.</li>
                        <li><strong class="font-semibold text-ink">Connected-service data:</strong> information you authorise us to obtain from services such as Google and GitHub, together with encrypted access and refresh tokens needed to maintain those connections.</li>
                        <li><strong class="font-semibold text-ink">Google Search Console data:</strong> connected properties and search-performance information such as queries, pages, clicks, impressions, click-through rates, and average positions.</li>
                        <li><strong class="font-semibold text-ink">Google Business Profile data:</strong> account and location identifiers, business details, categories, opening hours, website information, customer reviews and existing replies, posts, and approved profile changes.</li>
                        <li><strong class="font-semibold text-ink">Technical and security data:</strong> IP address, browser and device information, timestamps, logs, cookie identifiers, and information needed to prevent abuse and maintain the service.</li>
                        <li><strong class="font-semibold text-ink">Public business information:</strong> business contact details, websites, and publicly available evidence used to research potential customers and prepare outreach. Outreach engagement records may include whether a message or tracked link was opened or selected; we do not store the recipient’s IP address or user-agent string for that tracking.</li>
                    </ul>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">3. How and why we use information</h2>
                    <p>We use personal information to provide and secure Sitewell, authenticate users, process subscriptions, respond to enquiries, deliver requested audits and communications, monitor website health, manage forms and leads, provide connected features, generate reviewable recommendations and drafts, improve reliability, prevent fraud and spam, and comply with legal obligations.</p>
                    <p>Our lawful bases under UK data-protection law are performance of a contract, steps requested before entering a contract, legitimate interests in operating and improving our services and communicating with relevant businesses, consent where requested, and compliance with legal obligations. Where we rely on legitimate interests, we consider the effect on the people concerned and provide a way to object.</p>
                    <p>Some features use artificial intelligence to prepare drafts or recommendations. These outputs are intended for human review. We may send the minimum necessary content to our configured AI service provider to deliver the feature; we do not use Google user data to train general-purpose AI models.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">4. Google API data</h2>
                    <p>Sitewell only accesses Google data after an authorised user chooses to connect a Google account. We use that data to provide the Search Console and Google Business Profile features described above, including search reporting, profile health checks, review syncing, review-reply drafts, and posts or profile updates that an authorised user approves.</p>
                    <p>We do not sell Google user data, use it for advertising, or transfer it for creditworthiness, lending, or unrelated purposes. We share it only with service providers where necessary to operate or secure the requested feature, comply with law, or act with the user’s explicit direction.</p>
                    <p>Sitewell’s use and transfer to any other app of information received from Google APIs adheres to the <a href="https://developers.google.com/terms/api-services-user-data-policy" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>
                    <p>You can disconnect Google within Sitewell or revoke Sitewell’s access from your Google Account. Disconnecting stops new collection through that connection and removes the stored connection credentials, subject to limited backup, security, and legal-retention requirements.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">5. Sharing and international transfers</h2>
                    <p>We may share information with vetted providers that support hosting, infrastructure, email delivery, payments, customer support, security, analytics, AI-assisted features, and integrations requested by you. We may also disclose information when required by law, to protect people or the service, in connection with a business reorganisation, or with your direction.</p>
                    <p>Some providers process information outside the United Kingdom. Where required, we use recognised safeguards such as adequacy regulations or approved contractual protections.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">6. Retention and security</h2>
                    <p>We keep information only for as long as reasonably necessary for the purpose for which it was collected, to provide the service, resolve disputes, maintain security and audit records, and meet legal, tax, and accounting obligations. Retention depends on the type of record, account status, customer instructions, and applicable legal requirements.</p>
                    <p>We use administrative and technical measures designed to protect information. These include access controls, encrypted transport, encryption of stored OAuth credentials, restricted staff access, backups, and monitoring. No system can guarantee absolute security.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">7. Cookies</h2>
                    <p>Sitewell uses cookies and similar storage needed for login sessions, security, preferences, and core service operation. We may also use limited measurement technologies where permitted. You can control cookies through your browser, but blocking essential cookies may prevent account features from working.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">8. Your rights</h2>
                    <p>Depending on the circumstances, UK data-protection law may give you rights to access, correct, erase, restrict, or receive your personal information; object to processing; withdraw consent; and complain to the Information Commissioner’s Office. These rights can be limited by law and may depend on the lawful basis for processing.</p>
                    <p>Contact us through our <a href="{{ route('marketing.contact') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">contact page</a> to exercise a right. If your information was submitted to one of our customers through their website, contact that customer first because they usually control that information.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">9. Children and changes</h2>
                    <p>Sitewell is a business service and is not directed to children. We do not knowingly collect children’s information through customer accounts.</p>
                    <p>We may update this policy as the service or legal requirements change. We will publish the revised policy here, update its effective date, and provide additional notice where a material change requires it.</p>
                </section>
            </div>
        </div>
    </article>
@endsection
