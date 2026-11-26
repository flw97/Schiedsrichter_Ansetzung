# Added
- Success and error messages use vue3-toastify (`frontend/src/notify.ts`) instead of the Vuetify snackbar queue
- `GET /api/notifications?after={id}` returns the signed-in user's newer push messages (at most 50)
- While a session is open the frontend polls that endpoint every second, toasts new messages, and adds them to the bell

# Changed
- `StateController::notifications` reads `push_messages` for the current user with `id` greater than `after`
- `useAppStore` no longer keeps a `toasts` list. `act()` still reloads `/state` and toasts the action's `pushes` immediately; the poll covers messages that arrive without a local action
