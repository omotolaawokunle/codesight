<template>
  <Teleport to="body">
    <div class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 pointer-events-none">
      <TransitionGroup
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
      >
        <div
          v-for="toast in uiStore.toasts"
          :key="toast.id"
          class="pointer-events-auto flex items-start gap-3 rounded-xl px-4 py-3 shadow-xl text-sm font-medium min-w-64 max-w-sm"
          :class="toastClass(toast.type)"
        >
          <component :is="toastIcon(toast.type)" class="w-4 h-4 mt-0.5 shrink-0" />
          <span class="flex-1">{{ toast.message }}</span>
          <button
            class="shrink-0 opacity-60 hover:opacity-100 transition-opacity cursor-pointer"
            @click="uiStore.removeToast(toast.id)"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useUiStore, type ToastType } from '@/stores/ui'
import {
  CheckCircleIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

function toastClass(type: ToastType) {
  return {
    success: 'bg-slate-800 border border-slate-700 text-slate-100',
    error: 'bg-red-600 text-white',
    warning: 'bg-amber-600 text-white',
    info: 'bg-blue-600 text-white',
  }[type]
}

function toastIcon(type: ToastType) {
  return {
    success: CheckCircleIcon,
    error: ExclamationCircleIcon,
    warning: ExclamationTriangleIcon,
    info: InformationCircleIcon,
  }[type]
}
</script>
