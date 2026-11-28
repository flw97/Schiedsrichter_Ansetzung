# Added
- `GET` and `POST /api/cron` send pending notification mails. `CRON_TOKEN` is required (`?token=` or header `X-Cron-Token`); an empty token keeps the endpoint closed
- `push_messages.emailed_at`: set after a successful mail. Rows that already existed are stamped in the migration so the first cron does not mail the old bell history
- `NotificationMailer` bundles each account's pending rows into one mail. Accounts without an email and inactive accounts stay pending
- Password reset: `POST /api/password/forgot` (`login` is username or email) and `POST /api/password/reset`. The link opens `{FRONTEND_URL}/passwort-zuruecksetzen` and is valid for 60 minutes. The response does not say whether the account exists. A successful reset revokes that account's Sanctum tokens
- Login page links to the reset request. Both public pages use `AuthScreen`

```php
Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
Route::match(['get', 'post'], '/cron', CronController::class)->middleware('throttle:30,1');
```

# Changed
- Production env needs `CRON_TOKEN`, mail settings and `APP_URL` (or `FRONTEND_URL`) before the next deploy, because `config:cache` reads `.env.production` at deploy time
