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
        $pages = collect($details['pages'])->prepend('Home')->push('Contact')
            ->unique(fn (string $page): string => mb_strtolower($page))->values();
        $navigation = $pages->map(fn (string $page): string => '<a href="'.$this->pathFor($page).'">'.$this->escape($page).'</a>')->implode('');
        $files = ['styles.css' => $this->styles()];

        foreach ($pages as $page) {
            $filename = $page === 'Home' ? 'index.html' : Str::slug($page).'.html';
            $files[$filename] = $this->page($details, $page, $navigation, $page === 'Contact');
        }

        return $files;
    }

    /** @param array{name: string, sector: string, description: string} $details */
    private function page(array $details, string $page, string $navigation, bool $hasContactForm): string
    {
        $name = $this->escape($details['name']);
        $sector = $this->escape($details['sector']);
        $description = $this->escape($details['description']);
        $title = $page === 'Home' ? $name : $this->escape($page).' | '.$name;
        $heading = $page === 'Home' ? $name : $this->escape($page);
        $eyebrow = $page === 'Home' ? $sector : $name;
        $content = $hasContactForm ? $this->contactForm() : '<p class="lede">'.$description.'</p><a class="button" href="contact.html">Start a conversation</a>';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{$description}">
    <title>{$title}</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header><a class="brand" href="index.html">{$name}</a><nav aria-label="Primary">{$navigation}</nav></header>
    <main><p class="eyebrow">{$eyebrow}</p><h1>{$heading}</h1>{$content}</main>
    <footer>&copy; {$name}</footer>
</body>
</html>
HTML;
    }

    private function contactForm(): string
    {
        return <<<'HTML'
<p class="lede">Tell us what you need and we’ll get back to you.</p>
<form method="POST" action="https://sitewell.digizu.co.uk/submit">
    <input type="hidden" name="_form_name" value="Contact form">
    <div class="honeypot" aria-hidden="true"><label>Leave this field empty<input type="text" name="_honeypot" tabindex="-1" autocomplete="off"></label></div>
    <label>Name<input type="text" name="name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>Message<textarea name="message" rows="6" required></textarea></label>
    <button type="submit">Send enquiry</button>
</form>
HTML;
    }

    private function pathFor(string $page): string
    {
        return $page === 'Home' ? 'index.html' : Str::slug($page).'.html';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function styles(): string
    {
        return <<<'CSS'
:root{color-scheme:light;--ink:#102a2b;--paper:#f6faf8}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font-family:ui-sans-serif,system-ui,sans-serif;line-height:1.6}header,main,footer{width:min(72rem,calc(100% - 2rem));margin-inline:auto}header{display:flex;align-items:center;justify-content:space-between;gap:2rem;padding-block:1.5rem}.brand{font-size:1.15rem;font-weight:800}nav{display:flex;flex-wrap:wrap;gap:1rem}a{color:inherit;text-decoration:none}nav a:hover{text-decoration:underline}main{min-height:72vh;padding-block:clamp(5rem,12vw,10rem)}.eyebrow{color:#0f766e;font-weight:800;letter-spacing:.12em;text-transform:uppercase}h1{max-width:14ch;margin:.25rem 0 1.5rem;font-size:clamp(3rem,8vw,7rem);line-height:.95;letter-spacing:-.05em}.lede{max-width:42rem;font-size:clamp(1.15rem,2vw,1.5rem)}.button,button{display:inline-block;margin-top:1.5rem;border:0;border-radius:.5rem;background:var(--ink);color:white;padding:.85rem 1.2rem;font:inherit;font-weight:700;cursor:pointer}form{display:grid;max-width:40rem;gap:1rem}label{display:grid;gap:.35rem;font-weight:700}input,textarea{width:100%;border:1px solid #a8b9b5;border-radius:.5rem;background:white;padding:.8rem;font:inherit}.honeypot{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}footer{border-top:1px solid #dbe7e3;padding-block:2rem}@media(max-width:42rem){header{align-items:flex-start;flex-direction:column}}
CSS;
    }
}
