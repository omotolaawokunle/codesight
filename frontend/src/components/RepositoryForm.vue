<template>
  <TransitionRoot appear :show="modelValue" as="template">
    <Dialog as="div" class="relative z-50" @close="close">
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
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" />
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
          <DialogPanel class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 space-y-5">
            <!-- Header -->
            <div class="flex items-center justify-between">
              <DialogTitle class="text-lg font-semibold text-gray-900">
                Add Repository
              </DialogTitle>
              <button
                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                aria-label="Close"
                @click="close"
              >
                <XMarkIcon class="h-5 w-5" />
              </button>
            </div>

            <!-- Form -->
            <form class="space-y-4" @submit.prevent="submit">
              <!-- Repository Name -->
              <div>
                <label for="repo-name" class="block text-sm font-medium text-gray-700 mb-1">
                  Repository Name <span class="text-red-500">*</span>
                </label>
                <input
                  id="repo-name"
                  v-model="form.name"
                  type="text"
                  autocomplete="off"
                  placeholder="My Awesome Project"
                  class="w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="errors.name ? 'border-red-400' : 'border-gray-300'"
                />
                <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
              </div>

              <!-- Git URL -->
              <div>
                <label for="repo-url" class="block text-sm font-medium text-gray-700 mb-1">
                  Git URL <span class="text-red-500">*</span>
                </label>
                <input
                  id="repo-url"
                  v-model="form.git_url"
                  type="url"
                  autocomplete="off"
                  placeholder="https://github.com/owner/repo"
                  class="w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                  :class="errors.git_url ? 'border-red-400' : 'border-gray-300'"
                />
                <p v-if="errors.git_url" class="mt-1 text-xs text-red-500">{{ errors.git_url }}</p>
                <p class="mt-1 text-xs text-gray-400">Supports GitHub, GitLab, and Bitbucket HTTPS URLs.</p>
              </div>

              <!-- Branch -->
              <div>
                <label for="repo-branch" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                <input
                  id="repo-branch"
                  v-model="form.branch"
                  type="text"
                  autocomplete="off"
                  placeholder="main"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Git Token -->
              <div>
                <label for="repo-token" class="block text-sm font-medium text-gray-700 mb-1">
                  Personal Access Token
                  <span class="font-normal text-gray-400">(for private repos)</span>
                </label>
                <input
                  id="repo-token"
                  v-model="form.git_token"
                  type="password"
                  autocomplete="new-password"
                  placeholder="ghp_xxxxxxxxxxxxxxxx"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                />
                <p class="mt-1 text-xs text-gray-400">
                  Stored encrypted. Required for private repositories.
                </p>
              </div>

              <!-- Server-level error -->
              <p v-if="serverError" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">
                {{ serverError }}
              </p>

              <!-- Actions -->
              <div class="flex gap-3 pt-1">
                <button
                  type="button"
                  class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                  @click="close"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="isSubmitting"
                  class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                >
                  {{ isSubmitting ? 'Adding…' : 'Add Repository' }}
                </button>
              </div>
            </form>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { useRepositoryStore } from '@/stores/repository'
import type { CreateRepositoryPayload, Repository } from '@/types'
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { ref, watch } from 'vue'

const props = defineProps<{
  modelValue: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'created', repository: Repository): void
}>()

const store = useRepositoryStore()

const form = ref({ name: '', git_url: '', branch: 'main', git_token: '' })
const errors       = ref<Record<string, string>>({})
const serverError  = ref('')
const isSubmitting = ref(false)

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value        = { name: '', git_url: '', branch: 'main', git_token: '' }
    errors.value      = {}
    serverError.value = ''
    isSubmitting.value = false
  }
})

function close() {
  emit('update:modelValue', false)
}

function validate(): boolean {
  errors.value = {}
  if (!form.value.name.trim()) {
    errors.value.name = 'Repository name is required.'
  }
  if (!form.value.git_url.trim()) {
    errors.value.git_url = 'Git URL is required.'
  } else if (!/^https:\/\/(github\.com|gitlab\.com|bitbucket\.org)\/.+/i.test(form.value.git_url)) {
    errors.value.git_url = 'Only GitHub, GitLab, and Bitbucket HTTPS URLs are supported.'
  }
  return Object.keys(errors.value).length === 0
}

async function submit() {
  if (!validate()) return

  isSubmitting.value = true
  serverError.value  = ''

  try {
    const payload: CreateRepositoryPayload = {
      name:    form.value.name.trim(),
      git_url: form.value.git_url.trim(),
      branch:  form.value.branch.trim() || 'main',
    }
    if (form.value.git_token.trim()) payload.git_token = form.value.git_token.trim()

    const repository = await store.createRepository(payload)
    emit('created', repository)
    close()
  } catch (err: unknown) {
    const response = (err as { response?: { status: number; data: { errors?: Record<string, string[]>; message?: string } } })?.response
    if (response?.status === 422 && response.data.errors) {
      const fieldErrors: Record<string, string> = {}
      for (const [field, messages] of Object.entries(response.data.errors)) {
        fieldErrors[field] = Array.isArray(messages) ? messages[0] : String(messages)
      }
      errors.value = fieldErrors
    } else {
      serverError.value = response?.data?.message ?? 'Something went wrong. Please try again.'
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>
