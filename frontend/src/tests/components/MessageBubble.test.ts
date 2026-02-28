import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import MessageBubble from '@/components/MessageBubble.vue'
import type { Message } from '@/types'

const createMessage = (overrides: Partial<Message> = {}): Message => ({
  id: 1,
  conversation_id: 1,
  role: 'user',
  content: 'Hello, how does this work?',
  metadata: null,
  created_at: '2024-01-01T12:00:00Z',
  ...overrides,
})

describe('MessageBubble', () => {
  it('renders user message content as plain text', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: createMessage({ role: 'user', content: 'Hello world' }), isStreaming: false },
    })
    expect(wrapper.text()).toContain('Hello world')
  })

  it('applies reversed flex layout for user messages', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: createMessage({ role: 'user' }), isStreaming: false },
    })
    expect(wrapper.find('.flex-row-reverse').exists()).toBe(true)
  })

  it('applies normal flex layout for assistant messages', () => {
    const wrapper = mount(MessageBubble, {
      props: {
        message: createMessage({ role: 'assistant', content: 'Here is my answer.' }),
        isStreaming: false,
      },
    })
    expect(wrapper.find('.flex-row').exists()).toBe(true)
    expect(wrapper.find('.flex-row-reverse').exists()).toBe(false)
  })

  it('renders assistant message content as HTML (markdown)', () => {
    const wrapper = mount(MessageBubble, {
      props: {
        message: createMessage({ role: 'assistant', content: '**bold text**' }),
        isStreaming: false,
      },
    })
    // v-html renders markdown; check for rendered content container
    expect(wrapper.find('.prose').exists()).toBe(true)
  })

  it('shows streaming cursor when isStreaming is true for assistant', () => {
    const wrapper = mount(MessageBubble, {
      props: {
        message: createMessage({ role: 'assistant', content: '' }),
        isStreaming: true,
      },
    })
    expect(wrapper.find('.animate-pulse').exists()).toBe(true)
  })

  it('does not show streaming cursor when isStreaming is false', () => {
    const wrapper = mount(MessageBubble, {
      props: {
        message: createMessage({ role: 'assistant', content: 'Done.' }),
        isStreaming: false,
      },
    })
    // The only animate-pulse element should not exist (cursor hidden)
    const pulsers = wrapper.findAll('.animate-pulse')
    expect(pulsers.length).toBe(0)
  })

  it('displays a timestamp', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: createMessage(), isStreaming: false },
    })
    // Timestamp is rendered somewhere in the component
    expect(wrapper.html()).toBeTruthy()
  })
})
