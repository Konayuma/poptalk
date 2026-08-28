<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import ChannelTuner from './components/ChannelTuner.vue'
import PushToTalk from './components/PushToTalk.vue'
import SignalMeter from './components/SignalMeter.vue'
import { useRadioAudio, type MicrophoneState } from './composables/useRadioAudio'
import { useRadioBackend } from './composables/useRadioBackend'

type ActivityKind = 'system' | 'channel' | 'transmission' | 'warning'

interface ActivityItem {
  id: number
  kind: ActivityKind
  title: string
  detail: string
  time: string
  datetime: string
}

const STORAGE_KEYS = {
  channel: 'poptalk.channel',
  callsign: 'poptalk.callsign',
  radioEffect: 'poptalk.radio-effect',
  soundEffects: 'poptalk.sound-effects',
} as const

function readStoredString(key: string, fallback: string) {
  try {
    return window.localStorage.getItem(key) ?? fallback
  } catch {
    return fallback
  }
}

function readStoredBoolean(key: string, fallback: boolean) {
  return readStoredString(key, String(fallback)) === 'true'
}

function readStoredChannel() {
  const stored = Number(readStoredString(STORAGE_KEYS.channel, '7'))
  return Number.isInteger(stored) && stored >= 1 && stored <= 99 ? stored : 7
}

const channel = ref(readStoredChannel())
const callsign = ref(readStoredString(STORAGE_KEYS.callsign, 'ROOKIE-7'))
const radioEffect = ref(readStoredBoolean(STORAGE_KEYS.radioEffect, true))
const soundEffects = ref(readStoredBoolean(STORAGE_KEYS.soundEffects, true))
const pressHeld = ref(false)
const settingsPanel = ref<HTMLElement | null>(null)
const transmitSeconds = ref(0)

const {
  microphoneState,
  microphoneError,
  microphoneLabel,
  isTransmitting,
  signalLevel,
  signalBars,
  ensureMicrophone,
  startTransmission,
  stopTransmission,
  releaseMicrophone,
  playCue,
} = useRadioAudio({ radioEffect, soundEffects })

const {
  connectionState,
  connectionError,
  isSyncingIdentity,
  session: backendSession,
  remotePeerCount,
  activeSpeaker,
  isChannelBusy,
  connect: connectBackend,
  updateIdentity,
  claimFloor,
  releaseFloor,
} = useRadioBackend()

let activityId = 1
let pressSequence = 0
let globalSpaceHeld = false
let transmissionTimer = 0
let identitySyncTimer = 0
const initialActivityDate = new Date()

const activity = ref<ActivityItem[]>([
  {
    id: activityId,
    kind: 'system',
    title: 'Receiver ready',
    detail: 'Choose a channel and hold PTT for a radio check.',
    time: 'NOW',
    datetime: initialActivityDate.toISOString(),
  },
])

const formattedChannel = computed(() => String(channel.value).padStart(2, '0'))
const displayCallsign = computed(() => callsign.value.trim() || 'ROOKIE-7')
const isMicrophonePending = computed(() => microphoneState.value === 'requesting')
const isPttPending = computed(() => pressHeld.value && !isTransmitting.value)
const isBackendConnected = computed(() => connectionState.value === 'connected')
const isBackendConnecting = computed(
  () => connectionState.value === 'idle' || connectionState.value === 'connecting',
)
const isPttDisabled = computed(
  () =>
    microphoneState.value === 'unsupported' ||
    !isBackendConnected.value ||
    isSyncingIdentity.value ||
    isChannelBusy.value,
)
const relayLabel = computed(() => {
  if (isBackendConnected.value) return 'Base linked'
  if (isBackendConnecting.value) return 'Calling base'
  return 'Base offline'
})
const speakerTitle = computed(() => {
  if (isChannelBusy.value) return `${activeSpeaker.value?.callsign ?? 'Caller'} live`
  return 'Open line'
})
const speakerDetail = computed(() => {
  const count = remotePeerCount.value
  return `${count} remote ${count === 1 ? 'peer' : 'peers'}`
})
const relayTone = computed(() => {
  if (isBackendConnected.value) return 'ready'
  if (isBackendConnecting.value) return 'idle'
  return 'warning'
})

const statusHeadline = computed(() => {
  if (isTransmitting.value) return 'ON THE AIR!'
  if (isPttPending.value) return 'OPENING LINE...'
  if (isSyncingIdentity.value) return 'TUNING BASE...'
  if (isBackendConnecting.value) return 'CALLING BASE...'
  if (!isBackendConnected.value) return 'BASE OFFLINE!'
  if (isChannelBusy.value) return `${activeSpeaker.value?.callsign ?? 'SOMEONE'} TALKING!`
  if (isMicrophonePending.value) return 'TUNING IN...'
  if (microphoneState.value === 'denied') return 'MIC BLOCKED!'
  if (microphoneState.value === 'error') return 'CHECK YOUR MIC!'
  if (microphoneState.value === 'unsupported') return 'NO SIGNAL!'
  if (microphoneState.value === 'ready') return 'READY TO POP!'
  return 'PRESS TO TALK!'
})

const statusKicker = computed(() => {
  if (isTransmitting.value) return `${displayCallsign.value} · CH ${formattedChannel.value}`
  if (isPttPending.value) return 'Requesting the PTT floor'
  if (isSyncingIdentity.value) return `Syncing channel ${formattedChannel.value}`
  if (isBackendConnected.value) return `Linked as ${backendSession.value?.callsign ?? displayCallsign.value}`
  if (isMicrophonePending.value) return 'Permission check'
  if (microphoneState.value === 'ready') return 'Microphone armed'
  return 'Radio control link'
})

const stationStatus = computed(() => {
  if (isTransmitting.value) return 'Transmitting'
  if (isPttPending.value) return 'Opening line'
  if (isSyncingIdentity.value) return 'Syncing radio'
  if (isBackendConnecting.value) return 'Connecting'
  if (!isBackendConnected.value) return 'Backend offline'
  if (isChannelBusy.value) return `${activeSpeaker.value?.callsign ?? 'Channel'} talking`
  if (isMicrophonePending.value) return 'Arming microphone'
  if (microphoneState.value === 'denied') return 'Permission blocked'
  if (microphoneState.value === 'error') return 'Needs attention'
  if (microphoneState.value === 'unsupported') return 'Unsupported browser'
  if (microphoneState.value === 'ready') return 'Ready'
  return 'Standby'
})

const stationTone = computed(() => {
  if (isTransmitting.value) return 'live'
  if (!isBackendConnected.value || isChannelBusy.value) return 'warning'
  if (
    microphoneState.value === 'denied' ||
    microphoneState.value === 'error' ||
    microphoneState.value === 'unsupported'
  ) {
    return 'warning'
  }
  if (microphoneState.value === 'ready') return 'ready'
  return 'idle'
})

const durationLabel = computed(() => {
  const minutes = Math.floor(transmitSeconds.value / 60)
  const seconds = transmitSeconds.value % 60
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
})

function currentTime(date: Date) {
  return new Intl.DateTimeFormat(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function addActivity(kind: ActivityKind, title: string, detail: string) {
  const date = new Date()
  activityId += 1
  activity.value.unshift({
    id: activityId,
    kind,
    title,
    detail,
    time: currentTime(date),
    datetime: date.toISOString(),
  })
  activity.value = activity.value.slice(0, 5)
}

function startTransmissionClock() {
  window.clearInterval(transmissionTimer)
  transmitSeconds.value = 0
  transmissionTimer = window.setInterval(() => {
    transmitSeconds.value += 1
  }, 1000)
}

function stopTransmissionClock() {
  window.clearInterval(transmissionTimer)
  transmissionTimer = 0
  return durationLabel.value
}

async function beginPress() {
  if (pressHeld.value || isPttDisabled.value) return

  pressHeld.value = true
  const sequence = ++pressSequence
  const microphoneReady = await ensureMicrophone()

  if (!pressHeld.value || sequence !== pressSequence) return
  if (!microphoneReady) {
    pressHeld.value = false
    return
  }

  const floorClaimed = await claimFloor(channel.value)
  if (!pressHeld.value || sequence !== pressSequence) {
    if (floorClaimed) await releaseFloor()
    return
  }
  if (!floorClaimed) {
    pressHeld.value = false
    addActivity(
      'warning',
      'Transmission blocked',
      connectionError.value || 'The selected channel is currently unavailable.',
    )
    return
  }

  const transmissionStarted = await startTransmission(
    () => pressHeld.value && sequence === pressSequence,
  )
  if (!transmissionStarted) {
    await releaseFloor()
    if (sequence === pressSequence) pressHeld.value = false
    return
  }

  startTransmissionClock()
  playCue('start')
  addActivity(
    'transmission',
    `${displayCallsign.value} keyed up`,
    `PTT floor secured on channel ${formattedChannel.value}; audio remains local.`,
  )
}

function endPress() {
  if (!pressHeld.value && !isTransmitting.value) return

  pressHeld.value = false
  pressSequence += 1

  if (!isTransmitting.value) return

  const duration = stopTransmissionClock()
  stopTransmission()
  void releaseFloor()
  playCue('end')
  addActivity(
    'transmission',
    `${displayCallsign.value} signed off`,
    `${duration} transmission · channel ${formattedChannel.value}.`,
  )
}

async function armMicrophone() {
  const wasReady = microphoneState.value === 'ready'
  const ready = await ensureMicrophone()
  if (ready && !wasReady) {
    playCue('channel')
    addActivity('system', 'Microphone armed', 'Audio stays muted until you hold PTT.')
  }
}

function disarmMicrophone() {
  endPress()
  releaseMicrophone()
  addActivity('system', 'Microphone released', 'This tab is no longer holding the mic.')
}

function retryBackend() {
  void connectBackend(displayCallsign.value, channel.value)
}

function updateCallsign(event: Event) {
  const target = event.target as HTMLInputElement
  callsign.value = target.value
    .toUpperCase()
    .replace(/[^A-Z0-9-]/g, '')
    .slice(0, 14)
}

function focusSettings() {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  settingsPanel.value?.scrollIntoView({
    behavior: reduceMotion ? 'auto' : 'smooth',
    block: 'center',
  })
  window.setTimeout(
    () => settingsPanel.value?.querySelector<HTMLInputElement>('input')?.focus(),
    reduceMotion ? 0 : 350,
  )
}

function isInteractiveTarget(target: EventTarget | null) {
  if (!(target instanceof HTMLElement)) return false
  return (
    target.isContentEditable ||
    ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName)
  )
}

function handleGlobalKeyDown(event: KeyboardEvent) {
  if (event.code !== 'Space' || event.repeat || isInteractiveTarget(event.target)) return
  event.preventDefault()
  globalSpaceHeld = true
  void beginPress()
}

function handleGlobalKeyUp(event: KeyboardEvent) {
  if (event.code !== 'Space' || !globalSpaceHeld) return
  event.preventDefault()
  globalSpaceHeld = false
  endPress()
}

function handleVisibilityChange() {
  if (document.hidden) endPress()
}

watch(channel, (nextChannel) => {
  endPress()
  playCue('channel')
  addActivity(
    'channel',
    `Tuned to channel ${String(nextChannel).padStart(2, '0')}`,
    'Control API syncing; audio remains local.',
  )
  void updateIdentity(displayCallsign.value, nextChannel)
})

watch(callsign, () => {
  window.clearTimeout(identitySyncTimer)
  identitySyncTimer = window.setTimeout(() => {
    void updateIdentity(displayCallsign.value, channel.value)
  }, 300)
})

watch(microphoneState, (nextState: MicrophoneState, previousState: MicrophoneState) => {
  if (nextState === previousState) return
  if (nextState === 'denied') {
    addActivity('warning', 'Microphone blocked', 'Update the site permission, then try again.')
  } else if (nextState === 'error') {
    addActivity('warning', 'Microphone error', microphoneError.value)
  }
})

watch(connectionState, (nextState, previousState) => {
  if (nextState === 'connected' && previousState !== 'connected') {
    addActivity('system', 'Base linked', 'Presence and PTT controls are synchronized.')
  } else if (nextState === 'disconnected' && previousState === 'connected') {
    endPress()
    addActivity('warning', 'Base connection lost', connectionError.value)
  }
})

watch(isChannelBusy, (busy) => {
  if (busy && isTransmitting.value) endPress()
})

watch([channel, callsign, radioEffect, soundEffects], () => {
  try {
    window.localStorage.setItem(STORAGE_KEYS.channel, String(channel.value))
    window.localStorage.setItem(STORAGE_KEYS.callsign, displayCallsign.value)
    window.localStorage.setItem(STORAGE_KEYS.radioEffect, String(radioEffect.value))
    window.localStorage.setItem(STORAGE_KEYS.soundEffects, String(soundEffects.value))
  } catch {
    // The radio still works when private browsing blocks persistent storage.
  }
})

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeyDown)
  window.addEventListener('keyup', handleGlobalKeyUp)
  window.addEventListener('blur', endPress)
  document.addEventListener('visibilitychange', handleVisibilityChange)
  void connectBackend(displayCallsign.value, channel.value)
})

onBeforeUnmount(() => {
  window.clearInterval(transmissionTimer)
  window.clearTimeout(identitySyncTimer)
  window.removeEventListener('keydown', handleGlobalKeyDown)
  window.removeEventListener('keyup', handleGlobalKeyUp)
  window.removeEventListener('blur', endPress)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})
</script>

<template>
  <div
    class="app-shell"
    :class="{
      'app-shell--live': isTransmitting,
      'app-shell--pending': isMicrophonePending,
    }"
  >
    <a class="skip-link" href="#radio-controls">Skip to radio controls</a>

    <div class="comic-wash comic-wash--yellow" aria-hidden="true"></div>
    <div class="comic-wash comic-wash--cyan" aria-hidden="true"></div>
    <div class="comic-wash comic-wash--pink" aria-hidden="true"></div>

    <header class="topbar">
      <a class="brand" href="#" aria-label="Pop Talk home">
        <span class="brand__pop">POP!</span>
        <span class="brand__talk">TALK</span>
        <span class="brand__bolt" aria-hidden="true">ϟ</span>
      </a>

      <p class="topbar__tagline">Make some noise. Keep it snappy.</p>

      <div class="topbar__actions">
        <span
          class="mode-badge"
          :class="`mode-badge--${connectionState}`"
          :title="
            isBackendConnected
              ? 'Control API connected; audio remains local'
              : connectionError || 'Connecting to the control API'
          "
        >
          <i aria-hidden="true"></i>
          {{ relayLabel }}
        </span>
        <span class="callsign-badge">
          <small>Callsign</small>
          {{ displayCallsign }}
        </span>
        <button class="icon-button" type="button" aria-label="Open radio settings" @click="focusSettings">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
            <path d="M19.4 15a1.8 1.8 0 0 0 .36 2l.06.06-2.83 2.83-.06-.06a1.8 1.8 0 0 0-2-.36 1.8 1.8 0 0 0-1.09 1.65V21h-4v-.09A1.8 1.8 0 0 0 8.75 19.3a1.8 1.8 0 0 0-2 .36l-.06.06-2.83-2.83.06-.06a1.8 1.8 0 0 0 .36-2 1.8 1.8 0 0 0-1.65-1.09h-.09v-4h.09a1.8 1.8 0 0 0 1.65-1.09 1.8 1.8 0 0 0-.36-2l-.06-.06 2.83-2.83.06.06a1.8 1.8 0 0 0 2 .36A1.8 1.8 0 0 0 9.84 2.5V2.4h4v.1a1.8 1.8 0 0 0 1.09 1.65 1.8 1.8 0 0 0 2-.36l.06-.06 2.83 2.83-.06.06a1.8 1.8 0 0 0-.36 2 1.8 1.8 0 0 0 1.65 1.09h.09v4h-.09A1.8 1.8 0 0 0 19.4 15Z" />
          </svg>
        </button>
      </div>
    </header>

    <main id="radio-controls" class="control-room">
      <section class="status-banner" aria-live="polite">
        <div class="status-banner__state">
          <span class="status-lamp" :class="`status-lamp--${stationTone}`" aria-hidden="true"></span>
          <div>
            <small>{{ statusKicker }}</small>
            <strong>{{ stationStatus }}</strong>
          </div>
        </div>

        <div class="status-burst" :class="{ 'status-burst--live': isTransmitting }">
          <span aria-hidden="true">///</span>
          <strong>{{ statusHeadline }}</strong>
          <span aria-hidden="true">///</span>
        </div>

        <div class="status-banner__channel">
          <small>Current channel</small>
          <strong>CH {{ formattedChannel }}</strong>
          <span>Virtual public band</span>
        </div>
      </section>

      <div class="radio-layout">
        <section class="radio-card radio-card--tuner">
          <span class="screw screw--tl" aria-hidden="true"></span>
          <span class="screw screw--tr" aria-hidden="true"></span>
          <span class="screw screw--bl" aria-hidden="true"></span>
          <span class="screw screw--br" aria-hidden="true"></span>

          <ChannelTuner
            v-model="channel"
            :disabled="pressHeld || isTransmitting || isMicrophonePending || isSyncingIdentity"
          />

          <div class="speaker-block">
            <div class="speaker-grille" aria-hidden="true">
              <i v-for="dot in 28" :key="dot"></i>
            </div>
            <div class="speaker-copy">
              <span>Receiver</span>
              <strong>{{ speakerTitle }}</strong>
              <small>{{ speakerDetail }}</small>
            </div>
          </div>

          <div class="radio-note">
            <span aria-hidden="true">★</span>
            <p><strong>Channel tip</strong> Scroll over the display or use the bright arrow keys.</p>
          </div>
        </section>

        <section class="radio-card radio-card--talk">
          <div class="talk-heading">
            <div>
              <span class="section-kicker">One voice at a time</span>
              <h1>Hold it. Say it.<br /><em>Release it.</em></h1>
            </div>
            <span v-if="isTransmitting" class="air-timer" aria-label="Transmission duration">
              REC {{ durationLabel }}
            </span>
          </div>

          <PushToTalk
            :held="pressHeld"
            :transmitting="isTransmitting"
            :pending="isPttPending"
            :disabled="isPttDisabled"
            @press-start="beginPress"
            @press-end="endPress"
          />

          <SignalMeter :bars="signalBars" :level="signalLevel" :active="isTransmitting" />

          <div
            v-if="connectionState === 'disconnected'"
            class="mic-alert mic-alert--connection"
            role="alert"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M4 12a8 8 0 0 1 13.65-5.65M20 12a8 8 0 0 1-13.65 5.65" />
              <path d="m17 3 .65 3.35L21 7M7 21l-.65-3.35L3 17" />
            </svg>
            <div>
              <strong>Control API offline</strong>
              <p>{{ connectionError || 'The backend did not respond.' }}</p>
            </div>
            <button type="button" @click="retryBackend">Try now</button>
          </div>

          <div
            v-if="microphoneError"
            class="mic-alert"
            :class="{ 'mic-alert--warning': microphoneState === 'denied' }"
            role="alert"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 2 2 21h20L12 2Z" />
              <path d="M12 9v5M12 17.5v.1" />
            </svg>
            <div>
              <strong>{{ microphoneLabel }}</strong>
              <p>{{ microphoneError }}</p>
            </div>
            <button
              v-if="microphoneState !== 'unsupported'"
              type="button"
              @click="armMicrophone"
            >
              Try again
            </button>
          </div>
        </section>

        <aside class="side-stack" aria-label="Station details and settings">
          <section class="side-card station-card">
            <header class="side-card__heading">
              <div>
                <span class="section-kicker">Station check</span>
                <h2>Your radio</h2>
              </div>
              <span class="station-card__number">#{{ formattedChannel }}</span>
            </header>

            <dl class="station-stats">
              <div>
                <dt>Status</dt>
                <dd><i :class="`station-dot--${stationTone}`" aria-hidden="true"></i>{{ stationStatus }}</dd>
              </div>
              <div>
                <dt>Microphone</dt>
                <dd>{{ microphoneLabel }}</dd>
              </div>
              <div>
                <dt>Relay</dt>
                <dd><i :class="`station-dot--${relayTone}`" aria-hidden="true"></i>{{ relayLabel }}</dd>
              </div>
            </dl>

            <div class="privacy-note">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 10V7a6 6 0 0 1 12 0v3M5 10h14v11H5z" />
                <path d="M12 14v3" />
              </svg>
              <p>
                <strong>Control link only.</strong>
                Presence and PTT use the API; audio stays in this tab until a media relay is added.
              </p>
            </div>

            <button
              v-if="microphoneState === 'ready'"
              class="secondary-button"
              type="button"
              @click="disarmMicrophone"
            >
              Release microphone
            </button>
            <button
              v-else
              class="secondary-button"
              type="button"
              :disabled="isMicrophonePending || microphoneState === 'unsupported'"
              @click="armMicrophone"
            >
              {{ isMicrophonePending ? 'Waiting for permission…' : 'Arm microphone' }}
            </button>
          </section>

          <section ref="settingsPanel" class="side-card settings-card">
            <header class="side-card__heading">
              <div>
                <span class="section-kicker">Twist the knobs</span>
                <h2>Radio kit</h2>
              </div>
              <span class="settings-card__icon" aria-hidden="true">✦</span>
            </header>

            <label class="callsign-field">
              <span>Callsign</span>
              <input
                :value="callsign"
                type="text"
                maxlength="14"
                autocomplete="nickname"
                spellcheck="false"
                aria-describedby="callsign-hint"
                @input="updateCallsign"
              />
              <small id="callsign-hint">Letters, numbers, and dashes</small>
            </label>

            <button
              class="toggle-row"
              type="button"
              role="switch"
              :aria-checked="radioEffect"
              @click="radioEffect = !radioEffect"
            >
              <span>
                <strong>Vintage filter</strong>
                <small>Classic narrow-band radio tone</small>
              </span>
              <i :class="{ 'toggle-row__on': radioEffect }" aria-hidden="true"><b></b></i>
            </button>

            <button
              class="toggle-row"
              type="button"
              role="switch"
              :aria-checked="soundEffects"
              @click="soundEffects = !soundEffects"
            >
              <span>
                <strong>Sound cues</strong>
                <small>Beeps, clicks, and squelch</small>
              </span>
              <i :class="{ 'toggle-row__on': soundEffects }" aria-hidden="true"><b></b></i>
            </button>
          </section>

          <section class="side-card activity-card">
            <header class="side-card__heading">
              <div>
                <span class="section-kicker">This session</span>
                <h2>Radio log</h2>
              </div>
              <span class="activity-card__count">{{ activity.length }}</span>
            </header>

            <ol class="activity-list" aria-live="polite">
              <li v-for="item in activity" :key="item.id">
                <span class="activity-list__icon" :class="`activity-list__icon--${item.kind}`" aria-hidden="true">
                  {{ item.kind === 'channel' ? '#' : item.kind === 'transmission' ? '!' : item.kind === 'warning' ? '×' : '•' }}
                </span>
                <div>
                  <strong>{{ item.title }}</strong>
                  <p>{{ item.detail }}</p>
                </div>
                <time :datetime="item.datetime">{{ item.time }}</time>
              </li>
            </ol>
          </section>
        </aside>
      </div>
    </main>

    <footer class="ticker">
      <div aria-hidden="true">
        <span>Push</span><i>★</i><span>Talk</span><i>★</i><span>Release</span><i>★</i>
        <span>Keep it friendly</span><i>★</i><span>Over & out</span><i>★</i>
        <span>Push</span><i>★</i><span>Talk</span><i>★</i><span>Release</span><i>★</i>
        <span>Keep it friendly</span><i>★</i><span>Over & out</span><i>★</i>
      </div>
    </footer>
  </div>
</template>
