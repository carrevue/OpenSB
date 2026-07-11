if (typeof playerConfig === 'undefined') {
    playerConfig = {
        "url": "https://squarebracket.pw/dynamic/videos/V05kmlJpDzC.converted.mp4",
    };
}

document.addEventListener("DOMContentLoaded", () => {
    console.debug("Video player");
    let wrapper = document.getElementById('video-wrapper');
    fetchWithRetry(`/html5_player_template`, {
        method: "GET",
        headers: {
            "Content-type": "text/html; charset=UTF-8"
        },
        cache: "force-cache"
    })
        .then(response => response.text())
        .then(html => {
            wrapper.insertAdjacentHTML('afterbegin', html);
            createVideoElement();
        });
});

function createVideoElement() {
    let container = document.querySelector('.video-container');
    let video = document.createElement('video');
    video.setAttribute('x-webkit-airplay', 'allow'); // allow airplay
    video.className = 'video-stream main-video';
    video.src = playerConfig.url;
    container.appendChild(video);

    let playButton = document.querySelector('.player-button-play');
    playButton.addEventListener('click', () => {
        if (video.paused) {
            video.play();
        } else {
            video.pause();
        }
    });

    video.addEventListener('play', () => {
        playButton.classList.remove('player-button-play');
        playButton.classList.add('player-button-pause');
    });

    video.addEventListener('pause', () => {
        playButton.classList.remove('player-button-pause');
        playButton.classList.add('player-button-play');
    });
}