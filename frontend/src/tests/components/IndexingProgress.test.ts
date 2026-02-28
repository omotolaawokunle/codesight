import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import IndexingProgress from '@/components/IndexingProgress.vue'

describe('IndexingProgress', () => {
  it('shows the progress percentage when progress > 0', () => {
    const wrapper = mount(IndexingProgress, {
      props: { progress: 65, indexedFiles: 65, totalFiles: 100 },
    })
    expect(wrapper.text()).toContain('65%')
  })

  it('does not show percentage text when progress is 0', () => {
    const wrapper = mount(IndexingProgress, {
      props: { progress: 0, indexedFiles: 0, totalFiles: 100 },
    })
    expect(wrapper.text()).not.toContain('%')
  })

  it('shows indexed file count and total', () => {
    const wrapper = mount(IndexingProgress, {
      props: { progress: 50, indexedFiles: 50, totalFiles: 100 },
    })
    expect(wrapper.text()).toContain('50')
    expect(wrapper.text()).toContain('100')
  })

  it('shows question mark for unknown total files', () => {
    const wrapper = mount(IndexingProgress, {
      props: { progress: 0, indexedFiles: null, totalFiles: null },
    })
    expect(wrapper.text()).toContain('?')
  })

  it('sets progress bar width based on progress prop', () => {
    const wrapper = mount(IndexingProgress, {
      props: { progress: 40, indexedFiles: 40, totalFiles: 100 },
    })
    const bar = wrapper.find('[style]')
    expect(bar.attributes('style')).toContain('40%')
  })

  it('uses minimum 4% width even at zero progress', () => {
    const wrapper = mount(IndexingProgress, {
      props: { progress: 0, indexedFiles: 0, totalFiles: 100 },
    })
    const bar = wrapper.find('[style]')
    expect(bar.attributes('style')).toContain('4%')
  })
})
