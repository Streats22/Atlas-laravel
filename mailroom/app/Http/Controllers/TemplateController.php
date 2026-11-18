<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Builder\DesignRenderer;
use App\Models\Campaign;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        return view('templates.index', ['templates' => EmailTemplate::query()->latest()->get()]);
    }

    public function show(EmailTemplate $template): Response
    {
        return response($template->html)->header('Content-Security-Policy', "script-src 'none'");
    }

    /** Save a campaign's current content as a reusable template. */
    public function store(Request $request, DesignRenderer $renderer): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'campaign_id' => ['required', 'exists:campaigns,id']]);
        $campaign = Campaign::query()->findOrFail($data['campaign_id']);
        EmailTemplate::query()->create([
            'name' => $data['name'],
            'design' => $campaign->design,
            'html' => $campaign->design ? $renderer->render($campaign->design) : $campaign->html,
        ]);

        return back()->with('status', 'Saved as template “'.$data['name'].'”.');
    }

    public function destroy(EmailTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('templates.index')->with('status', 'Template deleted.');
    }
}
