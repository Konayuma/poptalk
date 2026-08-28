<script setup lang="ts">
import { computed, onBeforeUnmount } from 'vue'

const props = defineProps<{
  modelValue: number
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

const formattedChannel = computed(() => String(props.modelValue).padStart(2, '0'))
let accumulatedWheelDelta = 0
let wheelResetTimer = 0

const quickChannels = computed(() =>
  [-2, -1, 0, 1, 2].map((offset) => {
    const zeroBased = (props.modelValue - 1 + offset + 99) % 99
    return zeroBased + 1
  }),
)

function setChannel(value: number) {
  if (props.disabled) return
  const wrapped = ((Math.round(value) - 1 + 99) % 99) + 1
  emit('update:modelValue', wrapped)
}

function handleWheel(event: WheelEvent) {
  accumulatedWheelDelta += event.deltaY
  window.clearTimeout(wheelResetTimer)
  wheelResetTimer = window.setTimeout(() => {
    accumulatedWheelDelta = 0
  }, 160)

  if (Math.abs(accumulatedWheelDelta) < 24) return
  setChannel(props.modelValue + (accumulatedWheelDelta > 0 ? 1 : -1))
  accumulatedWheelDelta = 0
}

onBeforeUnmount(() => window.clearTimeout(wheelResetTimer))
</script>

<template>
  <section class="tuner" aria-labelledby="channel-heading">
    <div class="tuner__heading">
      <div>
        <span class="eyebrow">Public frequency</span>
        <h2 id="channel-heading">Pick a channel</h2>
      </div>
      <span class="live-chip"><i aria-hidden="true"></i> Open band</span>
    </div>

    <div class="tuner__console">
      <button
        class="tuner__step tuner__step--down"
        type="button"
        :disabled="disabled"
        aria-label="Previous channel"
        @click="setChannel(modelValue - 1)"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="m15 5-7 7 7 7" />
        </svg>
      </button>

      <div class="channel-display" aria-live="polite" @wheel.prevent="handleWheel">
        <span class="channel-display__label">Channel</span>
        <strong>{{ formattedChannel }}</strong>
        <span class="channel-display__frequency">Virtual public band</span>
        <span class="channel-display__shine" aria-hidden="true"></span>
      </div>

      <button
        class="tuner__step tuner__step--up"
        type="button"
        :disabled="disabled"
        aria-label="Next channel"
        @click="setChannel(modelValue + 1)"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="m9 5 7 7-7 7" />
        </svg>
      </button>
    </div>

    <input
      class="channel-range"
      type="range"
      min="1"
      max="99"
      step="1"
      :value="modelValue"
      :disabled="disabled"
      aria-label="Channel number"
      @input="setChannel(Number(($event.target as HTMLInputElement).value))"
    />

    <div class="quick-channels" role="group" aria-label="Nearby channels">
      <button
        v-for="channel in quickChannels"
        :key="channel"
        type="button"
        :class="{ 'quick-channels__active': channel === modelValue }"
        :disabled="disabled"
        :aria-current="channel === modelValue ? 'true' : undefined"
        :aria-label="`Tune to channel ${channel}`"
        @click="setChannel(channel)"
      >
        {{ String(channel).padStart(2, '0') }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.tuner {
  display: grid;
  gap: 1rem;
}

.tuner__heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

.eyebrow {
  display: block;
  margin-bottom: 0.12rem;
  color: var(--ink-soft);
  font-family: var(--font-mono);
  font-size: 0.68rem;
  font-weight: 900;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

h2 {
  font-family: var(--font-display);
  font-size: clamp(1.7rem, 4vw, 2.25rem);
  letter-spacing: 0.02em;
  line-height: 0.95;
  text-transform: uppercase;
}

.live-chip {
  display: inline-flex;
  flex: none;
  align-items: center;
  gap: 0.42rem;
  border: 2px solid var(--ink);
  border-radius: 999px;
  background: var(--paper);
  padding: 0.38rem 0.65rem;
  font-family: var(--font-mono);
  font-size: 0.65rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.live-chip i {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background: var(--cyan);
  box-shadow: 0 0 0 2px var(--ink);
}

.tuner__console {
  display: grid;
  grid-template-columns: 3.3rem minmax(0, 1fr) 3.3rem;
  align-items: stretch;
  gap: 0.7rem;
}

.tuner__step {
  display: grid;
  min-height: 7.7rem;
  place-items: center;
  border: 3px solid var(--ink);
  border-radius: var(--radius-sm);
  background: var(--yellow);
  box-shadow: 3px 3px 0 var(--ink);
  color: var(--ink);
  cursor: pointer;
  transition:
    translate 120ms ease,
    box-shadow 120ms ease,
    background-color 120ms ease;
}

.tuner__step--up {
  background: var(--cyan);
}

.tuner__step:hover:not(:disabled) {
  translate: -1px -1px;
  box-shadow: 5px 5px 0 var(--ink);
}

.tuner__step:active:not(:disabled) {
  translate: 3px 3px;
  box-shadow: 0 0 0 var(--ink);
}

.tuner__step:disabled {
  cursor: not-allowed;
  filter: grayscale(1);
  opacity: 0.48;
}

.tuner__step svg {
  width: 1.7rem;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 3.2;
}

.channel-display {
  position: relative;
  display: grid;
  min-height: 7.7rem;
  place-items: center;
  overflow: hidden;
  border: 4px solid var(--ink);
  border-radius: var(--radius-sm);
  background:
    linear-gradient(rgba(0, 229, 255, 0.07) 50%, transparent 50%) 0 0 / 100% 4px,
    #172c2e;
  box-shadow:
    inset 0 0 0 4px rgba(255, 255, 255, 0.08),
    5px 5px 0 var(--ink);
  color: #c8fff5;
  font-family: var(--font-mono);
}

.channel-display strong {
  position: relative;
  z-index: 1;
  margin: -0.25rem 0 -0.5rem;
  color: var(--yellow);
  font-size: clamp(3.6rem, 11vw, 5.6rem);
  font-weight: 900;
  letter-spacing: -0.09em;
  line-height: 0.9;
  text-shadow: 3px 3px 0 rgba(255, 0, 85, 0.65);
}

.channel-display__label,
.channel-display__frequency {
  position: relative;
  z-index: 1;
  font-size: 0.66rem;
  font-weight: 900;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

.channel-display__label {
  align-self: end;
  color: var(--cyan);
}

.channel-display__frequency {
  align-self: start;
}

.channel-display__shine {
  position: absolute;
  inset: -70% -30%;
  rotate: 24deg;
  background: linear-gradient(
    90deg,
    transparent 43%,
    rgba(255, 255, 255, 0.11) 48%,
    rgba(255, 255, 255, 0.11) 52%,
    transparent 57%
  );
  pointer-events: none;
}

.channel-range {
  width: 100%;
  height: 1.1rem;
  margin: 0.05rem 0;
  appearance: none;
  border: 3px solid var(--ink);
  border-radius: 999px;
  background:
    repeating-linear-gradient(
      90deg,
      transparent 0,
      transparent calc(10% - 2px),
      var(--ink) calc(10% - 2px),
      var(--ink) 10%
    ),
    var(--paper);
  cursor: ew-resize;
}

.channel-range::-webkit-slider-thumb {
  width: 1.8rem;
  height: 1.8rem;
  appearance: none;
  border: 3px solid var(--ink);
  border-radius: 50%;
  background: var(--pink);
  box-shadow: 2px 2px 0 var(--ink);
}

.channel-range::-moz-range-thumb {
  width: 1.45rem;
  height: 1.45rem;
  border: 3px solid var(--ink);
  border-radius: 50%;
  background: var(--pink);
  box-shadow: 2px 2px 0 var(--ink);
}

.channel-range:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.quick-channels {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.45rem;
}

.quick-channels button {
  min-width: 0;
  border: 2px solid var(--ink);
  border-radius: 0.3rem;
  background: var(--paper);
  padding: 0.42rem 0.25rem;
  font-family: var(--font-mono);
  font-size: 0.74rem;
  font-weight: 900;
  cursor: pointer;
}

.quick-channels button:hover:not(:disabled) {
  background: color-mix(in srgb, var(--cyan) 30%, var(--paper));
}

.quick-channels button:disabled {
  cursor: not-allowed;
}

.quick-channels__active {
  background: var(--pink) !important;
  color: var(--ink);
  box-shadow: 2px 2px 0 var(--ink);
  translate: -1px -1px;
}

@media (max-width: 420px) {
  .tuner__console {
    grid-template-columns: 2.8rem minmax(0, 1fr) 2.8rem;
    gap: 0.45rem;
  }

  .tuner__step,
  .channel-display {
    min-height: 6.9rem;
  }

  .live-chip {
    display: none;
  }
}
</style>
