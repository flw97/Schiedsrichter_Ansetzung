<?php

namespace App\Services;

use App\Models\PushMessage;
use App\Models\Referee;
use App\Models\User;

/**
 * Push-Mitteilungen: werden beim Empfänger gespeichert (Glocke) und für die auslösende Person
 * als Bestätigung gesammelt. Die E-Mail dazu verschickt der Cron-Endpunkt, nicht diese Anfrage.
 */
class Push
{
    /** @var list<string> */
    private array $sent = [];

    public function referee(Referee $referee, string $text, string $as = ''): void
    {
        $this->send("Push an {$referee->name}".($referee->phone ? " ({$referee->phone})" : '')."{$as}: {$text}", $referee->user()->pluck('id')->all());
    }

    /** Push an alle Ansetzer (Admins und Master-Admins). */
    public function admins(string $text): void
    {
        $this->send("Push an Ansetzer: {$text}", User::whereIn('role', ['master', 'admin'])->where('active', true)->pluck('id')->all());
    }

    /** @param list<int> $userIds */
    private function send(string $text, array $userIds): void
    {
        foreach ($userIds as $id) {
            PushMessage::create(['user_id' => $id, 'text' => $text]);
        }
        $this->sent[] = $text;
    }

    /** @return list<string> */
    public function sent(): array
    {
        return $this->sent;
    }
}
