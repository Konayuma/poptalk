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
- **Frontend:** React (Next.js or Vite), Tailwind CSS + Custom CSS (for halftone patterns & SVG filters), Framer Motion.
- **Audio Processing:** Web Audio API (MediaRecorder / AudioContext).
- **Real-Time Server:** Node.js + Socket.io or WebRTC (PeerJS for rapid prototyping).
