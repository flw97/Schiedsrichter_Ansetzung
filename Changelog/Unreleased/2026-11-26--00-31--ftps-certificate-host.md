# Fixed
- Deploy FTPS failed with `certificate common name doesn't match requested host name 'hosting256340.ae92d.netcup.net'`. The server certificate is `*.netcup.net`, which covers `ae92d.netcup.net` (same address, `202.61.233.45`) and not the extra customer label. `FTP_HOST` in `.github/workflows/ci.yml` is now `ae92d.netcup.net`.
