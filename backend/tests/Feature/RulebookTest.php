<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RulebookTest extends ApiTestCase
{
    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('regeln.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    public function test_upload_new_version_read_confirmation_and_delete(): void
    {
        Storage::fake();
        $this->as('s.weber');
        $this->post('/api/rulebooks', ['title' => 'Spielregeln', 'category' => 'Spielregeln', 'version' => '2026', 'mandatory' => '1', 'file' => UploadedFile::fake()->createWithContent('x.pdf', 'kein pdf')], ['Accept' => 'application/json'])->assertStatus(422);
        $id = $this->post('/api/rulebooks', ['title' => 'Spielregeln', 'category' => 'Spielregeln', 'version' => '2026', 'mandatory' => '1', 'file' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(5, 'pushes')->json('id');
        $this->post("/api/rulebooks/{$id}/versions", ['version' => '2027', 'mandatory' => '1', 'file' => $this->pdf()], ['Accept' => 'application/json'])->assertOk();

        $this->as('k.rehberg');
        $this->get("/api/rulebooks/{$id}/file")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->postJson("/api/rulebooks/{$id}/read")->assertOk();
        $this->assertDatabaseHas('rulebook_reads', ['rulebook_id' => $id, 'version' => '2027']);
        $this->deleteJson("/api/rulebooks/{$id}")->assertForbidden();

        $this->as('s.weber');
        $this->deleteJson("/api/rulebooks/{$id}")->assertOk();
        $this->assertSame([], Storage::allFiles('rulebooks'));
    }
}
