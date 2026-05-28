import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

window.Pusher = Pusher

let _echo: Echo<'reverb'> | null = null

export function getEcho(): Echo<'reverb'> | null {
  if (_echo) return _echo

  const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined
  if (!key || key.startsWith('${')) return null   // unexpanded placeholder

  try {
    _echo = new Echo({
      broadcaster: 'reverb',
      key,
      wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
      wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/broadcasting/auth',
      auth: {
        headers: { Accept: 'application/json' },
      },
      withCredentials: true,
    })
  } catch (e) {
    console.warn('[Echo] WebSocket init failed, real-time disabled.', e)
    return null
  }

  return _echo
}

export default getEcho
