<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Spotify API</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .error-container {
            background-color: #282828;
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
        }
        .error-message {
            color: #ff5555;
            margin-bottom: 1rem;
        }
        .back-link {
            background-color: #1DB954;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            display: inline-block;
        }
    </style>
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
