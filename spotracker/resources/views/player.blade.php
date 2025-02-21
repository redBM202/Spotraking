<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Spotify Player</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: white;
            margin: 0;
            padding: 20px;
        }
        #player-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #282828;
            border-radius: 8px;
            padding: 20px;
        }
        .player-controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }
        .control-button {
            background-color: #1DB954;
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 16px;
        }
        .control-button:hover {
            background-color: #1ed760;
        }
        .track-info {
            text-align: center;
            margin: 20px 0;
        }
        .track-name {
            color: #1DB954;
            font-size: 24px;
            margin: 10px 0;
        }
        .artist-name {
            color: #b3b3b3;
            font-size: 18px;
        }
        .progress-bar {
            width: 100%;
            height: 4px;
            background-color: #404040;
            border-radius: 2px;
            margin: 20px 0;
            position: relative;
        }
        .progress {
            height: 100%;
            background-color: #1DB954;
            border-radius: 2px;
            transition: width 0.1s linear;
        }
        .nav-buttons {
            margin-bottom: 20px;
        }
        .back-button {
            background-color: #404040;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .embedded-player {
            width: 100%;
            height: 352px;
            border: none;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div id="player-container">
        <div class="nav-buttons">
            <a href="{{ url()->previous() }}" class="back-button">← Back</a>
        </div>

        @if(isset($currentTrack))
            <iframe class="embedded-player"
                src="https://open.spotify.com/embed/track/{{ $currentTrack->item->id }}"
                frameborder="0"
                allowtransparency="true"
                allow="encrypted-media">
            </iframe>
            <div class="player-controls">
                <button onclick="controlPlayback('previous')" class="control-button">⏮ Previous</button>
                <button onclick="controlPlayback('next')" class="control-button">Next ⏭</button>
            </div>
        @else
            <p>No track currently playing</p>
        @endif
    </div>

    <script>
        function controlPlayback(action) {
            fetch(`/spotify/${action}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            }).then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }
    </script>
</body>
</html>
