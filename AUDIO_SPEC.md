# Audio Engine & Radio Effect Spec

```javascript
// Web Audio API Radio Filter Node Pipeline
const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
const sourceNode = audioCtx.createMediaStreamSource(stream);

// Highpass filter (cuts low rumble)
const highpass = audioCtx.createBiquadFilter();
highpass.type = "highpass";
highpass.frequency.value = 400;

// Lowpass filter (cuts high hiss, gives walkie-talkie muffled tone)
const lowpass = audioCtx.createBiquadFilter();
lowpass.type = "lowpass";
lowpass.frequency.value = 2500;

// Connect Nodes
sourceNode.connect(highpass);
highpass.connect(lowpass);
// Connect lowpass to socket output / audio analyzer
