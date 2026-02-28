import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import StatusBadge from '@/components/StatusBadge.vue'
import type { IndexingStatus } from '@/types'

describe('StatusBadge', () => {
  it('renders "Pending" for pending status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'pending' as IndexingStatus } })
    expect(wrapper.text()).toContain('Pending')
  })

  it('renders "Indexing…" for in_progress status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'in_progress' as IndexingStatus } })
    expect(wrapper.text()).toContain('Indexing')
  })

  it('renders "Indexed" for completed status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'completed' as IndexingStatus } })
    expect(wrapper.text()).toContain('Indexed')
  })

  it('renders "Failed" for failed status', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'failed' as IndexingStatus } })
    expect(wrapper.text()).toContain('Failed')
  })

  it('shows animated dot indicator for in_progress', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'in_progress' as IndexingStatus } })
    // The pinging dot element only renders for in_progress
    expect(wrapper.find('span.animate-ping').exists()).toBe(true)
  })

  it('does not show animated dot for completed', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'completed' as IndexingStatus } })
    expect(wrapper.find('span.animate-ping').exists()).toBe(false)
  })
})
