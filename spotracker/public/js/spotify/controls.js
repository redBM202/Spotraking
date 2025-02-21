const spotifyControls = {
    doSpotifyAction(action) {
        const deviceId = elements.deviceSelector.value;
        if (!deviceId) {
            alert('Please select a playback device first');
            return;
        }

        // Disable all buttons during action
        const buttons = document.querySelectorAll('.control-button');
        buttons.forEach(btn => btn.disabled = true);

        fetch(`/spotify/${action}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': elements.csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ device_id: deviceId })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            console.log('Action response:', data);
            location.reload();
        })
        .catch(error => {
            console.error('Control error:', error);
            alert('Failed to control playback. Please try again.');
        })
        .finally(() => {
            buttons.forEach(btn => btn.disabled = false);
        });
    },

    playTrack(uri) {
        const deviceId = elements.deviceSelector.value;
        if (!deviceId) {
            alert('Please select a playback device first');
            return;
        }
        
        const button = event.target;
        button.disabled = true;
        
        fetch('/spotify/play', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': elements.csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                uri: uri,
                device_id: deviceId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(data.message || 'Failed to start playback');
            }
        })
        .catch(error => {
            console.error('Playback error:', error);
            alert(error.message || 'Failed to start playback. Please try again.');
            deviceManager.loadDevices();
        })
        .finally(() => {
            button.disabled = false;
        });
    }
};
