const deviceManager = {
    loadDevices() {
        fetch('/spotify/devices', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': elements.csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && Array.isArray(data.devices)) {
                this.updateDeviceSelector(data.devices);
            } else {
                throw new Error(data.message || 'Failed to load devices');
            }
        })
        .catch(error => {
            console.error('Error loading devices:', error);
            const selector = elements.deviceSelector;
            selector.innerHTML = '<option value="">Error loading devices - Please refresh</option>';
            setTimeout(() => this.loadDevices(), 5000);
        });
    },

    updateDevice(event) {
        event.preventDefault();
        const deviceId = event.target.value;
        
        if (!deviceId) {
            alert('Please select a valid device');
            return;
        }

        const buttons = document.querySelectorAll('.control-button, .play-now-button');
        buttons.forEach(btn => btn.disabled = true);

        const selectedOption = event.target.options[event.target.selectedIndex];
        elements.activeDevice.textContent = 'Switching to ' + selectedOption.text;

        fetch('/spotify/play', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': elements.csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ device_id: deviceId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                elements.activeDevice.textContent = selectedOption.text;
                if (elements.playPauseButton) {
                    elements.playPauseButton.textContent = data.is_playing ? '⏸ Pause' : '▶ Play';
                }
                this.loadDevices();
            } else {
                throw new Error(data.message || 'Failed to switch device');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.loadDevices();
        })
        .finally(() => {
            buttons.forEach(btn => btn.disabled = false);
        });
    },

    updateDeviceSelector(devices) {
        // ... existing updateDeviceSelector code ...
    }
};
