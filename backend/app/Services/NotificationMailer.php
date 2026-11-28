<?php

namespace App\Services;

use App\Mail\NotificationsMail;
use App\Models\PushMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Verschickt noch nicht zugestellte Push-Mitteilungen gebündelt je Konto. */
class NotificationMailer
{
    /** @return array{sent: int, skipped: int, failed: int} */
    public function sendPending(): array
    {
        $pending = PushMessage::query()
            ->whereNull('emailed_at')
            ->with('user')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->groupBy('user_id');

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($pending as $messages) {
            $user = $messages->first()->user;
            if (! $this->reachable($user)) {
                $skipped += $messages->count();

                continue;
            }
            try {
                Mail::to($user->email)->send(new NotificationsMail($user, $messages));
                PushMessage::whereIn('id', $messages->modelKeys())->update(['emailed_at' => now()]);
                $sent += $messages->count();
            } catch (Throwable $e) {
                report($e);
                $failed += $messages->count();
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
    }

    private function reachable(?User $user): bool
    {
        return $user !== null && $user->active && filter_var($user->email, FILTER_VALIDATE_EMAIL);
    }
}
