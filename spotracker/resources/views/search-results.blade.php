<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spotify Search - {{ $query ?? 'Home' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/spotify.css') }}">
    <script src="{{ asset('js/spotify/controls.js') }}" defer></script>
    <script src="{{ asset('js/spotify/devices.js') }}" defer></script>
    <script src="{{ asset('js/spotify/state.js') }}" defer></script>
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
                        @if(isset($devices->devices) && count($devices->devices) > 0)
                            @foreach($devices->devices as $device)
                                <option value="{{ $device->id }}" {{ $device->is_active ? 'selected' : '' }}>
                                    {{ $device->name }} ({{ $device->type }}){{ $device->is_active ? ' - Active' : '' }}
                                </option>
                            @endforeach
                        @else
                            <option value="">No devices found - Open Spotify on any device</option>
                        @endif
                    </select>
                    <div class="device-info">
                        <span>Current device:</span>
                        <span id="activeDevice">
                            @if(isset($devices->devices))
                                @foreach($devices->devices as $device)
                                    @if($device->is_active)
                                        {{ $device->name }} ({{ $device->type }})
                                    @endif
                                @endforeach
                            @else
                                None selected
                            @endif
                        </span>
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
                            <button class="control-button" id="prevButton" onclick="spotifyControls.doSpotifyAction('previous')">Previous ⏮</button>
                            <button class="control-button" id="playPauseButton" onclick="spotifyControls.doSpotifyAction('{{ $playbackState->is_playing ? 'pause' : 'play' }}')">
                                {{ $playbackState->is_playing ? '⏸ Pause' : '▶ Play' }}
                            </button>
                            <button class="control-button" id="nextButton" onclick="spotifyControls.doSpotifyAction('next')">Next ⏭</button>
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
        const elements = {
            deviceSelector: document.getElementById('deviceSelector'),
            activeDevice: document.getElementById('activeDevice'),
            playPauseButton: document.getElementById('playPauseButton'),
            countdown: document.getElementById('countdown'),
            csrfToken: document.querySelector('meta[name="csrf-token"]').content
        };

        // Initialize everything
        document.addEventListener('DOMContentLoaded', () => {
            deviceManager.loadDevices();
            setInterval(() => deviceManager.loadDevices(), 15000);
            
            if (document.visibilityState === 'visible') {
                requestAnimationFrame(function tick() {
                    if (document.visibilityState === 'visible') {
                        stateManager.refreshPlaybackState();
                        requestAnimationFrame(tick);
                    }
                });
            }
        });
    </script>
</body>
</html>
