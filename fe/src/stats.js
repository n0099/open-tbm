/* eslint-disable @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
import Stats from 'stats.js';

// eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
const stats = new Stats();
stats.showPanel(0); // 0: fps, 1: ms, 2: mb, 3+: custom

const container = document.createElement('div');
container.className = 'statsjs';
const style = document.createElement('style');

// https://github.com/mrdoob/stats.js/issues/115
style.textContent = '.statsjs canvas { display: block !important; }';
container.append(style);
// eslint-disable-next-line @typescript-eslint/no-unsafe-argument
container.append(stats.dom);
document.body.append(container);

requestAnimationFrame(function updateStats() {
    stats.update();
    requestAnimationFrame(updateStats);
});
