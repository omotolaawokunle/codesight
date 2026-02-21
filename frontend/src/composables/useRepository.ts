import { useRepositoryStore } from '@/stores/repository'
import { storeToRefs } from 'pinia'

export function useRepository() {
  const store = useRepositoryStore()
  const { repositories, currentRepository, isLoading, error } = storeToRefs(store)

  return {
    repositories,
    currentRepository,
    isLoading,
    error,
    fetchRepositories: store.fetchRepositories,
    fetchRepository: store.fetchRepository,
    createRepository: store.createRepository,
    deleteRepository: store.deleteRepository,
    reindexRepository: store.reindexRepository,
  }
}
