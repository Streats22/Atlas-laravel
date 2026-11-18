<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Http\Requests\ContactRequest;
use App\Models\Audience;
use App\Models\Contact;
use App\Services\TagSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(private readonly TagSync $tags) {}

    public function create(Audience $audience): View
    {
        return view('contacts.edit', [
            'audience' => $audience,
            'contact' => new Contact(['status' => ContactStatus::Subscribed]),
        ]);
    }

    public function store(ContactRequest $request, Audience $audience): RedirectResponse
    {
        $data = $request->safe()->except('tags');
        $contact = $audience->contacts()->create($data + [
            'subscribed_at' => $data['status'] === ContactStatus::Subscribed->value ? now() : null,
        ]);
        $this->tags->sync($contact, $request->tagNames());

        return redirect()->route('audiences.show', $audience)->with('status', 'Contact added.');
    }

    public function edit(Audience $audience, Contact $contact): View
    {
        $contact->load(['tags', 'sends.campaign']);

        return view('contacts.edit', ['audience' => $audience, 'contact' => $contact]);
    }

    public function update(ContactRequest $request, Audience $audience, Contact $contact): RedirectResponse
    {
        $data = $request->safe()->except('tags');
        if ($data['status'] === ContactStatus::Unsubscribed->value && $contact->status !== ContactStatus::Unsubscribed) {
            $data['unsubscribed_at'] = now();
        }
        $contact->update($data);
        $this->tags->sync($contact, $request->tagNames());

        return redirect()->route('audiences.show', $audience)->with('status', 'Contact saved.');
    }

    public function destroy(Audience $audience, Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('audiences.show', $audience)->with('status', 'Contact deleted.');
    }

    public function bulk(Request $request, Audience $audience): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['tag', 'untag', 'unsubscribe', 'delete'])],
            'tag' => ['required_if:action,tag,untag', 'nullable', 'string', 'max:60'],
        ]);
        $contacts = $audience->contacts()->whereKey($data['ids'])->get();

        foreach ($contacts as $contact) {
            match ($data['action']) {
                'tag' => $this->tags->attach($contact, $data['tag']),
                'untag' => $this->tags->detach($contact, $data['tag']),
                'unsubscribe' => $contact->unsubscribe(),
                'delete' => $contact->delete(),
            };
        }

        return back()->with('status', $contacts->count().' contacts updated.');
    }
}
