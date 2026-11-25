# Fixed
- Deploy login failed with `530 Login incorrect` after `FTP_HOST` was changed to `ae92d.netcup.net` in `2026-11-26--00-31--ftps-certificate-host.md`. That name matches `*.netcup.net`, but the FTP account is on `hosting256340.ae92d.netcup.net`.
- `FTP_HOST` is the account host again. `lftp` still requires TLS and a trusted certificate (`ssl:verify-certificate yes`) and only skips the hostname match (`ssl:check-hostname no`).
