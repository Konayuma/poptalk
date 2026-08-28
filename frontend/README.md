# Pop Talk frontend

The Vue 3 client for Pop Talk, a pop-art push-to-talk radio experience.

## Included

- Channels 01–99 with dial, range, wheel, and nearby-channel controls
- Hold-to-talk pointer and keyboard interactions with safe release handling
- Real microphone permission, capture, live level visualization, and cleanup
- Optional 400–2500 Hz vintage radio filter and synthesized sound cues
- Persistent callsign, channel, and radio preferences
- Typed backend client with session recovery, presence polling, PTT floor control,
  heartbeats, retries, and visible offline states
- Responsive, accessible UI with permission, device, and connection error states

The control API coordinates presence and one-speaker-at-a-time access. Audio is still
processed inside the browser and is not uploaded by this client; a WebRTC or other
media transport can be added separately by the backend team.

## Run locally

```sh
npm install
npm run dev
```

Copy `.env.example` to `.env` when the backend origin differs from the default.
During local development, Vite proxies `/api` to `VITE_API_PROXY_TARGET`. For a
deployed frontend, set `VITE_API_URL` to the full versioned API URL.

Microphone capture works on `localhost` or over HTTPS. Hold the main button or press
Space while focus is not inside another control. Releasing the pointer/key, changing
tabs, or blurring the window immediately ends capture.

## Backend API contract

The frontend expects JSON under `/api/v1` by default. `POST /sessions` and
`GET /health` are public; every other request sends the session token as
`Authorization: Bearer <token>`.

- `GET /health` — service status.
- `POST /sessions` — create presence from `{ "callsign", "channel" }`.
- `GET|PATCH|DELETE /sessions/current` — read, update, or end the current session.
- `POST /sessions/current/heartbeat` — renew current-session presence.
- `GET /channels` and `GET /channels/{channel}` — channel presence and active caller.
- `POST /channels/{channel}/transmissions` — atomically claim the PTT floor.
- `PATCH|DELETE /transmissions/{id}` — renew or release an owned PTT floor.

Successful resource responses use `{ "data": ... }`. Session creation also returns
`meta.session_token`. A channel resource contains `number`, `listener_count`,
`is_busy`, and an optional `active_transmission`; session and transmission resources
use the TypeScript shapes in `src/services/radioApi.ts`.

The client handles `401` by replacing an expired session, `409` as a busy-channel
conflict, `422` as validation failure, and network/time-out failures as a disconnected
state with automatic and manual retry.

## Production check

```sh
npm test
npm run build
```

Vitest covers UI controls, API request/error behavior, session recovery, and PTT
floor handling. The build command runs `vue-tsc` before creating the Vite production
bundle.
