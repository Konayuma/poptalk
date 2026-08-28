<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  bars: number[]
  level: number
  active: boolean
}>()

const levelPercent = computed(() => Math.round(props.level * 100))
const accessibleLevel = computed(() =>
  props.active ? Math.round(levelPercent.value / 10) * 10 : 0,
)
const levelWord = computed(() => {
  if (!props.active) return 'quiet'
  if (props.level > 0.72) return 'loud'
  if (props.level > 0.36) return 'clear'
  return 'soft'
})

function barHeight(value: number) {
  return `${Math.max(8, Math.round(value * 100))}%`
}
</script>

<template>
  <section class="signal-meter" :class="{ 'signal-meter--active': active }">
    <header>
      <div>
        <span class="signal-meter__eyebrow">Mic signal</span>
        <strong>{{ active ? 'Broadcast level' : 'Standing by' }}</strong>
      </div>
      <span class="signal-meter__readout">{{ active ? `${levelPercent}% / ${levelWord}` : '— dB' }}</span>
    </header>

    <div
      class="signal-meter__screen"
      role="meter"
      aria-label="Microphone level"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="accessibleLevel"
      :aria-valuetext="active ? `${accessibleLevel} percent, ${levelWord}` : 'Not transmitting'"
    >
      <div class="signal-meter__bars" aria-hidden="true">
        <i
          v-for="(_, index) in bars"
          :key="index"
          :style="{ height: barHeight(bars[index] ?? 0) }"
        ></i>
      </div>
      <div class="signal-meter__scale" aria-hidden="true">
        <span>MIN</span>
        <span>VOICE</span>
        <span>MAX</span>
      </div>
    </div>
  </section>
</template>

<style scoped>
.signal-meter {
  display: grid;
  gap: 0.7rem;
}

header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

header > div {
  display: grid;
  gap: 0.12rem;
}

header strong {
  font-size: 0.9rem;
  font-weight: 900;
  text-transform: uppercase;
}

.signal-meter__eyebrow,
.signal-meter__readout {
  font-family: var(--font-mono);
  font-size: 0.62rem;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.signal-meter__eyebrow {
  color: var(--ink-soft);
}

.signal-meter__readout {
  border: 2px solid var(--ink);
  border-radius: 999px;
  background: var(--yellow);
  padding: 0.25rem 0.5rem;
}

.signal-meter__screen {
  display: grid;
  min-height: 6.1rem;
  overflow: hidden;
  border: 3px solid var(--ink);
  border-radius: var(--radius-sm);
  background:
    linear-gradient(rgba(255, 255, 255, 0.035) 50%, transparent 50%) 0 0 / 100% 4px,
    #172c2e;
  box-shadow:
    inset 0 0 0 3px rgba(255, 255, 255, 0.06),
    4px 4px 0 var(--ink);
  padding: 0.8rem 0.9rem 0.55rem;
}

.signal-meter__bars {
  display: flex;
  height: 3.7rem;
  align-items: end;
  gap: clamp(0.12rem, 0.65vw, 0.26rem);
}

.signal-meter__bars i {
  flex: 1;
  min-width: 2px;
  border: 1px solid rgba(17, 17, 17, 0.8);
  border-radius: 1px 1px 0 0;
  background: var(--cyan);
  opacity: 0.42;
  transition:
    height 60ms linear,
    background-color 120ms ease,
    opacity 120ms ease;
}

.signal-meter__bars i:nth-last-child(-n + 6) {
  background: var(--yellow);
}

.signal-meter__bars i:nth-last-child(-n + 2) {
  background: var(--pink);
}

.signal-meter--active .signal-meter__bars i {
  opacity: 1;
  box-shadow: 0 0 8px currentColor;
}

.signal-meter__scale {
  display: flex;
  justify-content: space-between;
  color: #b6d9d2;
  font-family: var(--font-mono);
  font-size: 0.52rem;
  font-weight: 900;
  letter-spacing: 0.12em;
}
</style>
