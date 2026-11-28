<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Hoster-Cron: verschickt ausstehende Benachrichtigungs-Mails. Geschützt über CRON_TOKEN. */
class CronController extends Controller
{
    public function __invoke(Request $request, NotificationMailer $mailer): JsonResponse|Response
    {
        $expected = (string) config('services.cron.token');
        $given = (string) ($request->headers->get('X-Cron-Token') ?: $request->query('token', ''));
        abort_unless($expected !== '' && hash_equals($expected, $given), 403, 'Zugriff verweigert.');

        $result = $mailer->sendPending();
        if ($result['failed'] > 0) {
            return response()->json($result, 500);
        }
        if ($result['sent'] === 0) {
            return response()->noContent();
        }

        return response()->json($result);
    }
}
