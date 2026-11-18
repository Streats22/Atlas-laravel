<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Services\ContactImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactImportController extends Controller
{
    public function create(Audience $audience): View
    {
        return view('contacts.import', ['audience' => $audience]);
    }

    public function store(Request $request, Audience $audience, ContactImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'status' => ['required', Rule::in(['subscribed', 'unsubscribed'])],
            'tag' => ['nullable', 'string', 'max:60'],
            'consent' => ['accepted'],
        ]);

        $result = $importer->import($audience, $data['file']->getRealPath(), $data['status'], $data['tag'] ?? null);

        return redirect()->route('audiences.show', $audience)->with(
            'status',
            "Import finished: {$result['created']} added, {$result['updated']} updated, {$result['skipped']} skipped.",
        );
    }

    public function export(Audience $audience): StreamedResponse
    {
        return response()->streamDownload(function () use ($audience): void {
            $out = fopen('php://output', 'w');
            $keys = $audience->fields->pluck('key')->all();
            fputcsv($out, ['email', 'first_name', 'last_name', ...$keys, 'status', 'tags', 'subscribed_at', 'consent_at']);
            $audience->contacts()->with('tags')->chunkById(500, function ($contacts) use ($out, $keys): void {
                foreach ($contacts as $c) {
                    fputcsv($out, array_map([self::class, 'csvSafe'], [
                        $c->email, $c->first_name, $c->last_name, ...array_map(fn ($k) => $c->field($k), $keys), $c->status->value,
                        $c->tags->pluck('name')->implode(', '), $c->subscribed_at?->toDateTimeString(), $c->consent_at?->toDateTimeString(),
                    ]));
                }
            });
            fclose($out);
        }, str($audience->name)->slug().'-contacts.csv', ['Content-Type' => 'text/csv']);
    }

    /** Prevent CSV/formula injection when the export is opened in a spreadsheet. */
    public static function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
