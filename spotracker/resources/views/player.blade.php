<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Spotify Player</title>
    <link rel="stylesheet" href="{{ asset('css/spotify.css') }}">
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
