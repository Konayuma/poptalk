import { defineComponent, h, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ChannelTuner from '../ChannelTuner.vue'
import PushToTalk from '../PushToTalk.vue'
import SignalMeter from '../SignalMeter.vue'
import { useRadioAudio } from '../../composables/useRadioAudio'

describe('ChannelTuner', () => {
  it('renders a padded channel and emits arrow changes', async () => {
    const wrapper = mount(ChannelTuner, { props: { modelValue: 7 } })

    expect(wrapper.get('.channel-display strong').text()).toBe('07')
    expect(wrapper.get('.channel-display__frequency').text()).toBe('Virtual public band')

    await wrapper.get('button[aria-label="Next channel"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([[8]])

    wrapper.unmount()
  })

  it('wraps both ends of the 1–99 channel range', async () => {
    const upper = mount(ChannelTuner, { props: { modelValue: 99 } })
    await upper.get('button[aria-label="Next channel"]').trigger('click')
    expect(upper.emitted('update:modelValue')).toEqual([[1]])
    upper.unmount()

    const lower = mount(ChannelTuner, { props: { modelValue: 1 } })
    await lower.get('button[aria-label="Previous channel"]').trigger('click')
    expect(lower.emitted('update:modelValue')).toEqual([[99]])
    lower.unmount()
  })

  it('only captures wheel gestures over the channel display', async () => {
    const wrapper = mount(ChannelTuner, { props: { modelValue: 12 } })

    await wrapper.get('.tuner__console').trigger('wheel', { deltaY: 100 })
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    await wrapper.get('.channel-display').trigger('wheel', { deltaY: 100 })
    expect(wrapper.emitted('update:modelValue')).toEqual([[13]])

    wrapper.unmount()
  })

  it('does not change channels while disabled', async () => {
    const wrapper = mount(ChannelTuner, {
      props: { modelValue: 7, disabled: true },
    })

    await wrapper.get('button[aria-label="Next channel"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    wrapper.unmount()
  })
})

describe('PushToTalk', () => {
  it('emits matched start and end events for keyboard holds', async () => {
    const wrapper = mount(PushToTalk, {
      props: {
        held: false,
        transmitting: false,
        pending: false,
      },
    })
    const button = wrapper.get('button')

    await button.trigger('keydown', { key: ' ', code: 'Space', repeat: false })
    await button.trigger('keyup', { key: ' ', code: 'Space' })

    expect(wrapper.emitted('pressStart')).toHaveLength(1)
    expect(wrapper.emitted('pressEnd')).toHaveLength(1)

    wrapper.unmount()
  })

  it('ignores repeated keyboard events', async () => {
    const wrapper = mount(PushToTalk, {
      props: {
        held: false,
        transmitting: false,
        pending: false,
      },
    })

    await wrapper.get('button').trigger('keydown', {
      key: ' ',
      code: 'Space',
      repeat: true,
    })

    expect(wrapper.emitted('pressStart')).toBeUndefined()
    wrapper.unmount()
  })
})

describe('SignalMeter', () => {
  it('quantizes its accessible value while preserving the visual reading', () => {
    const wrapper = mount(SignalMeter, {
      props: {
        bars: Array.from({ length: 24 }, () => 0.47),
        level: 0.47,
        active: true,
      },
    })

    const meter = wrapper.get('[role="meter"]')
    expect(meter.attributes('aria-valuenow')).toBe('50')
    expect(wrapper.get('.signal-meter__readout').text()).toContain('47%')

    wrapper.unmount()
  })
})

describe('useRadioAudio', () => {
  it('explains unsupported microphone environments without requiring a click', () => {
    const Harness = defineComponent({
      setup() {
        const radioEffect = ref(true)
        const soundEffects = ref(false)
        const radio = useRadioAudio({ radioEffect, soundEffects })

        return () =>
          h('output', {
            'data-state': radio.microphoneState.value,
            'data-error': radio.microphoneError.value,
          })
      },
    })

    const wrapper = mount(Harness)
    expect(wrapper.get('output').attributes('data-state')).toBe('unsupported')
    expect(wrapper.get('output').attributes('data-error')).toContain('HTTPS or localhost')

    wrapper.unmount()
  })
})
