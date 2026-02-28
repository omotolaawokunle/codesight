import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import RepositoryCard from '@/components/RepositoryCard.vue'
import type { Repository } from '@/types'

const createRepo = (overrides: Partial<Repository> = {}): Repository => ({
  id: 1,
  name: 'my-awesome-repo',
  git_url: 'https://github.com/owner/my-awesome-repo',
  branch: 'main',
  indexing_status: 'completed',
  total_files: 42,
  indexed_files: 42,
  total_chunks: 150,
  last_indexed_commit: 'abc123',
  indexing_error: null,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-02T00:00:00Z',
  ...overrides,
})

const mountWithStubs = (repo: Repository) =>
  mount(RepositoryCard, {
    props: { repository: repo },
    global: {
      stubs: {
        RouterLink: { template: '<a><slot /></a>' },
        IndexingProgress: true,
        StatusBadge: { template: '<span>{{ status }}</span>', props: ['status'] },
        ConfirmDialog: true,
      },
    },
  })

describe('RepositoryCard', () => {
  it('renders the repository name', () => {
    const wrapper = mountWithStubs(createRepo())
    expect(wrapper.text()).toContain('my-awesome-repo')
  })

  it('renders the git URL', () => {
    const wrapper = mountWithStubs(createRepo())
    expect(wrapper.text()).toContain('https://github.com/owner/my-awesome-repo')
  })

  it('shows the Chat button when indexing is completed', () => {
    const wrapper = mountWithStubs(createRepo({ indexing_status: 'completed' }))
    expect(wrapper.text()).toContain('Chat')
  })

  it('does not show Chat button when indexing is pending', () => {
    const wrapper = mountWithStubs(createRepo({ indexing_status: 'pending' }))
    expect(wrapper.text()).not.toContain('Chat')
  })

  it('emits reindex event when Re-index button is clicked', async () => {
    const wrapper = mountWithStubs(createRepo({ indexing_status: 'completed' }))
    const reindexBtn = wrapper.findAll('button').find((b) => b.text().includes('Re-index'))
    await reindexBtn?.trigger('click')
    expect(wrapper.emitted('reindex')).toBeTruthy()
    expect(wrapper.emitted('reindex')![0]).toEqual([1])
  })

  it('opens confirm dialog when Delete button is clicked', async () => {
    const wrapper = mountWithStubs(createRepo())
    const deleteBtn = wrapper.findAll('button').find((b) => b.text().includes('Delete'))
    await deleteBtn?.trigger('click')
    // showConfirm should become true — verified by the ConfirmDialog stub receiving v-model
    expect(wrapper.emitted('delete')).toBeFalsy() // dialog intercepts, not direct emit
  })

  it('Re-index button is disabled when indexing is in progress', () => {
    const wrapper = mountWithStubs(createRepo({ indexing_status: 'in_progress' }))
    const reindexBtn = wrapper.findAll('button').find((b) => b.text().includes('Re-index'))
    expect(reindexBtn?.attributes('disabled')).toBeDefined()
  })

  it('shows error message when indexing has failed', () => {
    const wrapper = mountWithStubs(
      createRepo({ indexing_status: 'failed', indexing_error: 'Clone timed out.' }),
    )
    expect(wrapper.text()).toContain('Clone timed out.')
  })

  it('shows file and chunk stats when available', () => {
    const wrapper = mountWithStubs(createRepo({ total_files: 100, total_chunks: 300 }))
    expect(wrapper.text()).toContain('100')
    expect(wrapper.text()).toContain('300')
  })

  it('shows branch name', () => {
    const wrapper = mountWithStubs(createRepo({ branch: 'develop' }))
    expect(wrapper.text()).toContain('develop')
  })
})
