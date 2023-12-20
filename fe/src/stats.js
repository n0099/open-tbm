/* eslint-disable */
import Stats from 'stats.js';
const stats = new Stats();
stats.showPanel(0); // 0: fps, 1: ms, 2: mb, 3+: custom

const container = document.createElement('div');
container.className = 'statsjs';
const style = document.createElement('style');

// https://github.com/mrdoob/stats.js/issues/115
style.textContent = '.statsjs canvas { display: block !important; }';
container.append(style);
container.append(stats.dom);
document.body.append(container);

requestAnimationFrame(function updateStats() {
    stats.update();
    requestAnimationFrame(updateStats);
});
