<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referee;
use App\Models\Rulebook;
use App\Models\RulebookVersion;
use App\Services\Push;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Regelwerke als PDF: Admins stellen ein, alle sehen die aktuelle Fassung, frühere Fassungen nur Admins. */
class RulebookController extends Controller
{
    public const CATEGORIES = ['Spielregeln', 'Wettspielordnung', 'Durchführungsbestimmungen', 'Schiedsrichter-Leitfaden', 'Formulare', 'Sonstiges'];

    public function __construct(private Push $push) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'category' => 'required|in:'.implode(',', self::CATEGORIES),
            ...$this->versionRules(),
        ]);
        $book = Rulebook::create([...collect($data)->except('file')->all(), ...$this->storeFile($request->file('file')), 'updated_by' => $request->user()->name]);
        $this->announce($book);

        return $this->done(['id' => $book->id]);
    }

    /** Neue Fassung einstellen; die bisherige wandert in den Verlauf. */
    public function newVersion(Request $request, Rulebook $rulebook): JsonResponse
    {
        $data = $request->validate($this->versionRules());
        $rulebook->versions()->create([...$rulebook->only(['version', 'valid_from', 'filename', 'path']), 'replaced_at' => now()]);
        $rulebook->update([...collect($data)->except('file')->all(), ...$this->storeFile($request->file('file')), 'updated_by' => $request->user()->name]);
        $this->announce($rulebook);

        return $this->done();
    }

    public function destroy(Rulebook $rulebook): JsonResponse
    {
        Storage::delete([$rulebook->path, ...$rulebook->versions->pluck('path')]);
        $rulebook->delete();

        return $this->done();
    }

    public function file(Rulebook $rulebook, ?RulebookVersion $version = null): StreamedResponse
    {
        abort_if($version && $version->rulebook_id !== $rulebook->id, 404);
        $doc = $version ?? $rulebook;

        return Storage::download($doc->path, $doc->filename, ['Content-Type' => 'application/pdf']);
    }

    public function confirmRead(Request $request, Rulebook $rulebook): JsonResponse
    {
        DB::table('rulebook_reads')->updateOrInsert(
            ['rulebook_id' => $rulebook->id, 'user_id' => $request->user()->id],
            ['version' => $rulebook->version, 'updated_at' => now(), 'created_at' => now()],
        );

        return $this->done();
    }

    private function versionRules(): array
    {
        return [
            'version' => 'required|string|max:50',
            'valid_from' => 'nullable|string|max:20',
            'mandatory' => 'boolean',
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480', function (string $attr, UploadedFile $file, \Closure $fail) {
                // Dateikennung prüfen, nicht nur Endung bzw. angegebenen Typ
                if (file_get_contents($file->getRealPath(), length: 5) !== '%PDF-') {
                    $fail('Die Datei ist kein gültiges PDF (Dateikennung fehlt).');
                }
            }],
        ];
    }

    private function storeFile(UploadedFile $file): array
    {
        return ['path' => $file->store('rulebooks'), 'filename' => $file->getClientOriginalName(), 'size' => $file->getSize()];
    }

    private function announce(Rulebook $book): void
    {
        if ($book->mandatory) {
            Referee::all()->each(fn (Referee $r) => $this->push->referee($r, "Neue Pflichtlektüre — {$book->title}, Version {$book->version}. Bitte Kenntnisnahme bestätigen."));
        }
    }
}
