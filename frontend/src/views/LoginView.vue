<template>
  <div class="min-h-screen bg-slate-950 flex">
    <!-- Left brand panel (hidden on mobile) -->
    <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between p-12 relative overflow-hidden">
      <!-- Subtle grid background -->
      <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 40px 40px;" />

      <!-- Animated green glow -->
      <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 rounded-full blur-3xl opacity-20"
        style="background: radial-gradient(circle, #22c55e, transparent 70%);" />

      <div class="relative z-10">
        <div class="flex items-center gap-2">
          <span class="text-2xl font-mono font-bold text-green-400 tracking-tight">codesight</span>
          <span class="text-xs font-mono text-slate-600 border border-slate-800 px-1.5 py-0.5 rounded">v1.0</span>
        </div>
      </div>

      <div class="relative z-10 space-y-8">
        <div class="space-y-4">
          <h1 class="text-4xl xl:text-5xl font-mono font-bold text-slate-100 leading-tight">
            Your codebase,<br /><span class="text-green-400">understood.</span>
          </h1>
          <p class="text-slate-400 text-lg leading-relaxed max-w-md">
            Ask natural-language questions, debug errors, and explore your repositories — all powered by AI grounded in your actual code.
          </p>
        </div>

        <!-- Feature list -->
        <ul class="space-y-3">
          <li v-for="feature in features" :key="feature" class="flex items-center gap-3 text-slate-400 text-sm">
            <svg class="w-4 h-4 text-green-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            {{ feature }}
          </li>
        </ul>

        <!-- Terminal-style code snippet -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 font-mono text-sm">
          <div class="flex items-center gap-1.5 mb-3">
            <span class="w-3 h-3 rounded-full bg-red-500/60" />
            <span class="w-3 h-3 rounded-full bg-yellow-500/60" />
            <span class="w-3 h-3 rounded-full bg-green-500/60" />
          </div>
          <p class="text-slate-500 text-xs mb-1"># Ask anything about your code</p>
          <p class="text-green-400">$ <span class="text-slate-300">codesight ask</span> <span class="text-yellow-400">"How does auth work?"</span></p>
          <p class="text-slate-400 mt-2 text-xs">→ Found 3 relevant files: AuthService.php, LoginController.php...</p>
          <span class="inline-block w-2 h-4 bg-green-400 animate-pulse mt-1" />
        </div>
      </div>

      <div class="relative z-10 text-xs text-slate-700">
        © {{ new Date().getFullYear() }} Codesight. All rights reserved.
      </div>
    </div>

    <!-- Right form panel -->
    <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 lg:px-12">
      <!-- Mobile logo -->
      <div class="lg:hidden mb-8">
        <span class="text-2xl font-mono font-bold text-green-400">codesight</span>
      </div>

      <div class="w-full max-w-sm animate-slide-up">
        <div class="mb-8">
          <h2 class="text-2xl font-mono font-bold text-slate-100">Sign in</h2>
          <p class="text-slate-400 text-sm mt-1">Welcome back. Enter your credentials to continue.</p>
        </div>

        <!-- Global error -->
        <div v-if="globalError" class="mb-5 flex items-start gap-2 bg-red-950/50 border border-red-800/60 rounded-xl px-4 py-3 text-sm text-red-400">
          <svg class="w-4 h-4 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          {{ globalError }}
        </div>

        <form class="space-y-5" @submit.prevent="handleLogin">
          <!-- Email -->
          <div class="space-y-1.5">
            <label for="email" class="block text-sm font-medium text-slate-300">Email address</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              required
              placeholder="you@example.com"
              class="w-full bg-slate-900 border rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-2 transition-colors"
              :class="fieldErrors.email
                ? 'border-red-700 focus:ring-red-500/30'
                : 'border-slate-700 focus:border-green-500 focus:ring-green-500/20'"
            />
            <p v-if="fieldErrors.email" class="text-xs text-red-400">{{ fieldErrors.email }}</p>
          </div>

          <!-- Password -->
          <div class="space-y-1.5">
            <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
            <div class="relative">
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                required
                placeholder="••••••••"
                class="w-full bg-slate-900 border rounded-xl px-4 py-2.5 pr-10 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-2 transition-colors"
                :class="fieldErrors.password
                  ? 'border-red-700 focus:ring-red-500/30'
                  : 'border-slate-700 focus:border-green-500 focus:ring-green-500/20'"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-3 flex items-center text-slate-600 hover:text-slate-400 cursor-pointer transition-colors"
                @click="showPassword = !showPassword"
              >
                <svg v-if="showPassword" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
                  <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                  <line x1="1" y1="1" x2="23" y2="23" />
                </svg>
                <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              </button>
            </div>
            <p v-if="fieldErrors.password" class="text-xs text-red-400">{{ fieldErrors.password }}</p>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="isLoading"
            class="w-full flex items-center justify-center gap-2 bg-green-500 hover:bg-green-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 font-bold text-sm rounded-xl px-4 py-2.5 transition-colors duration-150 cursor-pointer"
          >
            <svg v-if="isLoading" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12a9 9 0 1 1-6.219-8.56" />
            </svg>
            {{ isLoading ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
          Don't have an account?
          <RouterLink to="/register" class="text-green-400 hover:text-green-300 font-medium transition-colors">
            Create one
          </RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { extractValidationErrors } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = ref({ email: '', password: '' })
const showPassword = ref(false)
const isLoading = ref(false)
const globalError = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})

const features = [
  'Ask natural-language questions about your codebase',
  'Debug errors with AI-powered stack trace analysis',
  'Explore architecture and design patterns instantly',
  'Supports GitHub, GitLab, and Bitbucket',
]

async function handleLogin() {
  globalError.value = null
  fieldErrors.value = {}
  isLoading.value = true
  try {
    await authStore.login(form.value)
    const redirect = (route.query.redirect as string) || '/'
    await router.push(redirect)
  } catch (err: unknown) {
    const fields = extractValidationErrors(err)
    if (Object.keys(fields).length) {
      fieldErrors.value = fields
    } else {
      globalError.value = authStore.error ?? 'An unexpected error occurred.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>
