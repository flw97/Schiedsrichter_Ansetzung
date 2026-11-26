// Kurze Rückmeldungen. Push-Mitteilungen kommen zusätzlich vom Backend (siehe stores/app.ts).

import { toast, updateGlobalOptions } from 'vue3-toastify'

/** Helles oder dunkles Toastify-Schema. Die Farben selbst stehen in styles/main.css. */
export function syncToastTheme (dark: boolean) {
  updateGlobalOptions({ theme: dark ? 'dark' : 'light' })
}

export function notifySuccess (text: string) {
  toast.success(text)
}

export function notifyError (text: string) {
  toast.error(text)
}

export function notifyPush (text: string) {
  toast.info(text)
}
