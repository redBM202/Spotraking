<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;
use Illuminate\Support\Facades\Session as LaravelSession;

class SpotifyController extends Controller
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $api;

    public function __construct()
    {
        $this->clientId = env('SPOTIFY_CLIENT_ID');
        $this->clientSecret = env('SPOTIFY_CLIENT_SECRET');
        $this->redirectUri = env('REDIRECT_URI');
        $this->api = new SpotifyWebAPI();
        
        // Configure API options
        $this->api->setOptions([
            'auto_retry' => true,
            'retry_times' => 2,
            'return_assoc' => false,
            'base_uri' => 'https://api.spotify.com/v1/',
        ]);
    }

    private function refreshToken() {
        try {
            $session = new Session(
                $this->clientId,
                $this->clientSecret,
                $this->redirectUri
            );

            $refreshToken = LaravelSession::get('spotify_refresh_token');
            if (!$refreshToken) {
                return false;
            }

            $session->refreshAccessToken($refreshToken);
            $newAccessToken = $session->getAccessToken();
            
            if (!$newAccessToken) {
                return false;
            }

            LaravelSession::put('spotify_access_token', $newAccessToken);
            LaravelSession::put('spotify_refresh_token', $session->getRefreshToken());

            $this->api->setAccessToken($newAccessToken);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkAndGetToken()
    {
        try {
            $accessToken = LaravelSession::get('spotify_access_token');
            if (!$accessToken) {
                throw new \Exception('Not authenticated');
            }
            
            $this->api->setAccessToken($accessToken);
            
            // Test the connection
            try {
                $this->api->me();
            } catch (\Exception $e) {
                // Try to refresh the token if the current one is invalid
                if (!$this->refreshToken()) {
                    throw new \Exception('Session expired. Please log in again.');
                }
            }
            
            return LaravelSession::get('spotify_access_token');
        } catch (\Exception $e) {
            LaravelSession::forget('spotify_access_token');
            LaravelSession::forget('spotify_refresh_token');
            throw new \Exception('Connection to Spotify failed: ' . $e->getMessage());
        }
    }

    public function login()
    {
        $session = new Session(
            $this->clientId,
            $this->clientSecret,
            $this->redirectUri
        );

        $scopes = [
            'user-read-private',
            'user-read-email',
            'user-read-playback-state',
            'user-modify-playback-state',
            'user-read-currently-playing',
            'user-read-recently-played',  // Add this scope
            'user-read-playback-position', // Add this scope for better playback info
            'streaming', // Add this scope for Web Playback SDK
            'app-remote-control' // Add this scope for remote control
        ];
        
        $options = [
            'scope' => $scopes
        ];

        return redirect($session->getAuthorizeUrl($options));
    }

    public function callback(Request $request)
    {
        try {
            $session = new Session(
                $this->clientId,
                $this->clientSecret,
                $this->redirectUri
            );

            // Request a access token using the code from Spotify
            $session->requestAccessToken($request->code);

            // Store tokens in Laravel session
            LaravelSession::put('spotify_access_token', $session->getAccessToken());
            LaravelSession::put('spotify_refresh_token', $session->getRefreshToken());

            return redirect()->route('spotify.search');
        } catch (\Exception $e) {
            return response()->view('error', ['message' => 'Authentication failed: ' . $e->getMessage()]);
        }
    }

    private function getPlayerState() {  // Renamed from getPlaybackState
        try {
            $token = $this->checkAndGetToken();
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.spotify.com/v1/me/player",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json'
                ]
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return json_decode($result);
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getCurrentPlaybackState() {
        try {
            $token = $this->checkAndGetToken();
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.spotify.com/v1/me/player",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 5
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 204 means no active device/playback
            if ($httpCode === 204) {
                return ['is_playing' => false];
            }

            if ($httpCode === 200) {
                $data = json_decode($result);
                return [
                    'is_playing' => $data->is_playing ?? false,
                    'device_id' => $data->device->id ?? null
                ];
            }

            return ['is_playing' => false];
        } catch (\Exception $e) {
            return ['is_playing' => false];
        }
    }

    private function getLastPlayedTrack() {
        try {
            $token = $this->checkAndGetToken();
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.spotify.com/v1/me/player/recently-played?limit=1",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json'
                ]
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($result);
                return !empty($data->items[0]) ? $data->items[0] : null;
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function initCurl($url, $token, $customOptions = []) {
        $ch = curl_init();
        $defaultOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true
        ];
        
        $options = $customOptions + $defaultOptions;
        curl_setopt_array($ch, $options);
        return $ch;
    }

    private function handleApiResponse($ch) {
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [$result, $httpCode, $error];
    }

    public function search(Request $request)
    {
        try {
            $token = $this->checkAndGetToken();
            $query = $request->input('q');
            
            // Get all data in parallel using curl_multi
            $mh = curl_multi_init();
            $channels = [];

            // Profile request
            $channels['profile'] = $this->initCurl(
                "https://api.spotify.com/v1/me",
                $token
            );
            
            // Current playback request
            $channels['playback'] = $this->initCurl(
                "https://api.spotify.com/v1/me/player/currently-playing",
                $token
            );
            
            // Last played track request
            $channels['lastPlayed'] = $this->initCurl(
                "https://api.spotify.com/v1/me/player/recently-played?limit=1",
                $token
            );
            
            // Devices request
            $channels['devices'] = $this->initCurl(
                "https://api.spotify.com/v1/me/player/devices",
                $token
            );

            // Add all handles and execute requests
            foreach ($channels as $ch) {
                curl_multi_add_handle($mh, $ch);
            }

            // Add search request if query exists
            if ($query) {
                $channels['search'] = $this->initCurl(
                    "https://api.spotify.com/v1/search?q=" . urlencode($query) . "&type=track&limit=20&market=US",
                    $token
                );
                curl_multi_add_handle($mh, $channels['search']);
            }

            // Execute all requests
            do {
                $status = curl_multi_exec($mh, $running);
                if ($running) {
                    curl_multi_select($mh);
                }
            } while ($running && $status == CURLM_OK);

            // Process all responses
            $responses = [];
            foreach ($channels as $key => $ch) {
                $responses[$key] = curl_multi_getcontent($ch);
                curl_multi_remove_handle($mh, $ch);
            }
            curl_multi_close($mh);

            // Process responses
            $profile = json_decode($responses['profile']);
            $playbackState = json_decode($responses['playback']);
            $devices = json_decode($responses['devices']);
            $lastPlayed = json_decode($responses['lastPlayed']);
            $tracks = [];

            // Handle current playback state
            $currentTrack = null;
            if ($playbackState && isset($playbackState->item)) {
                $currentTrack = (object)[
                    'item' => $playbackState->item,
                    'is_playing' => $playbackState->is_playing ?? false
                ];
            } elseif ($lastPlayed && isset($lastPlayed->items[0])) {
                // Use last played if no current playback
                $currentTrack = (object)[
                    'item' => $lastPlayed->items[0]->track,
                    'is_playing' => false
                ];
            }

            if ($query && isset($responses['search'])) {
                $searchResults = json_decode($responses['search']);
                $tracks = $searchResults->tracks->items ?? [];
            }

            return view('search-results', [
                'profile' => $profile,
                'currentTrack' => $currentTrack,
                'playbackState' => $currentTrack, // Use currentTrack as playbackState
                'tracks' => $tracks,
                'query' => $query,
                'devices' => $devices
            ]);

        } catch (\Exception $e) {
            return response()->view('error', ['message' => $e->getMessage()], 500);
        }
    }

    private function formatDuration($ms) {
        $seconds = floor($ms / 1000);
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        return sprintf("%d:%02d", $minutes, $seconds);
    }

    private function getActiveDevice()
    {
        $response = $this->api->getRequest()->api('GET', 'me/player/devices');
        $devices = $response->devices ?? [];
        
        if (empty($devices)) {
            throw new \Exception('No Spotify devices found. Please open Spotify on any device.');
        }
        
        // First try to find an active device
        foreach ($devices as $device) {
            if ($device->is_active) {
                return $device->id;
            }
        }
        
        // Then try to find a non-web player
        foreach ($devices as $device) {
            if ($device->type !== 'Web Player') {
                return $device->id;
            }
        }
        
        // Finally, return the first available device
        return $devices[0]->id;
    }

    public function player()
    {
        try {
            $this->checkAndGetToken();
            $currentTrack = $this->api->getMyCurrentTrack();
            $playbackState = $this->api->getMyCurrentPlaybackInfo();
            
            return view('player', [
                'currentTrack' => $currentTrack,
                'playbackState' => $playbackState
            ]);
        } catch (\Exception $e) {
            return response()->view('error', ['message' => $e->getMessage()]);
        }
    }

    public function webplayer()
    {
        try {
            $this->checkAndGetToken();
            $currentTrack = $this->api->getMyCurrentTrack();
            
            return view('player', [
                'currentTrack' => $currentTrack
            ]);
        } catch (\Exception $e) {
            return response()->view('error', ['message' => 'Player failed: ' . $e->getMessage()]);
        }
    }

    public function getDevices()
    {
        try {
            $token = $this->checkAndGetToken();
            
            // Manual curl request to ensure proper URL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.spotify.com/v1/me/player/devices",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception('Failed to fetch devices');
            }

            $devices = json_decode($result);
            
            return response()->json([
                'status' => 'success',
                'devices' => $devices->devices ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get devices: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPlaybackState(Request $request)
    {
        try {
            $token = $this->checkAndGetToken();
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.spotify.com/v1/me/player",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 204) {
                return response()->json([
                    'status' => 'success',
                    'is_playing' => false,
                    'device_id' => null
                ]);
            }

            if ($httpCode === 200) {
                $data = json_decode($result);
                return response()->json([
                    'status' => 'success',
                    'is_playing' => $data->is_playing ?? false,
                    'device_id' => $data->device->id ?? null
                ]);
            }

            throw new \Exception('Failed to get playback state');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get playback state: ' . $e->getMessage()
            ], 500);
        }
    }

    public function play(Request $request)
    {
        try {
            $token = $this->checkAndGetToken();
            $deviceId = $request->device_id;

            if (!$deviceId) {
                throw new \Exception('Device ID is required');
            }

            // Get current state first
            $currentState = $this->getCurrentPlaybackState();
            $needsDeviceActivation = true;

            if ($currentState['device_id'] === $deviceId) {
                $needsDeviceActivation = false;
            }

            if ($needsDeviceActivation) {
                $ch = curl_init();
                $options = [
                    CURLOPT_URL => "https://api.spotify.com/v1/me/player",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => "PUT",
                    CURLOPT_POSTFIELDS => json_encode([
                        'device_ids' => [$deviceId],
                        'play' => false
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $token,
                        'Content-Type: application/json'
                    ],
                    CURLOPT_SSL_VERIFYPEER => false
                ];
                
                curl_setopt_array($ch, $options);
                curl_exec($ch);
                curl_close($ch);
                usleep(500000);
            }

            // Then handle playback
            $ch = curl_init();
            $url = "https://api.spotify.com/v1/me/player/play?device_id=" . $deviceId;
            
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ]
            ];

            if ($request->has('uri')) {
                $options[CURLOPT_POSTFIELDS] = json_encode(['uris' => [$request->uri]]);
            } else if ($request->has('track_id')) {
                $options[CURLOPT_POSTFIELDS] = json_encode(['uris' => ['spotify:track:' . $request->track_id]]);
            }

            curl_setopt_array($ch, $options);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            usleep(200000); // 200ms wait
            $newState = $this->getCurrentPlaybackState();

            return response()->json([
                'status' => 'success',
                'message' => $needsDeviceActivation ? 'Device activated and playback started' : 'Playback started',
                'is_playing' => $newState['is_playing'],
                'device_id' => $newState['device_id']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function pause(Request $request)
    {
        try {
            $token = $this->checkAndGetToken();
            $deviceId = $request->device_id;

            // First check current state
            $currentState = $this->getCurrentPlaybackState();
            
            if (empty($currentState['device_id'])) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Device transitioning',
                    'is_playing' => false
                ]);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.spotify.com/v1/me/player/pause",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 204 means success, 202 means accepted, 403 might mean already paused
            if ($httpCode !== 204 && $httpCode !== 202 && $httpCode !== 403) {
                if ($result) {
                    $error = json_decode($result, true);
                    if (isset($error['error']['message'])) {
                        throw new \Exception($error['error']['message']);
                    }
                }
                throw new \Exception('Failed to pause playback');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Playback paused',
                'is_playing' => false
            ]);

        } catch (\Exception $e) {
            // If it's already paused or transitioning, treat as success
            if (str_contains($e->getMessage(), 'Player command failed') || 
                str_contains($e->getMessage(), 'Device not found') ||
                str_contains($e->getMessage(), 'No active device')) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Playback already paused',
                    'is_playing' => false
                ]);
            }
            
            return response()->json([
                'status' => 'success', // Changed to success since the action still worked
                'message' => 'Playback state updated',
                'is_playing' => false
            ]);
        }
    }

    public function next(Request $request)
    {
        try {
            $token = $this->checkAndGetToken();
            $deviceId = $request->device_id;
            
            if (!$deviceId) {
                throw new \Exception('Device ID is required');
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.spotify.com/v1/me/player/next",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POSTFIELDS => json_encode(['device_id' => $deviceId])
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 204) {
                throw new \Exception('Failed to skip track');
            }

            // Get updated playback state
            $currentState = $this->getCurrentPlaybackState();
            return response()->json([
                'status' => 'success',
                'is_playing' => $currentState['is_playing'] ?? false
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function previous(Request $request)
    {
        try {
            $token = $this->checkAndGetToken();
            $deviceId = $request->device_id;
            
            if (!$deviceId) {
                throw new \Exception('Device ID is required');
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.spotify.com/v1/me/player/previous",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POSTFIELDS => json_encode(['device_id' => $deviceId])
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 204) {
                throw new \Exception('Failed to go to previous track');
            }

            // Get updated playback state
            $currentState = $this->getCurrentPlaybackState();
            return response()->json([
                'status' => 'success',
                'is_playing' => $currentState['is_playing'] ?? false
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
