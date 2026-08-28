import { computed, onBeforeUnmount, ref, shallowRef, watch, type Ref } from 'vue'

export type MicrophoneState =
  | 'idle'
  | 'requesting'
  | 'ready'
  | 'denied'
  | 'unsupported'
  | 'error'

export type RadioCue = 'channel' | 'start' | 'end'

interface RadioAudioOptions {
  radioEffect: Ref<boolean>
  soundEffects: Ref<boolean>
}

type AudioContextConstructor = typeof AudioContext

const EMPTY_BARS = Array.from({ length: 24 }, () => 0.04)
const UNSUPPORTED_MESSAGE =
  'Microphone capture needs a modern browser on HTTPS or localhost.'

export function useRadioAudio({ radioEffect, soundEffects }: RadioAudioOptions) {
  const canUseMicrophone =
    typeof window !== 'undefined' &&
    typeof navigator !== 'undefined' &&
    Boolean(navigator.mediaDevices?.getUserMedia) &&
    Boolean(
      window.AudioContext ||
        (window as typeof window & { webkitAudioContext?: AudioContextConstructor })
          .webkitAudioContext,
    )

  const microphoneState = ref<MicrophoneState>(canUseMicrophone ? 'idle' : 'unsupported')
  const microphoneError = ref(canUseMicrophone ? '' : UNSUPPORTED_MESSAGE)
  const isTransmitting = ref(false)
  const signalLevel = ref(0)
  const signalBars = ref([...EMPTY_BARS])
  const processedStream = shallowRef<MediaStream | null>(null)

  let inputContext: AudioContext | null = null
  let cueContext: AudioContext | null = null
  let inputStream: MediaStream | null = null
  let sourceNode: MediaStreamAudioSourceNode | null = null
  let highpassNode: BiquadFilterNode | null = null
  let lowpassNode: BiquadFilterNode | null = null
  let gainNode: GainNode | null = null
  let analyserNode: AnalyserNode | null = null
  let streamDestination: MediaStreamAudioDestinationNode | null = null
  let animationFrame = 0
  let frequencyData: Uint8Array<ArrayBuffer> | null = null
  let pendingRequest: Promise<boolean> | null = null
  let requestGeneration = 0

  const AudioContextClass = () =>
    window.AudioContext ||
    (window as typeof window & { webkitAudioContext?: AudioContextConstructor })
      .webkitAudioContext

  const microphoneLabel = computed(() => {
    switch (microphoneState.value) {
      case 'requesting':
        return 'Waiting for microphone permission'
      case 'ready':
        return 'Microphone armed'
      case 'denied':
        return 'Microphone access blocked'
      case 'unsupported':
        return 'Microphone unavailable'
      case 'error':
        return 'Microphone error'
      default:
        return 'Microphone not armed'
    }
  })

  function setTracksEnabled(enabled: boolean) {
    inputStream?.getAudioTracks().forEach((track) => {
      track.enabled = enabled
    })
  }

  function rebuildInputGraph() {
    if (!sourceNode || !gainNode || !analyserNode || !streamDestination) return

    sourceNode.disconnect()
    highpassNode?.disconnect()
    lowpassNode?.disconnect()
    gainNode.disconnect()

    if (radioEffect.value && highpassNode && lowpassNode) {
      sourceNode.connect(highpassNode)
      highpassNode.connect(lowpassNode)
      lowpassNode.connect(gainNode)
    } else {
      sourceNode.connect(gainNode)
    }

    gainNode.connect(analyserNode)
    gainNode.connect(streamDestination)
  }

  function updateSignal() {
    if (!analyserNode || !frequencyData || !isTransmitting.value) {
      animationFrame = 0
      return
    }

    const data = frequencyData
    analyserNode.getByteFrequencyData(data)

    const nextBars = signalBars.value.map((_, index) => {
      const sourceIndex = Math.min(
        data.length - 1,
        Math.floor((index / signalBars.value.length) * data.length),
      )
      const rawValue = data[sourceIndex] ?? 0
      return Math.max(0.055, Math.min(1, rawValue / 190))
    })

    let total = 0
    for (const value of data) {
      total += value
    }

    signalBars.value = nextBars
    signalLevel.value = Math.min(1, total / Math.max(1, data.length) / 105)
    animationFrame = window.requestAnimationFrame(updateSignal)
  }

  function startSignalLoop() {
    if (animationFrame) return
    animationFrame = window.requestAnimationFrame(updateSignal)
  }

  function stopSignalLoop() {
    if (animationFrame) window.cancelAnimationFrame(animationFrame)
    animationFrame = 0
    signalLevel.value = 0
    signalBars.value = [...EMPTY_BARS]
  }

  async function ensureMicrophone(): Promise<boolean> {
    if (microphoneState.value === 'ready' && inputStream) return true
    if (!canUseMicrophone) {
      microphoneState.value = 'unsupported'
      microphoneError.value = UNSUPPORTED_MESSAGE
      return false
    }
    if (pendingRequest) return pendingRequest

    const requestVersion = ++requestGeneration
    pendingRequest = (async () => {
      microphoneState.value = 'requesting'
      microphoneError.value = ''

      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: {
            autoGainControl: true,
            channelCount: 1,
            echoCancellation: true,
            noiseSuppression: true,
          },
          video: false,
        })

        if (requestVersion !== requestGeneration) {
          stream.getTracks().forEach((track) => track.stop())
          return false
        }

        const Context = AudioContextClass()
        if (!Context) throw new Error('Web Audio is not supported in this browser.')

        inputContext = new Context()
        inputStream = stream
        sourceNode = inputContext.createMediaStreamSource(stream)

        highpassNode = inputContext.createBiquadFilter()
        highpassNode.type = 'highpass'
        highpassNode.frequency.value = 400
        highpassNode.Q.value = 0.7

        lowpassNode = inputContext.createBiquadFilter()
        lowpassNode.type = 'lowpass'
        lowpassNode.frequency.value = 2500
        lowpassNode.Q.value = 0.8

        gainNode = inputContext.createGain()
        gainNode.gain.value = 1

        analyserNode = inputContext.createAnalyser()
        analyserNode.fftSize = 128
        analyserNode.minDecibels = -86
        analyserNode.maxDecibels = -18
        analyserNode.smoothingTimeConstant = 0.72
        frequencyData = new Uint8Array(analyserNode.frequencyBinCount)

        streamDestination = inputContext.createMediaStreamDestination()
        processedStream.value = streamDestination.stream

        rebuildInputGraph()
        setTracksEnabled(false)

        stream.getAudioTracks().forEach((track) => {
          track.addEventListener(
            'ended',
            () => {
              if (inputStream !== stream) return
              releaseMicrophone()
              microphoneState.value = 'error'
              microphoneError.value = 'The microphone was disconnected.'
            },
            { once: true },
          )
        })

        microphoneState.value = 'ready'
        return true
      } catch (error) {
        const errorName = error instanceof DOMException ? error.name : ''
        releaseMicrophone()

        if (errorName === 'NotAllowedError' || errorName === 'SecurityError') {
          microphoneState.value = 'denied'
          microphoneError.value =
            'Allow microphone access in your browser settings, then try again.'
        } else if (errorName === 'NotFoundError' || errorName === 'DevicesNotFoundError') {
          microphoneState.value = 'error'
          microphoneError.value = 'No microphone was found on this device.'
        } else {
          microphoneState.value = 'error'
          microphoneError.value =
            error instanceof Error ? error.message : 'The microphone could not be started.'
        }

        return false
      } finally {
        pendingRequest = null
      }
    })()

    return pendingRequest
  }

  async function startTransmission(canStart: () => boolean = () => true) {
    if (microphoneState.value !== 'ready' || !inputStream) return false

    const context = inputContext
    const stream = inputStream

    try {
      if (!context) throw new Error('The audio engine is unavailable.')
      if (context.state === 'suspended') await context.resume()
      if (context.state !== 'running') throw new Error('The audio engine did not start.')
    } catch {
      releaseMicrophone()
      microphoneState.value = 'error'
      microphoneError.value = 'The browser could not start the audio engine. Try again.'
      return false
    }

    if (
      inputContext !== context ||
      inputStream !== stream ||
      !canStart()
    ) {
      return false
    }

    setTracksEnabled(true)
    isTransmitting.value = true
    startSignalLoop()
    return true
  }

  function stopTransmission() {
    setTracksEnabled(false)
    isTransmitting.value = false
    stopSignalLoop()
  }

  function releaseMicrophone() {
    requestGeneration += 1
    stopTransmission()
    inputStream?.getTracks().forEach((track) => track.stop())
    inputStream = null
    processedStream.value = null

    sourceNode?.disconnect()
    highpassNode?.disconnect()
    lowpassNode?.disconnect()
    gainNode?.disconnect()
    analyserNode?.disconnect()

    sourceNode = null
    highpassNode = null
    lowpassNode = null
    gainNode = null
    analyserNode = null
    streamDestination = null
    frequencyData = null

    if (inputContext && inputContext.state !== 'closed') {
      void inputContext.close()
    }
    inputContext = null

    microphoneState.value = canUseMicrophone ? 'idle' : 'unsupported'
    microphoneError.value = canUseMicrophone ? '' : UNSUPPORTED_MESSAGE
  }

  function getCueContext() {
    const Context = AudioContextClass()
    if (!Context) return null
    cueContext ??= new Context()
    return cueContext
  }

  function playCue(cue: RadioCue) {
    if (!soundEffects.value) return

    const context = getCueContext()
    if (!context) return

    void (async () => {
      try {
        if (context.state === 'suspended') await context.resume()
        const now = context.currentTime

        if (cue === 'end') {
          const duration = 0.11
          const frameCount = Math.ceil(context.sampleRate * duration)
          const buffer = context.createBuffer(1, frameCount, context.sampleRate)
          const channel = buffer.getChannelData(0)

          for (let index = 0; index < frameCount; index += 1) {
            channel[index] = (Math.random() * 2 - 1) * (1 - index / frameCount)
          }

          const noise = context.createBufferSource()
          const bandpass = context.createBiquadFilter()
          const volume = context.createGain()
          noise.buffer = buffer
          bandpass.type = 'bandpass'
          bandpass.frequency.value = 1450
          bandpass.Q.value = 0.7
          volume.gain.setValueAtTime(0.0001, now)
          volume.gain.exponentialRampToValueAtTime(0.075, now + 0.008)
          volume.gain.exponentialRampToValueAtTime(0.0001, now + duration)
          noise.connect(bandpass).connect(volume).connect(context.destination)
          noise.start(now)
          noise.stop(now + duration)
          return
        }

        const oscillator = context.createOscillator()
        const volume = context.createGain()
        oscillator.type = 'square'
        oscillator.frequency.setValueAtTime(cue === 'start' ? 660 : 520, now)

        if (cue === 'channel') {
          oscillator.frequency.setValueAtTime(520, now)
          oscillator.frequency.setValueAtTime(760, now + 0.045)
        }

        const duration = cue === 'start' ? 0.075 : 0.09
        volume.gain.setValueAtTime(0.0001, now)
        volume.gain.exponentialRampToValueAtTime(0.045, now + 0.008)
        volume.gain.exponentialRampToValueAtTime(0.0001, now + duration)
        oscillator.connect(volume).connect(context.destination)
        oscillator.start(now)
        oscillator.stop(now + duration)
      } catch {
        // Browsers may suppress cues until the first direct user gesture.
      }
    })()
  }

  watch(radioEffect, rebuildInputGraph)

  onBeforeUnmount(() => {
    releaseMicrophone()
    if (cueContext && cueContext.state !== 'closed') void cueContext.close()
  })

  return {
    microphoneState,
    microphoneError,
    microphoneLabel,
    isTransmitting,
    signalLevel,
    signalBars,
    processedStream,
    ensureMicrophone,
    startTransmission,
    stopTransmission,
    releaseMicrophone,
    playCue,
  }
}
