# Added
- Login returns a JWT access token (`expires_in` 900 seconds) and a refresh token. `POST /api/refresh` rotates the refresh token and returns a new pair. The SPA renews the access token in the last minute and after a 401, and signs out only when refresh is rejected
- `users.token_version` and `refresh_tokens`. `User::revokeSessions()` bumps the version and deletes refresh tokens, so outstanding access tokens stop working immediately

# Changed
- API authentication uses the `jwt` guard (`Authorization: Bearer`) instead of Laravel Sanctum. Logout revokes the refresh token sent in the body and no longer requires a valid access token
- Password reset and account deactivation call `User::revokeSessions()`

# Fixed
- PHPUnit forces `CRON_TOKEN` empty, so the Docker value `local` does not open `/api/cron` during tests

# Removed
- Idle sign-out after 15 minutes (`AppServiceProvider` token check and the browser activity timer). `laravel/sanctum` and `personal_access_tokens` are no longer used
