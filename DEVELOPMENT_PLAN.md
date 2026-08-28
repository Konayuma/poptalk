# Development Roadmap

## Phase 1: Shell & Visual UI (Vibe Check)
- [ ] Set up Next.js / React project with Tailwind CSS.
- [ ] Implement global Pop Art styling (halftone background, thick borders, neo-brutalist shadows).
- [ ] Build the Walkie-Talkie main interface frame.
- [ ] Build the interactive **Push-To-Talk (PTT)** button with press animations and sound/visual triggers.
- [ ] Build the **Channel Selector Dial/Display**.

## Phase 2: Audio Engine (Web Audio API)
- [ ] Capture microphone input using `navigator.mediaDevices.getUserMedia`.
- [ ] Create Web Audio API pipeline: `Microphone -> Bandpass Filter -> Gain (Volume) -> Output`.
- [ ] Add visualizer node (`AnalyserNode`) to extract amplitude for UI animations.

## Phase 3: Real-Time Communication
- [ ] Set up Socket.io server (or PeerJS peer connections).
- [ ] Implement channel room joining/leaving logic.
- [ ] Stream chunked audio data on PTT activation; broadcast to connected channel peers.
- [ ] Handle incoming audio playback with visual receiver indicators.

## Phase 4: Polish & Comic FX
- [ ] Add sound effects (squelch on button release, static bursts, beep on channel change).
- [ ] Add comic text popups ("TALKING...", "OVER!", "STATIC...") on audio state changes.
- [ ] Add responsive mobile layout touch optimizations.
