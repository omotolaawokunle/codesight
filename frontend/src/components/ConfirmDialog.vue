<template>
  <TransitionRoot appear :show="modelValue" as="template">
    <Dialog as="div" class="relative z-[60]" @close="cancel">
      <!-- Backdrop -->
      <TransitionChild
        as="template"
        enter="duration-200 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-150 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" />
      </TransitionChild>

      <!-- Panel -->
      <div class="fixed inset-0 flex items-center justify-center p-4">
        <TransitionChild
          as="template"
          enter="duration-200 ease-out"
          enter-from="opacity-0 scale-95"
          enter-to="opacity-100 scale-100"
          leave="duration-150 ease-in"
          leave-from="opacity-100 scale-100"
          leave-to="opacity-0 scale-95"
        >
          <DialogPanel class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 space-y-4">
            <!-- Icon + Title -->
            <div class="flex items-start gap-4">
              <div
                class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full"
                :class="iconBg"
              >
                <ExclamationTriangleIcon v-if="variant === 'danger'" class="h-5 w-5 text-red-400" />
                <InformationCircleIcon v-else class="h-5 w-5 text-accent-400" />
              </div>
              <div>
                <DialogTitle class="text-base font-semibold text-slate-100">
                  {{ title }}
                </DialogTitle>
                <p class="mt-1 text-sm text-slate-400">{{ description }}</p>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 justify-end">
              <button
                type="button"
                class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors cursor-pointer"
                @click="cancel"
              >
                {{ cancelLabel }}
              </button>
              <button
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-medium transition-colors cursor-pointer"
                :class="confirmBtnClass"
                @click="confirm"
              >
                {{ confirmLabel }}
              </button>
            </div>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionChild,
    TransitionRoot,
} from '@headlessui/vue';
import { ExclamationTriangleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  modelValue: boolean
  title: string
  description?: string
  confirmLabel?: string
  cancelLabel?: string
  /** 'danger' = red confirm button; 'info' = blue */
  variant?: 'danger' | 'info'
}>(), {
  description: '',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'danger',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

const iconBg = computed(() =>
  props.variant === 'danger' ? 'bg-red-950/50' : 'bg-accent-950/50'
)

const confirmBtnClass = computed(() =>
  props.variant === 'danger'
    ? 'bg-red-600 hover:bg-red-500 text-white'
    : 'bg-accent-600 hover:bg-accent-500 text-white'
)

function confirm() {
  emit('confirm')
  emit('update:modelValue', false)
}

function cancel() {
  emit('cancel')
  emit('update:modelValue', false)
}
</script>
