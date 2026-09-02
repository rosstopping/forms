@extends('layouts.marketing')

@section('title', 'Terms of service')
@section('meta_description', 'The terms governing access to and use of Sitewell website care and connected services.')

@section('content')
    <article class="py-16 sm:py-24">
        <div class="mx-auto max-w-4xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">Legal</p>
            <h1 class="mt-5 font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Terms of service</h1>
            <p class="mt-6 max-w-2xl text-pretty text-lg text-ink/65">Terms for using Sitewell’s website care, monitoring, form, lead, content, and connected-platform services.</p>
            <p class="mt-4 text-sm text-ink/50">Effective date: 2 September 2026</p>

            <div class="mt-14 space-y-12 text-base leading-7 text-ink/70">
                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">1. Agreement and eligibility</h2>
                    <p>These terms form an agreement between you and Sitewell when you access or use the service. If you use Sitewell for an organisation, you confirm that you have authority to accept these terms on its behalf. Sitewell is intended for business use by people aged 18 or over.</p>
                    <p>Our <a href="{{ route('marketing.privacy') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Privacy Policy</a> explains how we handle personal information.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">2. Accounts and access</h2>
                    <p>You must provide accurate account information, keep credentials secure, use reasonable safeguards for authorised users, and notify us promptly if you suspect unauthorised access. You are responsible for activity performed through your account and for ensuring that people you invite have appropriate authority.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">3. The service</h2>
                    <p>Sitewell may provide website setup and care, health and search reporting, forms and lead management, email workflows, content recommendations, connected-platform tools, and related support. Your plan, order, or written proposal determines the features and service levels included for you.</p>
                    <p>We may improve or alter the service over time. If a change materially reduces a paid core feature, we will give reasonable notice where practical.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">4. Charges, renewals, and cancellation</h2>
                    <p>Prices and billing intervals are shown when you subscribe or agreed in writing. Unless stated otherwise, subscriptions renew automatically for the same billing interval until cancelled. You authorise our payment provider to collect applicable charges and taxes using your selected payment method.</p>
                    <p>You may cancel through the available account tools or by contacting us. Cancellation stops future renewal and normally takes effect at the end of the paid billing period. Fees already paid are non-refundable except where required by law or expressly agreed otherwise. Your statutory rights, where they apply, are not affected.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">5. Your content and responsibilities</h2>
                    <p>You retain ownership of content and data you submit or connect. You give Sitewell a limited licence to host, copy, process, display, modify, and transmit that material only as needed to provide, secure, and improve the service and follow your instructions.</p>
                    <p>You are responsible for having the rights, permissions, notices, and lawful basis required for content and personal information you provide. You remain responsible for your website, published content, communications, regulatory obligations, and decisions made using Sitewell.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">6. Connected services</h2>
                    <p>You may choose to connect services operated by third parties, including Google and GitHub. You authorise Sitewell to access and use those services and their data only to provide the features you select. Your use of a connected service remains subject to that provider’s own terms and policies.</p>
                    <p>Third-party services may change, restrict, suspend, or discontinue their APIs. We are not responsible for a third party’s systems, content, decisions, or availability, but we will take reasonable steps to keep supported integrations working.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">7. Automated and AI-assisted features</h2>
                    <p>Sitewell may generate reports, recommendations, drafts, classifications, or other automated output. Such output can be incomplete or inaccurate and does not replace professional advice. You must review it and confirm that it is accurate, appropriate, lawful, and authorised before publishing, sending, or relying on it.</p>
                    <p>Features described as approval-first will not intentionally publish or send the relevant draft without an authorised user’s approval, except where you separately configure an automatic workflow.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">8. Acceptable use</h2>
                    <p>You must not use Sitewell to break the law; infringe rights; send unlawful, deceptive, or abusive communications; distribute malware; obtain unauthorised access; interfere with the service; bypass security or usage limits; scrape the service; or use it to build a competing product through systematic copying.</p>
                    <p>You must comply with applicable marketing, privacy, communications, content, and platform rules. We may investigate suspected misuse and suspend affected access where reasonably necessary to protect users, third parties, or the service.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">9. Our intellectual property</h2>
                    <p>Sitewell and its software, design, documentation, branding, and underlying technology belong to us or our licensors. Subject to these terms and payment of applicable charges, we grant you a limited, non-exclusive, non-transferable right to use the service for your internal business purposes during your subscription.</p>
                    <p>Feedback is voluntary. You allow us to use feedback without restriction or payment, provided we do not identify you publicly without permission.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">10. Availability and warranties</h2>
                    <p>We will provide the service with reasonable care and skill. Internet services cannot be uninterrupted or error-free, and results can depend on third-party systems, customer configuration, and changing search or platform behaviour. Except for terms that cannot lawfully be excluded, the service is provided without additional warranties or guarantees.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">11. Liability</h2>
                    <p>Nothing in these terms excludes or limits liability where doing so would be unlawful, including liability for fraud, fraudulent misrepresentation, or death or personal injury caused by negligence.</p>
                    <p>To the fullest extent permitted by law, neither party is liable for indirect or consequential loss, loss of profits, revenue, anticipated savings, goodwill, or business opportunity. Sitewell’s total liability arising from the service is limited to the fees you paid for the service during the 12 months before the event giving rise to the claim.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">12. Suspension and termination</h2>
                    <p>You may stop using Sitewell and cancel as described above. We may suspend or terminate access for material breach, non-payment, security risk, unlawful use, or conduct that could harm the service or others. Where practical, we will give notice and an opportunity to remedy a breach.</p>
                    <p>On termination, your right to use the service ends. Provisions concerning payment, ownership, confidentiality, liability, and any terms intended by their nature to continue will survive. Data handling after termination follows our Privacy Policy and any applicable customer agreement.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">13. General</h2>
                    <p>You may not transfer this agreement without our consent. We may transfer it as part of a reorganisation, financing, or sale of the relevant business. A delay in enforcing a right is not a waiver. If a provision is unenforceable, the remaining terms continue. These terms, together with an applicable order or written agreement, are the entire agreement about the service.</p>
                    <p>We may update these terms to reflect service, legal, or security changes. We will post the revised terms and give reasonable notice of material changes. Continued use after they take effect constitutes acceptance where permitted by law.</p>
                    <p>These terms are governed by the laws of England and Wales, and the courts of England and Wales have exclusive jurisdiction, except where mandatory law gives you another right.</p>
                </section>

                <section class="space-y-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight text-ink">14. Contact</h2>
                    <p>Questions about these terms can be sent through our <a href="{{ route('marketing.contact') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">contact page</a>.</p>
                </section>
            </div>
        </div>
    </article>
@endsection
