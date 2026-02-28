<template>
  <header class="bg-slate-950 border-b border-slate-800 h-14 flex items-center px-4 sm:px-6 lg:px-8 shrink-0">
    <div class="flex items-center justify-between w-full max-w-7xl mx-auto">
      <!-- Logo -->
      <RouterLink to="/" class="flex items-center gap-2 group">
        <span class="text-lg font-mono font-bold text-green-400 tracking-tight group-hover:text-green-300 transition-colors">
          codesight
        </span>
      </RouterLink>

      <!-- Nav + user menu -->
      <div class="flex items-center gap-1">
        <RouterLink
          to="/"
          class="hidden sm:flex items-center gap-1.5 text-sm font-medium text-slate-400 hover:text-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-800/60 transition-colors"
          active-class="text-slate-100 bg-slate-800/60"
        >
          Dashboard
        </RouterLink>

        <!-- User menu -->
        <Menu v-if="user" as="div" class="relative ml-2">
          <MenuButton
            class="flex items-center gap-2 text-sm text-slate-400 hover:text-slate-200 px-2 py-1.5 rounded-lg hover:bg-slate-800/60 transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500"
          >
            <span class="w-7 h-7 rounded-full bg-slate-700 border border-slate-600 flex items-center justify-center text-xs font-mono font-bold text-green-400 shrink-0">
              {{ initials }}
            </span>
            <span class="hidden sm:block max-w-28 truncate">{{ user.name }}</span>
            <svg class="w-3.5 h-3.5 shrink-0 ui-open:rotate-180 transition-transform duration-150" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </MenuButton>

          <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-1"
          >
            <MenuItems
              class="absolute right-0 top-full mt-1.5 w-52 bg-slate-900 border border-slate-700 rounded-xl shadow-xl z-50 py-1 origin-top-right focus:outline-none"
            >
              <!-- User info (not a menu item — just a header) -->
              <div class="px-3 py-2.5 border-b border-slate-800">
                <p class="text-xs font-medium text-slate-200 truncate">{{ user.name }}</p>
                <p class="text-xs text-slate-500 truncate mt-0.5">{{ user.email }}</p>
              </div>

              <!-- Sign out -->
              <MenuItem v-slot="{ active }">
                <button
                  class="w-full flex items-center gap-2 px-3 py-2.5 text-sm transition-colors cursor-pointer"
                  :class="active ? 'text-red-400 bg-red-950/30' : 'text-slate-400'"
                  @click="handleLogout"
                >
                  <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                  </svg>
                  Sign out
                </button>
              </MenuItem>
            </MenuItems>
          </transition>
        </Menu>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { useAuthStore } from '@/stores/auth'
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const user = computed(() => authStore.user)

const initials = computed(() => {
  if (!user.value) return ''
  return user.value.name
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('')
})

async function handleLogout() {
  await authStore.logout()
  await router.push('/login')
}
</script>
