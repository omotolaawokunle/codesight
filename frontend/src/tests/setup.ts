import { config } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach } from 'vitest'
import { createHash } from 'node:crypto'

// Polyfill crypto.hash for Node < 21.7 (used by @vitejs/plugin-vue internals)
if (typeof (globalThis.crypto as Record<string, unknown>)?.hash !== 'function') {
  Object.defineProperty(globalThis.crypto, 'hash', {
    value: (algorithm: string, data: string) => createHash(algorithm).update(data).digest('hex'),
    writable: true,
    configurable: true,
  })
}

beforeEach(() => {
  setActivePinia(createPinia())
})

// Suppress Vue warnings in tests to keep output clean.
config.global.config.warnHandler = () => {}
