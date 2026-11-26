# Fixed
- Refreshing the page no longer flashes the login form while a stored session is restored
 - `App.vue` waits for `GET /state` before rendering `LoginPage` when `sessionStorage` already has a token
