import { createHash } from 'node:crypto'

export function setup() {
  // Polyfill crypto.hash for Node < 21.7 (required by @vitejs/plugin-vue v6+)
  const cryptoGlobal = globalThis.crypto as Record<string, unknown>
  if (cryptoGlobal && typeof cryptoGlobal.hash !== 'function') {
    cryptoGlobal.hash = (algorithm: string, data: string) =>
      createHash(algorithm).update(data).digest('hex')
  }
}
