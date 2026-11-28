# Changed
- Refresh tokens expire after 7 days (`AuthTokens::REFRESH_DAYS`). A successful `POST /api/refresh` still rotates the token and starts a new 7-day lifetime. This replaces the previous 30-day lifetime in `2026-11-27--12-45--jwt-refresh-session.md`
