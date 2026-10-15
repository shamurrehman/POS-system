// Short audible feedback for scanner use, so the cashier doesn't need to look at the screen.
let ctx;

export function beep(ok = true) {
    try {
        ctx ??= new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'square';
        osc.frequency.value = ok ? 1200 : 220;
        gain.gain.value = 0.05;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + (ok ? 0.08 : 0.35));
    } catch {
        // Audio is optional.
    }
}
