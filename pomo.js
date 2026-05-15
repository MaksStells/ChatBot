const STORAGE_KEY = 'brightbot_pomo';

function saveState() {
  const state = {
    workTime, restTime, totalCycles, numberofCycles,
    currentCycle, currentTime,
    isRunning,
    // Save absolute end time so we can recover even if tab was closed
    endTimestamp: isRunning ? Date.now() + currentTime : null,
    savedAt: Date.now()
  };
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function loadState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch(e) { return null; }
}

function clearState() {
  localStorage.removeItem(STORAGE_KEY);
}

let workTime      = 25 * 60 * 1000;
let restTime      = 5  * 60 * 1000;
let totalCycles   = 4;
let numberofCycles = totalCycles;
let currentCycle  = 'Work';
let currentTime   = workTime;
let isRunning     = false;
let countdownInterval = null;


const timerEl      = document.getElementById('timer');
const cycleEl      = document.getElementById('cycle');
const cyclesLeftEl = document.getElementById('cycles-left');
const ringProgress = document.getElementById('ring-progress');
const timerCard    = document.querySelector('.timer-card');
const alarmAudio   = document.getElementById('alarm');
const clickAudio   = document.getElementById('click');

const playBtn  = document.getElementById('playBtn');
const skipBtn  = document.getElementById('skipBtn');
const resetBtn = document.getElementById('resetBtn');

const workInput     = document.getElementById('work-time-settings');
const restInput     = document.getElementById('rest-time-settings');
const intervalInput = document.getElementById('interval-settings');

const CIRCUMFERENCE = 2 * Math.PI * 100;

function updateRing(fraction) {
  // fraction 0→1: full ring to empty
  const offset = CIRCUMFERENCE * (1 - Math.max(0, Math.min(1, fraction)));
  ringProgress.style.strokeDashoffset = offset;
  if (currentCycle === 'Rest') {
    ringProgress.classList.add('rest-color');
  } else {
    ringProgress.classList.remove('rest-color');
  }
}

function updateDisplay(ms) {
  const totalMs = currentCycle === 'Work' ? workTime : restTime;
  const mins = Math.floor(ms / 1000 / 60).toString().padStart(2, '0');
  const secs = Math.floor(ms / 1000 % 60).toString().padStart(2, '0');

  timerEl.textContent = `${mins}:${secs}`;
  timerEl.classList.remove('finished');

  
  if (currentCycle === 'Work') {
    cycleEl.textContent = '🍅 Focus';
    cycleEl.className = 'cycle-badge work';
    timerCard.classList.add('work-mode');
    timerCard.classList.remove('rest-mode');
  } else {
    cycleEl.textContent = '☕ Break';
    cycleEl.className = 'cycle-badge rest';
    timerCard.classList.add('rest-mode');
    timerCard.classList.remove('work-mode');
  }

  cyclesLeftEl.textContent = `${numberofCycles} interval${numberofCycles !== 1 ? 's' : ''} remaining`;
  updateRing(ms / totalMs);

  document.title = `${mins}:${secs} · ${currentCycle === 'Work' ? 'Focus' : 'Break'} — BrightBot`;
}

// Countdown
function startCountdown(fromMs) {
  let remaining = fromMs;
  clearInterval(countdownInterval);

  countdownInterval = setInterval(() => {
    remaining -= 1000;
    currentTime = remaining;
    updateDisplay(remaining);
    saveState();

    if (remaining <= 0) {
      clearInterval(countdownInterval);
      try { alarmAudio.play(); } catch(e) {}
      onCycleEnd();
    }
  }, 1000);
}

function onCycleEnd() {
  // end after all work sessions complete
  if (currentCycle === 'Work') {
    numberofCycles--;
    if (numberofCycles <= 0) {
      showFinished();
      return;
    }
  }
  currentCycle = currentCycle === 'Work' ? 'Rest' : 'Work';
  currentTime  = currentCycle === 'Work' ? workTime : restTime;
  updateDisplay(currentTime);
  startCountdown(currentTime);
  saveState();
}

function showFinished() {
  isRunning = false;
  clearInterval(countdownInterval);
  timerEl.textContent = 'Done! 🎉';
  timerEl.classList.add('finished');
  cycleEl.textContent = 'All done';
  cycleEl.className = 'cycle-badge work';
  cyclesLeftEl.textContent = 'Great work!';
  updateRing(0);
  playBtn.textContent = 'Restart';
  playBtn.classList.remove('running');
  skipBtn.disabled = true;
  resetBtn.disabled = false;
  document.title = 'Done! 🎉 BrightBot';
  clearState();
}

function play() {
  try { clickAudio.play(); } catch(e) {}

  if (isRunning) {
    // Pause
    clearInterval(countdownInterval);
    isRunning = false;
    playBtn.textContent = 'Resume';
    playBtn.classList.remove('running');
    saveState();
  } else {
    // Start / Resume
    isRunning = true;
    playBtn.textContent = 'Pause';
    playBtn.classList.add('running');
    skipBtn.disabled = false;
    startCountdown(currentTime);
    saveState();
  }
}

function skipCycle() {
  try { clickAudio.play(); } catch(e) {}
  clearInterval(countdownInterval);
  currentCycle = currentCycle === 'Work' ? 'Rest' : 'Work';
  if (currentCycle === 'Work') numberofCycles = Math.max(0, numberofCycles - 1);
  if (numberofCycles < 0) { showFinished(); return; }
  currentTime = currentCycle === 'Work' ? workTime : restTime;
  updateDisplay(currentTime);
  if (isRunning) startCountdown(currentTime);
  saveState();
}

function reset() {
  try { clickAudio.play(); } catch(e) {}
  clearInterval(countdownInterval);
  isRunning = false;
  currentCycle = 'Work';
  numberofCycles = totalCycles;
  currentTime = workTime;
  playBtn.textContent = 'Start';
  playBtn.classList.remove('running');
  skipBtn.disabled = false;
  resetBtn.disabled = false;
  updateDisplay(currentTime);
  clearState();
  document.title = 'Pomodoro Timer — BrightBot';
}

function applySettings() {
  const w = parseInt(workInput.value) || 25;
  const r = parseInt(restInput.value) || 5;
  const c = parseInt(intervalInput.value) || 4;
  workTime = w * 60 * 1000;
  restTime = r * 60 * 1000;
  totalCycles = c;
  reset();
}

playBtn.addEventListener('click', play);
skipBtn.addEventListener('click', skipCycle);
resetBtn.addEventListener('click', reset);
document.getElementById('applyBtn').addEventListener('click', applySettings);

(function restoreState() {
  const s = loadState();
  if (!s) {
    workInput.value = 25;
    restInput.value = 5;
    intervalInput.value = 4;
    updateDisplay(currentTime);
    return;
  }

  workTime      = s.workTime      || workTime;
  restTime      = s.restTime      || restTime;
  totalCycles   = s.totalCycles   || totalCycles;
  numberofCycles = s.numberofCycles != null ? s.numberofCycles : totalCycles;
  currentCycle  = s.currentCycle  || 'Work';
  isRunning     = s.isRunning     || false;

  workInput.value     = Math.round(workTime / 60000);
  restInput.value     = Math.round(restTime / 60000);
  intervalInput.value = totalCycles;

  if (isRunning && s.endTimestamp) {
    // Calculate how much time is left based on wall clock
    const msLeft = s.endTimestamp - Date.now();
    currentTime = Math.max(0, msLeft);
  } else {
    currentTime = s.currentTime || workTime;
  }

  updateDisplay(currentTime);

  if (isRunning && currentTime > 0) {
    playBtn.textContent = 'Pause';
    playBtn.classList.add('running');
    startCountdown(currentTime);
  } else if (!isRunning && s.endTimestamp !== null) {
    // Was running but now paused
    playBtn.textContent = 'Resume';
  } else {
    updateDisplay(currentTime);
  }
})();
