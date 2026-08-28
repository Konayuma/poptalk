# Development Roadmap

## Phase 1: Shell & Visual UI (Vibe Check)
- [x] Set up the Vue 3, TypeScript, and Vite frontend.
- [x] Implement global Pop Art styling (halftone background, thick borders, neo-brutalist shadows).
- [x] Build the Walkie-Talkie main interface frame.
- [x] Build the interactive **Push-To-Talk (PTT)** button with press animations and sound/visual triggers.
- [x] Build the **Channel Selector Dial/Display**.

## Phase 2: Audio Engine (Web Audio API)
- [x] Capture microphone input using `navigator.mediaDevices.getUserMedia`.
- [x] Create Web Audio API pipeline: `Microphone -> Radio Filters -> Gain -> Processed MediaStream`.
- [x] Add visualizer node (`AnalyserNode`) to extract amplitude for UI animations.

## Phase 3: Real-Time Communication
> This phase is required before Pop Talk supports multi-user communication. The current frontend clearly operates in local mode.

- [ ] Set up Socket.io server (or PeerJS peer connections).
- [ ] Implement channel room joining/leaving logic.
- [ ] Stream chunked audio data on PTT activation; broadcast to connected channel peers.
- [ ] Handle incoming audio playback with visual receiver indicators.

## Phase 4: Polish & Comic FX
- [x] Add sound effects (squelch on button release, static bursts, beep on channel change).
- [x] Add dynamic comic status callouts for microphone and transmission states.
- [x] Add responsive mobile layout and touch optimizations.
