let timerId = null;

function formatTime(seconds) {
    const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
    const secs = (seconds % 60).toString().padStart(2, '0');
    return `${mins}:${secs}`;
}


function startTimer(sequence,duree=0) {
//démarre le timer pour la durée si > 0 ou la durée restante
    let timerDisplay = document.getElementById('timer' + sequence);
    let startBtn = document.getElementById('startBtn' + sequence);
    let resetBtn = document.getElementById('resetBtn' + sequence);
 
    startBtn.disabled = true;
    resetBtn.disabled = false;
    timerDisplay.classList.remove('completed');
    if(duree>0){timeLeft = duree;}
    timerId = setInterval(() => {
        timeLeft--; //décrémentation 1 seconde
        timerDisplay.textContent = formatTime(timeLeft);

        if (timeLeft <= 0) {
            //suite
            clearInterval(timerId);
            timerId = null;
            timerDisplay.classList.add('completed', 'animate__animated', 'animate__bounce');
            startBtn.disabled = false;
            resetBtn.disabled = true;
            //coupure du métronome si demandé
            timerWorker.postMessage("stop");
        }
    }, 1000);
}

function resetTimer(sequence,duree) {
//stoppe le timer et remet l'affichage à la duréee
    var timerDisplay = document.getElementById('timer' + sequence);
    var startBtn = document.getElementById('startBtn' + sequence);
    let resetBtn = document.getElementById('resetBtn' + sequence);
    clearInterval(timerId); //annule action démarée par set interval
    timerId = null;
    timeLeft=duree;
    timerDisplay.textContent = formatTime(duree);
    timerDisplay.classList.remove('completed', 'animate__animated', 'animate__bounce');
    startBtn.disabled = false;
    resetBtn.disabled = true;
    
}

