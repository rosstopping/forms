<?php

namespace App\Services;

use Illuminate\Support\Str;

class StaticWebsiteGenerator
{
    /** @param array{name: string, sector: string, description: string, pages: array<int, string>} $details
     * @return array<string, string>
     */
    public function generate(array $details): array
    {
        $brief = $this->prompt($details);
        $name = $this->escape($details['name']);
        $description = $this->escape($details['description']);

        return [
            '.gitignore' => "node_modules/\n_site/\nsrc/assets/css/site.css\n",
            '.github/copilot-instructions.md' => "Follow BUILD_SITE.md for the website's product, design, content, accessibility, form, and verification requirements. Use Eleventy and Tailwind CSS v4. Do not replace the chosen stack.\n",
            'BUILD_SITE.md' => $brief,
            'package.json' => $this->packageJson($details['name']),
            'eleventy.config.js' => <<<'JS'
export default function (eleventyConfig) {
  eleventyConfig.addPassthroughCopy({ "src/assets": "assets" });

  return {
    dir: { input: "src", output: "_site", includes: "_includes", data: "_data" },
    htmlTemplateEngine: "njk",
    markdownTemplateEngine: "njk",
  };
}
JS,
            'src/assets/css/input.css' => <<<'CSS'
@import "tailwindcss" source("../../");

@theme {
  --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
}
CSS,
            'src/_data/site.json' => json_encode([
                'name' => $details['name'],
                'sector' => $details['sector'],
                'description' => $details['description'],
                'pages' => collect($details['pages'])->prepend('Home')->push('Contact')->unique()->values()->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
            'src/_includes/base.njk' => $this->layout(),
            'src/index.njk' => <<<HTML
---
layout: base.njk
title: {$name}
description: {$description}
---
<section class="mx-auto flex min-h-[70vh] max-w-6xl items-center px-6 py-24 lg:px-8">
  <div class="max-w-3xl">
    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-700">{$this->escape($details['sector'])}</p>
    <h1 class="mt-5 text-5xl font-semibold tracking-tight text-slate-950 sm:text-7xl">{$name}</h1>
    <p class="mt-7 max-w-2xl text-xl leading-8 text-slate-600">{$description}</p>
    <a href="/contact/" class="mt-10 inline-flex rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-teal-700">Start a conversation</a>
  </div>
</section>
HTML,
            'src/contact.njk' => $this->contactPage($details['name']),
            'netlify.toml' => "[build]\n  command = \"npm run build\"\n  publish = \"_site\"\n",
        ];
    }

    /** @param array{name: string, sector: string, description: string, pages: array<int, string>} $details */
    public function prompt(array $details): string
    {
        $pages = collect($details['pages'])->prepend('Home')->push('Contact')
            ->unique(fn (string $page): string => mb_strtolower($page))->implode(', ');

        return Str::limit(<<<PROMPT
# Copilot website build

Create a production-quality marketing website for **{$details['name']}**, operating in the **{$details['sector']}** sector.

## Business brief

{$details['description']}

Required pages: {$pages}.

## Creative direction

- Design a distinctive, fully fledged visual identity specifically informed by the {$details['sector']} industry. Do not produce a generic SaaS landing page.
- Establish an intentional colour system, typography hierarchy, spacing rhythm, imagery direction, and reusable component language.
- Use strong art direction, considered responsive composition, polished navigation and footer, meaningful page transitions or restrained interaction where helpful, and varied editorial layouts.
- Write useful British-English page copy based only on the supplied brief. Do not invent awards, certifications, locations, people, prices, statistics, testimonials, clients, or guarantees.
- Every requested page must have substantial, differentiated content and a clear conversion path.

## Required implementation

- Keep Eleventy as the static-site generator and Tailwind CSS v4 as the styling system.
- Use the existing CSS-first Tailwind setup with `@import "tailwindcss"`; do not add a legacy `tailwind.config.js` or deprecated v3 utilities.
- Build reusable Nunjucks layouts/components and data-driven navigation. Use clean semantic HTML.
- Make the result responsive from small phones through large screens, keyboard accessible, WCAG-conscious, and respectful of reduced-motion preferences.
- Add unique page titles, meta descriptions, canonical-ready URLs, Open Graph metadata, favicon treatment, and appropriate structured data supported by the brief.
- Optimise image dimensions/loading and avoid layout shift. Use properly licensed remote imagery only when the source and licence are clear; otherwise use art-directed CSS/SVG treatments.

## Lead form — mandatory

The Contact page must contain this functional form contract exactly:

```html
<form method="POST" action="https://sitewell.digizu.co.uk/submit">
  <input type="hidden" name="_form_name" value="Contact form">
  <div class="..." style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
    <label>Leave this field empty<input type="text" name="_honeypot" tabindex="-1" autocomplete="off"></label>
  </div>
  <label>Name<input type="text" name="name" required></label>
  <label>Email<input type="email" name="email" required></label>
  <label>Message<textarea name="message" required></textarea></label>
  <button type="submit">Send enquiry</button>
</form>
```

The fields may be visually composed with Tailwind classes, but do not change the action, method, hidden field, honeypot name, or public field names.

## Completion checklist

1. Implement every requested page and all shared components.
2. Run `npm install`, `npm run build`, and `npm run check`.
3. Resolve all build errors, broken internal links, missing assets, overflow, and obvious accessibility issues.
4. Keep generated `_site` output uncommitted.
5. Open a pull request summarising the visual concept, page structure, form integration, and verification performed.
PROMPT, 30000, "\n[Build brief truncated.]\n");
    }

    private function packageJson(string $name): string
    {
        return json_encode([
            'name' => Str::slug($name).'-website',
            'version' => '1.0.0',
            'private' => true,
            'type' => 'module',
            'scripts' => [
                'dev' => 'concurrently "npx @tailwindcss/cli -i ./src/assets/css/input.css -o ./src/assets/css/site.css --watch" "npx @11ty/eleventy --serve"',
                'build:css' => 'npx @tailwindcss/cli -i ./src/assets/css/input.css -o ./src/assets/css/site.css --minify',
                'build' => 'npm run build:css && npx @11ty/eleventy',
                'check' => 'npm run build',
            ],
            'devDependencies' => [
                '@11ty/eleventy' => '^3.1.0',
                '@tailwindcss/cli' => '^4.0.0',
                'concurrently' => '^9.0.0',
                'tailwindcss' => '^4.0.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    private function layout(): string
    {
        return <<<'NJK'
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="{{ description or site.description }}">
  <title>{{ title }}{% if title != site.name %} | {{ site.name }}{% endif %}</title>
  <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="bg-stone-50 font-sans text-slate-950 antialiased">
  <header class="border-b border-slate-950/10"><nav class="mx-auto flex max-w-6xl items-center justify-between gap-8 px-6 py-5 lg:px-8" aria-label="Primary"><a href="/" class="text-lg font-semibold">{{ site.name }}</a><a href="/contact/" class="text-sm font-semibold text-teal-700">Contact</a></nav></header>
  <main>{{ content | safe }}</main>
  <footer class="border-t border-slate-950/10"><div class="mx-auto max-w-6xl px-6 py-10 text-sm text-slate-600 lg:px-8">&copy; {{ site.name }}</div></footer>
</body>
</html>
NJK;
    }

    private function contactPage(string $name): string
    {
        return <<<NJK
---
layout: base.njk
title: Contact
description: Contact {$this->escape($name)}.
permalink: /contact/index.html
---
<section class="mx-auto max-w-3xl px-6 py-20 lg:px-8"><h1 class="text-5xl font-semibold tracking-tight">Contact us</h1><p class="mt-5 text-lg text-slate-600">Tell us what you need and we’ll get back to you.</p><form method="POST" action="https://sitewell.digizu.co.uk/submit" class="mt-10 grid gap-5"><input type="hidden" name="_form_name" value="Contact form"><div class="absolute left-[-9999px] size-px overflow-hidden" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Leave this field empty<input type="text" name="_honeypot" tabindex="-1" autocomplete="off"></label></div><label class="grid gap-2 font-medium">Name<input class="rounded-xl border border-slate-300 bg-white px-4 py-3" type="text" name="name" required></label><label class="grid gap-2 font-medium">Email<input class="rounded-xl border border-slate-300 bg-white px-4 py-3" type="email" name="email" required></label><label class="grid gap-2 font-medium">Message<textarea class="rounded-xl border border-slate-300 bg-white px-4 py-3" name="message" rows="6" required></textarea></label><button class="justify-self-start rounded-full bg-slate-950 px-6 py-3 font-semibold text-white" type="submit">Send enquiry</button></form></section>
NJK;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
