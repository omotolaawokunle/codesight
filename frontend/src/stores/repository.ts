import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Repository, CreateRepositoryPayload } from '@/types'
import api from '@/services/api'

export const useRepositoryStore = defineStore('repository', () => {
  const repositories = ref<Repository[]>([])
  const currentRepository = ref<Repository | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchRepositories() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.get('/repositories')
      repositories.value = data.data
    } catch (err: unknown) {
      error.value = 'Failed to load repositories'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function fetchRepository(id: number) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.get(`/repositories/${id}`)
      currentRepository.value = data
    } catch (err: unknown) {
      error.value = 'Failed to load repository'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function createRepository(payload: CreateRepositoryPayload) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.post('/repositories', payload)
      repositories.value.unshift(data)
      return data as Repository
    } catch (err: unknown) {
      error.value = 'Failed to create repository'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function deleteRepository(id: number) {
    await api.delete(`/repositories/${id}`)
    repositories.value = repositories.value.filter((r) => r.id !== id)
    if (currentRepository.value?.id === id) {
      currentRepository.value = null
    }
  }

  async function reindexRepository(id: number) {
    await api.post(`/repositories/${id}/reindex`)
    const repo = repositories.value.find((r) => r.id === id)
    if (repo) repo.indexing_status = 'pending'
  }

  return {
    repositories,
    currentRepository,
    isLoading,
    error,
    fetchRepositories,
    fetchRepository,
    createRepository,
    deleteRepository,
    reindexRepository,
  }
})
