# Pop-Talk: Retro Pop Art Walkie-Talkie

## 1. Concept & Vision
Pop-Talk is a real-time voice walkie-talkie web app wrapped in a bold, vibrant Pop Art aesthetic (think Roy Lichtenstein, comic book halftones, thick black outlines, neon swatches, and dramatic action callouts).

## 2. Core Features
- **Channel Dial:** Switch between public frequency channels (e.g., #01 to #99).
- **Push-to-Talk (PTT):** Hold-to-speak interactive button with dynamic visual feedback.
- **Real-Time Audio Stream:** WebSockets / WebRTC audio streaming between users on the same channel.
- **Pop-Art Visualizer:** Audio input triggers halftone dot pulses, dynamic comic speech bubbles ("BZZZT!", "OVER!"), and screen flashes.
- **Audio Effects:** Optional vintage radio filter (bandpass filter + slight vinyl/crackle gain via Web Audio API).

## 3. Tech Stack
- **Frontend:** Vue 3, TypeScript, Vite, and custom CSS for the Pop Art interface.
- **Audio Processing:** MediaDevices + Web Audio API (`AudioContext`, filters, analyser, and processed `MediaStream`).
- **Application Server:** Laravel scaffold.
- **Real-Time Relay:** Not selected yet. Channel presence and remote audio still require a defined WebRTC or WebSocket contract.
