<?php

declare(strict_types=1);

namespace Atlas\Support;

use Atlas\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The floating toolbar editors see on the live site, so they can hop between the page and its editor
 * (and look at drafts) without losing their place. Visitors never see it, and it is never cached publicly.
 */
final class AdminBar
{
    public static function allowedFor(Request $request): bool
    {
        return Gate::forUser($request->user())->allows('useAtlas');
    }

    /** Insert the bar just before </body>; documents without one are returned untouched. */
    public static function inject(string $html, Page $page): string
    {
        $at = strripos($html, '</body>');

        return $at === false ? $html : substr($html, 0, $at) . self::html($page) . substr($html, $at);
    }

    private static function html(Page $page): string
    {
        $edit = e(route('atlas.pages.edit', $page));
        $pages = e(route('atlas.index'));
        $status = $page->isPublished() ? 'published' : 'draft';
        $label = e(__('atlas::ui.' . $status));
        $editLabel = e(__('atlas::ui.adminbar_edit'));
        $pagesLabel = e(__('atlas::ui.pages'));
        $hideLabel = e(__('atlas::ui.adminbar_hide'));
        $css = self::css();

        return <<<HTML
<div id="atlas-adminbar" data-status="{$status}" role="region" aria-label="Atlas">
<style>{$css}</style>
<span class="atlas-ab__logo" aria-hidden="true">▲</span>
<span class="atlas-ab__pill">{$label}</span>
<a class="atlas-ab__btn atlas-ab__btn--primary" href="{$edit}">✎ <span>{$editLabel}</span></a>
<a class="atlas-ab__btn" href="{$pages}"><span>{$pagesLabel}</span></a>
<button type="button" class="atlas-ab__btn atlas-ab__x" aria-label="{$hideLabel}" title="{$hideLabel}" onclick="var b=document.getElementById('atlas-adminbar');b.classList.toggle('atlas-ab--min');try{localStorage.setItem('atlas-adminbar',b.classList.contains('atlas-ab--min')?'1':'')}catch(e){}">⌄</button>
<script>try{if(localStorage.getItem('atlas-adminbar'))document.getElementById('atlas-adminbar').classList.add('atlas-ab--min')}catch(e){}</script>
</div>
HTML;
    }

    private static function css(): string
    {
        return <<<'CSS'
#atlas-adminbar{all:initial;position:fixed;z-index:2147483000;left:16px;bottom:16px;display:flex;align-items:center;gap:6px;padding:6px;border-radius:999px;background:rgba(17,24,39,.92);color:#fff;font:600 13px/1 system-ui,-apple-system,"Segoe UI",sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.35);backdrop-filter:blur(10px);max-width:calc(100vw - 24px);box-sizing:border-box}
#atlas-adminbar *{box-sizing:border-box}
#atlas-adminbar .atlas-ab__logo{color:#a5b4fc;padding:0 4px 0 10px;font-size:15px}
#atlas-adminbar .atlas-ab__pill{padding:5px 9px;border-radius:999px;background:rgba(255,255,255,.12);font-size:11px;text-transform:uppercase;letter-spacing:.06em}
#atlas-adminbar[data-status=draft] .atlas-ab__pill{background:#f59e0b;color:#111827}
#atlas-adminbar .atlas-ab__btn{all:unset;box-sizing:border-box;cursor:pointer;display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:0 14px;border-radius:999px;color:#fff;font:inherit;font-size:13px;white-space:nowrap}
#atlas-adminbar .atlas-ab__btn:hover,#atlas-adminbar .atlas-ab__btn:focus-visible{background:rgba(255,255,255,.18)}
#atlas-adminbar .atlas-ab__btn--primary{background:#6366f1}
#atlas-adminbar .atlas-ab__btn--primary:hover{background:#818cf8}
#atlas-adminbar .atlas-ab__x{padding:0 10px}
#atlas-adminbar.atlas-ab--min{padding:0;gap:0;background:rgba(17,24,39,.75)}
#atlas-adminbar.atlas-ab--min>:not(.atlas-ab__x){display:none}
#atlas-adminbar.atlas-ab--min .atlas-ab__x{transform:rotate(180deg);min-height:34px;min-width:34px;justify-content:center;padding:0}
@media (max-width:520px){#atlas-adminbar{bottom:10px;left:10px}#atlas-adminbar .atlas-ab__btn:not(.atlas-ab__btn--primary):not(.atlas-ab__x) span{display:none}#atlas-adminbar .atlas-ab__btn:not(.atlas-ab__btn--primary):not(.atlas-ab__x)::before{content:"☰"}}
CSS;
    }
}
