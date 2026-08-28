<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  held: boolean
  transmitting: boolean
  pending: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{
  pressStart: []
  pressEnd: []
}>()

const buttonLabel = computed(() => {
  if (props.disabled) return 'Push to talk unavailable'
  if (props.pending) return 'Release to cancel the connection request'
  if (props.transmitting) return 'Release to stop transmitting'
  return 'Hold to start transmitting'
})

function handlePointerDown(event: PointerEvent) {
  if (props.disabled || (event.pointerType === 'mouse' && event.button !== 0)) return
  ;(event.currentTarget as HTMLButtonElement).setPointerCapture?.(event.pointerId)
  emit('pressStart')
}

function handlePointerEnd(event: PointerEvent) {
  const button = event.currentTarget as HTMLButtonElement
  if (button.hasPointerCapture?.(event.pointerId)) button.releasePointerCapture(event.pointerId)
  emit('pressEnd')
}

function handleKeyDown(event: KeyboardEvent) {
  if (props.disabled || event.repeat) return
  emit('pressStart')
}
</script>

<template>
  <div
    class="ptt-shell"
    :class="{
      'ptt-shell--held': held,
      'ptt-shell--live': transmitting,
      'ptt-shell--pending': pending,
    }"
  >
    <span class="ptt-shell__burst ptt-shell__burst--one" aria-hidden="true">ZAP!</span>
    <span class="ptt-shell__burst ptt-shell__burst--two" aria-hidden="true">PTT</span>

    <div class="ptt-rings" aria-hidden="true">
      <span></span>
      <span></span>
      <span></span>
    </div>

    <button
      class="ptt-button"
      type="button"
      :disabled="disabled"
      :aria-label="buttonLabel"
      :aria-pressed="held"
      @contextmenu.prevent
      @pointerdown.prevent="handlePointerDown"
      @pointerup.prevent="handlePointerEnd"
      @pointercancel="handlePointerEnd"
      @keydown.space.prevent="handleKeyDown"
      @keydown.enter.prevent="handleKeyDown"
      @keyup.space.prevent="$emit('pressEnd')"
      @keyup.enter.prevent="$emit('pressEnd')"
    >
      <span class="ptt-button__gloss" aria-hidden="true"></span>
      <span class="ptt-button__status">
        {{ pending ? 'opening line' : transmitting ? 'on air' : 'push to' }}
      </span>
      <strong>{{ pending ? 'WAIT' : transmitting ? 'TALK' : 'TALK' }}</strong>
      <span class="ptt-button__hint">
        {{ pending ? 'release to cancel' : transmitting ? 'release when done' : 'press + hold' }}
      </span>
    </button>

    <p class="ptt-caption">
      <kbd>Space</kbd>
      <span>{{ transmitting ? 'Keep holding — you are live' : 'or hold the button' }}</span>
    </p>
  </div>
</template>

<style scoped>
.ptt-shell {
  position: relative;
  display: grid;
  min-height: 24rem;
  place-items: center;
  isolation: isolate;
  user-select: none;
  -webkit-user-select: none;
}

.ptt-rings {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: -1;
  width: min(85%, 22rem);
  aspect-ratio: 1;
  translate: -50% -53%;
  pointer-events: none;
}

.ptt-rings span {
  position: absolute;
  inset: 0;
  border: 3px solid var(--ink);
  border-radius: 50%;
  opacity: 0;
  scale: 0.6;
}

.ptt-shell--held .ptt-rings span {
  animation: radio-wave 1.7s cubic-bezier(0.2, 0.75, 0.2, 1) infinite;
}

.ptt-rings span:nth-child(2) {
  animation-delay: 0.5s;
}

.ptt-rings span:nth-child(3) {
  animation-delay: 1s;
}

.ptt-button {
  position: relative;
  display: grid;
  width: clamp(13.5rem, 44vw, 17.5rem);
  aspect-ratio: 1;
  place-content: center;
  overflow: hidden;
  border: 7px solid var(--ink);
  border-radius: 50%;
  background:
    radial-gradient(circle at 50% 72%, rgba(120, 0, 35, 0.36), transparent 36%),
    var(--pink);
  box-shadow:
    0 0 0 0.75rem var(--yellow),
    0 0 0 1.05rem var(--ink),
    0.8rem 0.95rem 0 var(--ink);
  color: var(--paper);
  font-family: var(--font-display);
  text-align: center;
  text-shadow: 3px 3px 0 var(--ink);
  cursor: grab;
  touch-action: none;
  transition:
    translate 130ms ease,
    box-shadow 130ms ease,
    filter 130ms ease,
    background-color 130ms ease;
}

.ptt-button:hover:not(:disabled) {
  translate: -0.2rem -0.2rem;
  box-shadow:
    0 0 0 0.75rem var(--yellow),
    0 0 0 1.05rem var(--ink),
    1.05rem 1.2rem 0 var(--ink);
  filter: saturate(1.12);
}

.ptt-button:active:not(:disabled),
.ptt-shell--held .ptt-button {
  translate: 0.55rem 0.65rem;
  box-shadow:
    0 0 0 0.75rem var(--yellow),
    0 0 0 1.05rem var(--ink),
    0.18rem 0.22rem 0 var(--ink);
  cursor: grabbing;
}

.ptt-shell--live .ptt-button {
  background:
    radial-gradient(circle at 50% 72%, rgba(71, 0, 25, 0.4), transparent 36%),
    #ff174f;
  animation: live-pulse 0.72s steps(2, end) infinite;
}

.ptt-shell--pending .ptt-button {
  background:
    repeating-linear-gradient(
      -45deg,
      transparent 0,
      transparent 12px,
      rgba(17, 17, 17, 0.16) 12px,
      rgba(17, 17, 17, 0.16) 24px
    ),
    var(--cyan);
  color: var(--ink);
  text-shadow: 2px 2px 0 var(--paper);
}

.ptt-button:disabled {
  cursor: not-allowed;
  filter: grayscale(1);
  opacity: 0.58;
}

.ptt-button__gloss {
  position: absolute;
  top: 10%;
  left: 22%;
  width: 36%;
  height: 16%;
  rotate: -18deg;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.3);
  filter: blur(1px);
}

.ptt-button__status,
.ptt-button__hint {
  position: relative;
  z-index: 1;
  display: block;
  font-family: var(--font-mono);
  font-size: 0.66rem;
  font-weight: 900;
  letter-spacing: 0.16em;
  line-height: 1;
  text-transform: uppercase;
}

.ptt-button strong {
  position: relative;
  z-index: 1;
  display: block;
  margin: 0.12rem 0 0.35rem;
  font-size: clamp(3.6rem, 12vw, 5.4rem);
  letter-spacing: 0.01em;
  line-height: 0.92;
  transform: skew(-5deg);
}

.ptt-shell__burst {
  position: absolute;
  z-index: 2;
  border: 3px solid var(--ink);
  background: var(--yellow);
  padding: 0.4rem 0.7rem;
  font-family: var(--font-display);
  font-size: 1.1rem;
  letter-spacing: 0.04em;
  line-height: 1;
  pointer-events: none;
  transition:
    opacity 150ms ease,
    scale 150ms ease,
    rotate 150ms ease;
}

.ptt-shell__burst--one {
  top: 15%;
  left: 3%;
  rotate: -10deg;
  clip-path: polygon(0 20%, 16% 18%, 20% 0, 36% 15%, 52% 4%, 62% 18%, 100% 14%, 90% 45%, 100% 72%, 70% 73%, 62% 100%, 43% 80%, 13% 91%, 15% 62%, 0 52%);
  padding: 1.1rem 1.3rem;
}

.ptt-shell__burst--two {
  right: 5%;
  bottom: 22%;
  rotate: 8deg;
  background: var(--cyan);
}

.ptt-shell--held .ptt-shell__burst--one {
  rotate: -17deg;
  scale: 1.18;
}

.ptt-shell--held .ptt-shell__burst--two {
  rotate: 14deg;
  scale: 1.15;
}

.ptt-caption {
  position: absolute;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
  width: 100%;
  margin: 0;
  color: var(--ink-soft);
  font-family: var(--font-mono);
  font-size: 0.72rem;
  font-weight: 700;
  text-align: center;
}

kbd {
  border: 2px solid var(--ink);
  border-radius: 0.25rem;
  background: var(--paper);
  box-shadow: 2px 2px 0 var(--ink);
  padding: 0.25rem 0.45rem;
  color: var(--ink);
  font: inherit;
  font-weight: 900;
}

@keyframes radio-wave {
  0% {
    opacity: 0.6;
    scale: 0.58;
  }
  75%,
  100% {
    opacity: 0;
    scale: 1.1;
  }
}

@keyframes live-pulse {
  50% {
    filter: brightness(1.16) saturate(1.2);
  }
}

@media (max-width: 560px) {
  .ptt-shell {
    min-height: 21.5rem;
  }

  .ptt-button {
    width: min(14.5rem, 67vw);
  }

  .ptt-shell__burst--one {
    left: -1%;
  }

  .ptt-shell__burst--two {
    right: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ptt-shell--held .ptt-rings span,
  .ptt-shell--live .ptt-button {
    animation: none;
  }
}
</style>
