const stateManager = {
    refreshPlaybackState: throttle(() => {
        if (!elements.deviceSelector.value || document.hidden) return;

        fetch('/spotify/playback-state', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': elements.csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && elements.playPauseButton) {
                elements.playPauseButton.textContent = data.is_playing ? '⏸ Pause' : '▶ Play';
            }
        })
        .catch(error => console.warn('State refresh error:', error));
    }, 1000)
};
