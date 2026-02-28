import api from '@/services/api'
import type { CreateRepositoryPayload, Repository, RepositoryStatus } from '@/types'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useUiStore } from '@/stores/ui'

export const useRepositoryStore = defineStore('repository', () => {
  const repositories = ref<Repository[]>([])
  const currentRepository = ref<Repository | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const ui = useUiStore()

  // ----- Fetch all repositories -----------------------------------------------

  async function fetchRepositories() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.get('/repositories')
      // Handle both paginated (data.data) and plain array responses.
      repositories.value = data.data ?? data
    } catch (err: unknown) {
      error.value = 'Failed to load repositories.'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // ----- Fetch single repository -----------------------------------------------

  async function fetchRepository(id: number) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.get(`/repositories/${id}`)
      currentRepository.value = data.data ?? data
    } catch (err: unknown) {
      error.value = 'Failed to load repository.'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // ----- Create repository -----------------------------------------------------

  async function createRepository(payload: CreateRepositoryPayload) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.post('/repositories', payload)
      const created: Repository = data.data ?? data
      repositories.value.unshift(created)
      ui.addToast(`"${created.name}" added and queued for indexing.`, 'success')
      return created
    } catch (err: unknown) {
      error.value = 'Failed to create repository.'
      ui.addToast('Failed to create repository.', 'error')
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // ----- Delete repository -----------------------------------------------------

  async function deleteRepository(id: number) {
    try {
      await api.delete(`/repositories/${id}`)
      repositories.value = repositories.value.filter((r) => r.id !== id)
      if (currentRepository.value?.id === id) {
        currentRepository.value = null
      }
      ui.addToast('Repository deleted.', 'success')
    } catch (err: unknown) {
      ui.addToast('Failed to delete repository.', 'error')
      throw err
    }
  }

  // ----- Re-index repository ---------------------------------------------------

  async function reindexRepository(id: number) {
    try {
      await api.post(`/repositories/${id}/reindex`)
      updateRepositoryInList(id, { indexing_status: 'pending', indexed_files: 0, total_chunks: 0 })
      ui.addToast('Re-indexing queued.', 'success')
    } catch (err: unknown) {
      ui.addToast('Failed to start re-indexing.', 'error')
      throw err
    }
  }

  // ----- Poll indexing status --------------------------------------------------

  /**
   * Fetches the current indexing status for a repository and merges the
   * progress fields back into the in-memory repository list.
   *
   * Called repeatedly by usePolling while a repository is in_progress.
   */
  async function fetchStatus(id: number): Promise<RepositoryStatus> {
    const { data } = await api.get<RepositoryStatus>(`/repositories/${id}/status`)
    const status = data

    updateRepositoryInList(id, {
      indexing_status: status.status,
      indexed_files: status.indexed_files,
      total_files: status.total_files,
      total_chunks: status.total_chunks,
      indexing_started_at: status.started_at ?? undefined,
      indexing_completed_at: status.completed_at ?? undefined,
    } as Partial<Repository>)

    // Also update currentRepository if it's the one being viewed.
    if (currentRepository.value?.id === id) {
      Object.assign(currentRepository.value, {
        indexing_status: status.status,
        indexed_files: status.indexed_files,
        total_files: status.total_files,
        total_chunks: status.total_chunks,
      })
    }

    return status
  }

  // ----- Internal helper -------------------------------------------------------

  function updateRepositoryInList(id: number, patch: Partial<Repository>) {
    const repo = repositories.value.find((r) => r.id === id)
    if (repo) Object.assign(repo, patch)
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
    fetchStatus,
  }
})
