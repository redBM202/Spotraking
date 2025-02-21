<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spotify Search - {{ $query ?? 'Home' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/spotify.css') }}">
</head>
<body>
    <div class="refresh-timer">Refreshing in <span id="countdown">5</span>s</div>
    <div class="search-container">
        <div class="nav-buttons">
            <a href="{{ url()->previous() }}" class="back-button">← Back</a>
        </div>
        @if(isset($profile))
            <div class="user-profile">
                @if(!empty($profile->images))
                    <img class="profile-image" src="{{ $profile->images[0]->url }}" alt="Profile">
                @endif
                <h2>Welcome, {{ $profile->display_name }}</h2>
                
                <div class="device-controls">
                    <div class="device-label">Select Playback Device:</div>
                    <select id="deviceSelector" class="device-selector" onchange="updateDevice(event, true)">
                        <option value="">Loading devices...</option>
                    </select>
                    <div class="device-info">
                        <span>Current device:</span>
                        <span id="activeDevice">None selected</span>
                    </div>
                </div>

                @if(isset($currentTrack) && isset($currentTrack->item))
                    <div class="now-playing">
                        <h3>{{ isset($playbackState->is_playing) && $playbackState->is_playing ? 'Currently Playing:' : 'Last Played:' }}</h3>
                        <iframe class="embedded-player"
                            src="https://open.spotify.com/embed/track/{{ $currentTrack->item->id }}?utm_source=generator&hideControls=1&showPlayButton=0"
                            frameborder="0"
                            allowtransparency="true"
                            allow="encrypted-media">
                        </iframe>
                        <div class="player-controls">
                            <button onclick="controlPlayback('previous')" class="control-button" id="prevButton">⏮ Previous</button>
                            <button onclick="togglePlayback('{{ $currentTrack->item->id }}')" class="control-button" id="playPauseButton">
                                {{ isset($playbackState->is_playing) && $playbackState->is_playing ? '⏸ Pause' : '▶ Resume' }}
                            </button>
                            <button onclick="controlPlayback('next')" class="control-button" id="nextButton">Next ⏭</button>
                        </div>
                    </div>
                @else
                    <div class="now-playing">
                        <h3>Not Currently Playing</h3>
                        <p>Select a device and play a track to begin</p>
                    </div>
                @endif
            </div>
        @endif

        <form method="GET" action="{{ route('spotify.search') }}" class="search-form">
            <input type="text" name="q" value="{{ $query ?? '' }}" 
                   placeholder="Search for tracks..." class="search-input">
            <button type="submit" class="play-button">Search</button>
        </form>

        @if(!empty($query))
            <h1>Search Results for "{{ $query }}"</h1>
            <div class="track-grid">
                @foreach($tracks as $track)
                    <div class="track-card">
                        @if(!empty($track->album->images))
                            <img class="track-image" src="{{ $track->album->images[0]->url }}" alt="{{ $track->name }}">
                        @endif
                        <h2 class="track-title">{{ $track->name }}</h2>
                        <p class="track-artist">
                            {{ collect($track->artists)->pluck('name')->implode(', ') }}
                        </p>
                        <p>Album: {{ $track->album->name }}</p>
                        <div class="popularity-text">
                            <span>Played on Spotify:</span>
                            <span class="popularity-star">
                                @if($track->popularity > 90)
                                    100M+ plays 🔥
                                @elseif($track->popularity > 75)
                                    50M+ plays ⭐
                                @elseif($track->popularity > 50)
                                    10M+ plays 👌
                                @elseif($track->popularity > 25)
                                    1M+ plays 📈
                                @else
                                    <100K plays 💎
                                @endif
                            </span>
                        </div>
                        <div class="popularity-meter">
                            <div class="popularity-fill" style="width: {{ $track->popularity ?? 0 }}%"></div>
                        </div>
                        <div class="play-actions">
                            <button onclick="playTrack('{{ $track->uri }}')" class="play-now-button">
                                Play Now ▶
                            </button>
                            <a href="{{ $track->external_urls->spotify }}" target="_blank" class="play-button">
                                Open in Spotify
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        function handleApiError(error) {
            console.error('Spotify API Error:', error);
            if (error.message.includes('Could not resolve host') || 
                error.message.includes('Failed to fetch')) {
                // Reload the page if it's a connection error
                alert('Lost connection to Spotify. Trying to reconnect...');
                setTimeout(() => location.reload(), 1000);
            } else if (error.message.includes('authenticated')) {
                // Redirect to login if authentication failed
                window.location.href = '{{ route("spotify.login") }}';
            } else {
                alert(error.message || 'An error occurred. Please try again.');
            }
        }

        function loadDevices() {
            fetch('/spotify/devices', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && Array.isArray(data.devices)) {
                    updateDeviceSelector(data.devices);
                } else {
                    throw new Error(data.message || 'Failed to load devices');
                }
            })
            .catch(error => {
                console.error('Error loading devices:', error);
                const selector = document.getElementById('deviceSelector');
                selector.innerHTML = '<option value="">Error loading devices - Please refresh</option>';
                setTimeout(loadDevices, 5000); // Retry after 5 seconds
            });
        }

        function updateDeviceSelector(devices) {
            const selector = document.getElementById('deviceSelector');
            const currentDevice = selector.value;
            selector.innerHTML = '';
            
            if (devices.length === 0) {
                selector.innerHTML = '<option value="">No devices found - Open Spotify on any device</option>';
                document.getElementById('activeDevice').textContent = 'No devices available';
                return;
            }

            // Sort devices: Active first, then non-web players, then others
            const sortedDevices = devices.sort((a, b) => {
                if (a.is_active && !b.is_active) return -1;
                if (!a.is_active && b.is_active) return 1;
                if (a.type !== 'Web Player' && b.type === 'Web Player') return -1;
                if (a.type === 'Web Player' && b.type !== 'Web Player') return 1;
                return 0;
            });

            sortedDevices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.id;
                option.text = `${device.name} (${device.type})${device.is_active ? ' - Active' : ''}`;
                option.selected = device.is_active || device.id === currentDevice;
                selector.appendChild(option);

                if (device.is_active) {
                    document.getElementById('activeDevice').textContent = 
                        `${device.name} (${device.type})`;
                }
            });
        }

        function updateDevice(event) {
            event.preventDefault();
            const deviceId = event.target.value;
            
            if (!deviceId) {
                alert('Please select a valid device');
                return;
            }

            const buttons = document.querySelectorAll('.control-button, .play-now-button');
            buttons.forEach(btn => btn.disabled = true);

            // Show loading state
            const selectedOption = event.target.options[event.target.selectedIndex];
            document.getElementById('activeDevice').textContent = 'Switching to ' + selectedOption.text;

            fetch('/spotify/play', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ device_id: deviceId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('activeDevice').textContent = selectedOption.text;
                    // Don't reload, just update UI
                    const playPauseButton = document.getElementById('playPauseButton');
                    if (playPauseButton) {
                        playPauseButton.textContent = data.is_playing ? '⏸ Pause' : '▶ Play';
                    }
                    loadDevices();
                } else {
                    throw new Error(data.message || 'Failed to switch device');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadDevices(); // Refresh device list
            })
            .finally(() => {
                buttons.forEach(btn => btn.disabled = false);
            });
        }

        // Update playTrack function
        function playTrack(uri) {
            const deviceId = document.getElementById('deviceSelector').value;
            if (!deviceId) {
                alert('Please select a playback device first');
                return;
            }
            
            const button = event.target;
            button.disabled = true;
            
            fetch('/spotify/play', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    uri: uri,
                    device_id: deviceId
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Failed to start playback');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Wait a bit longer for Spotify to update
                    setTimeout(() => location.reload(), 2500);
                } else {
                    throw new Error(data.message || 'Failed to start playback');
                }
            })
            .catch(error => {
                console.error('Playback error:', error);
                alert(error.message || 'Failed to start playback. Please try again.');
                // Refresh device list in case of issues
                loadDevices();
            })
            .finally(() => {
                button.disabled = false;
            });
        }

        function togglePlayback(trackId = null) {
            const deviceId = document.getElementById('deviceSelector').value;
            if (!deviceId) {
                alert('Please select a playback device first');
                return;
            }

            const button = document.getElementById('playPauseButton');
            button.disabled = true;
            const isPlaying = button.textContent.includes('Pause');
            const action = isPlaying ? 'pause' : 'play';

            const data = { device_id: deviceId };
            if (!isPlaying && trackId) {
                data.track_id = trackId;
            }

            fetch(`/spotify/${action}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                // Accept both success and error responses as potentially valid
                if (data.status === 'success' || data.is_playing === false) {
                    // Update button state immediately
                    button.textContent = isPlaying ? '▶ Play' : '⏸ Pause';
                    
                    // Refresh state after a delay
                    setTimeout(() => {
                        refreshPlaybackState();
                        loadDevices();
                    }, 1000);
                } else {
                    throw new Error(data.message || 'Failed to control playback');
                }
            })
            .catch(error => {
                console.warn('Playback state change:', error);
                // Still update the UI since the action probably worked
                button.textContent = isPlaying ? '▶ Play' : '⏸ Pause';
                setTimeout(() => {
                    refreshPlaybackState();
                    loadDevices();
                }, 1000);
            })
            .finally(() => {
                button.disabled = false;
            });
        }

        function refreshPlaybackState() {
            const deviceId = document.getElementById('deviceSelector').value;
            if (!deviceId) return;

            fetch('/spotify/playback-state', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const playPauseButton = document.getElementById('playPauseButton');
                    if (playPauseButton) {
                        playPauseButton.textContent = data.is_playing ? '⏸ Pause' : '▶ Play';
                    }
                }
            })
            .catch(error => {
                console.warn('State refresh error:', error);
                // Silent fail - will retry on next interval
            });
        }

        let countdown = 15; // Longer interval
        const countdownElement = document.getElementById('countdown');
        
        function updateCountdown() {
            countdownElement.textContent = countdown;
            if (countdown <= 0) {
                if (document.visibilityState === 'visible') {
                    loadDevices(); // Only refresh devices, not the whole page
                    countdown = 15; // Reset countdown
                }
            } else {
                countdown--;
            }
        }

        function controlPlayback(action) {
            const deviceId = document.getElementById('deviceSelector').value;
            if (!deviceId) {
                alert('Please select a playback device first');
                return;
            }

            // Disable buttons during request
            const buttons = document.querySelectorAll('.control-button');
            buttons.forEach(btn => btn.disabled = true);

            fetch(`/spotify/${action}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ device_id: deviceId })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Add a small delay to let Spotify update
                    setTimeout(() => {
                        loadDevices(); // Refresh device list instead of full page reload
                    }, 500);
                } else {
                    throw new Error(data.message || 'Failed to control playback');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                handleApiError(error);
            })
            .finally(() => {
                buttons.forEach(btn => btn.disabled = false);
            });
        }

        // Add toggle playback function
        function togglePlayback() {
            const deviceId = document.getElementById('deviceSelector').value;
            if (!deviceId) {
                alert('Please select a playback device first');
                return;
            }

            const button = document.getElementById('playPauseButton');
            button.disabled = true;

            const isPlaying = button.textContent.includes('Pause');
            const action = isPlaying ? 'pause' : 'play';

            fetch(`/spotify/${action}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ device_id: deviceId })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    button.textContent = isPlaying ? '▶ Play' : '⏸ Pause';
                    // Don't reload the page, just update the button state
                } else {
                    throw new Error(data.message || 'Failed to control playback');
                }
            })
            .catch(handleApiError)
            .finally(() => {
                button.disabled = false;
            });
        }

        // Update device refresh logic
        function refreshCurrentPlayback() {
            if (document.visibilityState === 'visible') {
                loadDevices();
            }
        }

        // Initialize with longer intervals
        loadDevices();
        setInterval(refreshCurrentPlayback, 15000);

        // Handle errors that might occur during playback control
        window.onerror = function(msg, url, line) {
            console.error('Error: ' + msg + '\nURL: ' + url + '\nLine: ' + line);
            alert('An error occurred. Please try again.');
            return false;
        };

        // Update countdown every second
        setInterval(updateCountdown, 1000);

        // Start the countdown
        updateCountdown();

        // Reset countdown when page becomes visible
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                countdown = 15;
                updateCountdown();
            }
        });

        // Load devices when page loads
        loadDevices();
        // Refresh devices list periodically
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                loadDevices();
                refreshPlaybackState();
            }
        }, 15000); // Check devices every 15 seconds

        // Update the refresh interval
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                refreshPlaybackState();
                loadDevices();
            }
        }, 3000); // Check more frequently
    </script>
</body>
</html>
