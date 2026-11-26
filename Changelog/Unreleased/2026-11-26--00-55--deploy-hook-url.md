# Fixed
- The finished upload exited 22 because `curl` got 404 for `/api/public/<hook>.php`. That run still used `FTP_PATH=/ansetzung`, so the hook was stored in `/ansetzung/ansetzung/` and was not on the public URL. The `mv`/`rm` 550 lines were missing legacy files and did not stop the script.
- The one-off hook is now uploaded to the vhost root and called as `/<random>.php`. `post-deploy.php` boots `api/vendor` and `api/bootstrap` from there. Those legacy `mv`/`rm` commands are gone.
