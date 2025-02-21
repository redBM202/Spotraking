<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Spotify API</title>
    <link rel="stylesheet" href="{{ asset('css/spotify.css') }}">
</head>
<body>
    <div class="error-container">
        <h1>Oops! Something went wrong</h1>
        <p class="error-message">{{ $message }}</p>
        @if(str_contains($message, 'log in again'))
            <a href="{{ route('spotify.login') }}" class="back-link">Log in to Spotify</a>
        @else
            <a href="{{ url()->previous() }}" class="back-link">Try Again</a>
        @endif
    </div>
</body>
</html>
