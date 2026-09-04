<?php
// app/Http/Controllers/SocialConfigController.php

namespace App\Http\Controllers;

use App\Models\SocialConfig;
use App\Models\SocialPost;
use App\Models\UserMedia;
use App\Models\Log as LogModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SocialConfigController extends Controller
{
    // ============================================================
    // CONSTANTES TIKTOK
    // ============================================================
    private const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';
    private const REFRESH_URL = 'https://open.tiktokapis.com/v2/oauth/token/';
    private const USER_URL = 'https://open.tiktokapis.com/v2/user/info/';
    private const PUBLISH_STATUS_URL = 'https://open.tiktokapis.com/v2/post/publish/status/fetch/';
    private const PUBLISH_INIT_URL = 'https://open.tiktokapis.com/v2/post/publish/content/init/';
    private const CREATOR_INFO_URL = 'https://open.tiktokapis.com/v2/post/publish/creator_info/query/';

    // ============================================================
    // CONSTANTES INSTAGRAM
    // ============================================================
    private const INSTAGRAM_GRAPH_URL = 'https://graph.instagram.com/v19.0/';
    private const FACEBOOK_GRAPH_URL = 'https://graph.facebook.com/v19.0/';
    private const INSTAGRAM_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
    private const INSTAGRAM_AUTH_URL = 'https://api.instagram.com/oauth/authorize';

    // ============================================================
    // SECTION 1: TIKTOK - OAuth (Connexion)
    // ============================================================

    private function getCreatorInfo($config): array
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($config->access_token)
                ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
                ->timeout(30)
                ->post(self::CREATOR_INFO_URL);

            if (!$response->successful()) {
                Log::warning('TikTok creator info query failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            return $response->json('data', []);
        } catch (\Exception $e) {
            Log::error('Erreur getCreatorInfo', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function validatePrivacyLevel(array $creatorInfo, string $requestedPrivacy): string
    {
        $availableOptions = $creatorInfo['privacy_level_options'] ?? ['SELF_ONLY'];

        if (in_array($requestedPrivacy, $availableOptions)) {
            return $requestedPrivacy;
        }

        return $availableOptions[0] ?? 'SELF_ONLY';
    }

    /**
     * Récupérer la configuration TikTok de l'utilisateur
     */
    private function getUserTikTokConfig(int $userId): ?SocialConfig
    {
        return SocialConfig::where('user_id', $userId)
            ->where('platform', 'tiktok')
            ->first();
    }

    /**
     * Initier la connexion OAuth TikTok
     * GET /social/tiktok/connect
     */
    public function connectTikTok(Request $request)
    {
        $user = $request->user();

        // Récupérer la config TikTok de l'utilisateur
        $config = $this->getUserTikTokConfig($user->id);

        if (!$config || !$config->client_id || !$config->client_secret) {
            return response()->json([
                'success' => false,
                'error' => 'Veuillez d\'abord importer vos credentials TikTok.',
                'requires_import' => true,
            ], 400);
        }

        $state = 'tiktok_' . $user->id . '_' . time() . '_' . bin2hex(random_bytes(8));
        $codeVerifier = bin2hex(random_bytes(64));
        $codeChallenge = strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');

        cache()->put("tiktok_state_{$user->id}", $state, now()->addMinutes(15));
        cache()->put("tiktok_code_verifier_{$user->id}", $codeVerifier, now()->addMinutes(15));

        // Utiliser les credentials de l'utilisateur
        $clientKey = $config->client_id;
        $redirectUri = $config->redirect_uri ?? 'https://redacteur.benin-digital.app/api/social/tiktok/callback';

        $authUrl = 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key' => $clientKey,
            'response_type' => 'code',
            'scope' => 'user.info.basic,user.info.profile,user.info.stats,video.list,video.publish',
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return response()->json([
            'success' => true,
            'auth_url' => $authUrl,
            'state' => $state,
        ]);
    }

    /**
     * Callback OAuth TikTok (public)
     * GET /social/tiktok/callback
     */
    public function tiktokCallback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');

        $frontend = 'https://redacteur.benin-digital.app/manager/social/tiktok';

        if ($error) {
            return redirect($frontend . '?error=' . urlencode($request->get('error_description') ?? $error));
        }

        if (!$code || !$state) {
            return redirect($frontend . '?error=missing_code_or_state');
        }

        preg_match('/tiktok_(\d+)_/', $state, $matches);
        $userId = $matches[1] ?? null;

        if (!$userId) {
            return redirect($frontend . '?error=invalid_state');
        }

        $storedState = cache()->get("tiktok_state_{$userId}");
        if ($state !== $storedState) {
            Log::error('TikTok state mismatch', ['received' => $state, 'stored' => $storedState]);
            return redirect($frontend . '?error=state_mismatch');
        }
        cache()->forget("tiktok_state_{$userId}");

        $codeVerifier = cache()->get("tiktok_code_verifier_{$userId}");
        cache()->forget("tiktok_code_verifier_{$userId}");

        try {
            // Récupérer la config TikTok de l'utilisateur
            $config = SocialConfig::where('user_id', $userId)
                ->where('platform', 'tiktok')
                ->first();

            if (!$config) {
                throw new \Exception('Configuration TikTok non trouvée');
            }

            $clientKey = $config->client_id;
            $clientSecret = $config->client_secret;
            $redirectUri = $config->redirect_uri ?? 'https://redacteur.benin-digital.app/api/social/tiktok/callback';

            $tokenResponse = Http::withOptions(['verify' => false])
                ->asForm()
                ->post(self::TOKEN_URL, [
                    'client_key' => $clientKey,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                    'code_verifier' => $codeVerifier,
                ]);

            if (!$tokenResponse->successful()) {
                Log::error('TikTok token exchange failed', ['body' => $tokenResponse->body()]);
                return redirect($frontend . '?error=' . urlencode('Token exchange failed: ' . $tokenResponse->body()));
            }

            $tokenData = $tokenResponse->json();

            if (isset($tokenData['error'])) {
                return redirect($frontend . '?error=' . urlencode($tokenData['error_description'] ?? $tokenData['error']));
            }

            // Récupération des infos utilisateur
            $userInfo = $this->fetchTikTokUserInfo($tokenData['access_token']);

            // Mettre à jour la configuration avec les tokens
            $config->fill([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'open_id' => $tokenData['open_id'] ?? null,
                'account_id' => $tokenData['open_id'] ?? null,
                'account_name' => $userInfo['display_name'] ?? null,
                'account_username' => $userInfo['username'] ?? null,
                'profile_picture_url' => $userInfo['avatar_url'] ?? null,
                'is_connected' => true,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 86400),
                'metadata' => array_merge($config->metadata ?? [], [
                    'user_info' => $userInfo,
                    'connected_at' => now()->toIso8601String(),
                ]),
            ])->save();

            LogModel::create([
                'user_id' => $userId,
                'action' => 'tiktok_connected',
                'details' => 'Compte TikTok connecté: ' . ($userInfo['display_name'] ?? 'unknown'),
                'ip_address' => $request->ip(),
            ]);

            return redirect($frontend . '?success=connected');
        } catch (\Exception $e) {
            Log::error('TikTok callback exception', ['error' => $e->getMessage()]);
            return redirect($frontend . '?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Vérifier le statut de connexion TikTok
     * GET /social/tiktok/status
     */
    public function tiktokStatus(Request $request)
    {
        $config = SocialConfig::where('user_id', $request->user()->id)
            ->where('platform', 'tiktok')
            ->first();

        if (!$config || !$config->is_connected) {
            return response()->json(['is_connected' => false]);
        }

        $tokenValid = true;
        if ($config->token_expires_at?->isPast()) {
            $tokenValid = $this->refreshTikTokToken($config);
        }

        return response()->json([
            'is_connected' => $tokenValid,
            'account_name' => $config->account_name,
            'account_username' => $config->account_username,
            'account_id' => $config->account_id,
            'open_id' => $config->open_id,
            'profile_picture' => $config->profile_picture_url,
            'is_token_expired' => !$tokenValid,
            'expires_at' => $config->token_expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Déconnecter le compte TikTok - Supprime la ligne dans la table
     * POST /social/tiktok/disconnect
     */
    public function disconnectTikTok(Request $request)
    {
        $user = $request->user();

        // Supprimer complètement la ligne
        $deleted = SocialConfig::where('user_id', $user->id)
            ->where('platform', 'tiktok')
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune configuration TikTok trouvée pour cet utilisateur.',
            ], 404);
        }

        LogModel::create([
            'user_id' => $user->id,
            'action' => 'tiktok_disconnected',
            'details' => 'Compte TikTok déconnecté et configuration supprimée',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Compte TikTok déconnecté avec succès',
        ]);
    }

    // ============================================================
    // SECTION 2: TIKTOK - PUBLICATION (RÉELLE)
    // ============================================================

    /**
     * Publier une vidéo sur TikTok
     * POST /social/tiktok/publish
     */
    public function publishToTikTok(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media_id' => 'nullable|integer|exists:user_media,id',
            'video' => 'nullable|file|mimes:mp4,mov,avi|max:204800',
            'video_url' => 'nullable|url|max:500',
            'title' => 'nullable|string|max:150',
            'privacy' => 'nullable|string|in:PUBLIC_TO_EVERYONE,MUTUAL_FOLLOW_FRIENDS,FOLLOWER_OF_CREATOR,SELF_ONLY',
            'disable_comment' => 'nullable|boolean',
            'disable_duet' => 'nullable|boolean',
            'disable_stitch' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$request->hasFile('video') && !$request->media_id && !$request->video_url) {
            return response()->json([
                'success' => false,
                'error' => 'Veuillez fournir un fichier vidéo, un media_id ou une URL vidéo'
            ], 400);
        }

        $user = $request->user();
        $tempFile = null;

        $config = SocialConfig::where('user_id', $user->id)
            ->where('platform', 'tiktok')
            ->where('is_connected', true)
            ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'error' => 'Compte TikTok non connecté',
                'requires_connect' => true,
            ], 400);
        }

        if ($config->token_expires_at?->isPast()) {
            $refreshed = $this->refreshTikTokToken($config);
            if (!$refreshed) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token TikTok expiré. Reconnectez votre compte.',
                ], 401);
            }
            $config->refresh();
        }

        $creatorInfo = $this->getCreatorInfo($config);
        $requestedPrivacy = $request->privacy ?? 'SELF_ONLY';
        $validPrivacy = $this->validatePrivacyLevel($creatorInfo, $requestedPrivacy);

        try {
            $title = substr($request->title ?? 'Nouvelle vidéo', 0, 150);
            $privacy = $validPrivacy;

            $result = null;
            $media = null;

            if ($request->video_url) {
                Log::info('Publication avec URL directe (PULL_FROM_URL)', ['url' => $request->video_url]);
                $result = $this->publishFromUrlTikTok($config, $request->video_url, $title, $privacy, $request);
            } elseif ($request->hasFile('video')) {
                $file = $request->file('video');
                $tempFile = $file->store('temp_videos', 'public');
                $filePath = storage_path('app/public/' . $tempFile);

                Log::info('Upload vidéo depuis fichier direct', [
                    'path' => $filePath,
                    'size' => $file->getSize(),
                ]);

                $result = $this->publishFromFileTikTok($config, $filePath, $title, $privacy, $request);

                if ($tempFile) {
                    @unlink($filePath);
                }
            } elseif ($request->media_id) {
                $media = UserMedia::where('user_id', $user->id)
                    ->where('id', $request->media_id)
                    ->firstOrFail();

                if ($media->type === 'image') {
                    // TikTok exige PULL_FROM_URL pour les photos, jamais de fichier local
                    if (!$media->file_url) {
                        throw new \Exception('URL publique introuvable pour cette image.');
                    }

                    Log::info('Publication image TikTok depuis média existant', [
                        'url' => $media->file_url,
                        'media_id' => $media->id
                    ]);

                    $result = $this->publishPhotoTikTok($config, [$media->file_url], $title, $title, $privacy, $request);
                } else {
                    $filePath = null;
                    if ($media->file_path) {
                        $filePath = storage_path('app/public/' . $media->file_path);
                    }

                    if ($filePath && file_exists($filePath)) {
                        Log::info('Upload vidéo depuis média existant (fichier local)', [
                            'path' => $filePath,
                            'size' => filesize($filePath),
                            'media_id' => $media->id
                        ]);

                        $this->ensureVideoMetadata($media, $filePath);
                        $result = $this->publishFromFileTikTok($config, $filePath, $title, $privacy, $request);
                    } elseif ($media->file_url) {
                        Log::info('Upload vidéo depuis média existant (URL)', [
                            'url' => $media->file_url,
                            'media_id' => $media->id
                        ]);
                        $result = $this->publishFromUrlTikTok($config, $media->file_url, $title, $privacy, $request);
                    } else {
                        throw new \Exception('Aucune source vidéo disponible pour ce média');
                    }
                }
            }

            if (!$result) {
                throw new \Exception('Aucun résultat de publication');
            }

            $post = SocialPost::create([
                'user_id' => $user->id,
                'media_id' => $media?->id,
                'social_config_id' => $config->id,
                'platform' => 'tiktok',
                'caption' => $title,
                'post_id' => $result['publish_id'],
                'status' => 'pending',
                'response_data' => $result,
            ]);

            if ($media) {
                $media->update(['published_to_tiktok' => true]);
            }

            LogModel::create([
                'user_id' => $user->id,
                'action' => 'tiktok_video_submitted',
                'details' => "Vidéo soumise à TikTok: {$title} (publish_id: {$result['publish_id']})",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vidéo soumise à TikTok. Traitement en cours.',
                'publish_id' => $result['publish_id'],
                'post_id' => $post->id,
                'method' => $result['method'],
            ]);
        } catch (\Exception $e) {
            if (isset($tempFile) && $tempFile) {
                @unlink(storage_path('app/public/' . $tempFile));
            }

            Log::error('TikTok publish error', [
                'user_id' => $user->id,
                'media_id' => $request->media_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'une publication TikTok
     * GET /social/tiktok/publish/{publishId}/status
     */
    public function checkTikTokStatus(Request $request, string $publishId)
    {
        $user = $request->user();

        $config = SocialConfig::where('user_id', $user->id)
            ->where('platform', 'tiktok')
            ->where('is_connected', true)
            ->firstOrFail();

        if ($config->token_expires_at?->isPast()) {
            $this->refreshTikTokToken($config);
            $config->refresh();
        }

        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($config->access_token)
                ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
                ->post(self::PUBLISH_STATUS_URL, ['publish_id' => $publishId]);

            if (!$response->successful()) {
                throw new \Exception('Erreur lors de la vérification du statut: ' . $response->body());
            }

            $statusData = $response->json('data', []);
            $tiktokStatus = $statusData['status'] ?? 'unknown';

            $mappedStatus = match ($tiktokStatus) {
                'PUBLISH_COMPLETE' => 'published',
                'PUBLISH_FAILED' => 'failed',
                'SEND_TO_USER_INBOX' => 'published',
                'PROCESSING' => 'pending',
                default => 'pending',
            };

            $post = SocialPost::where('user_id', $user->id)
                ->where('post_id', $publishId)
                ->first();

            if ($post && $post->status !== $mappedStatus) {
                $post->update([
                    'status' => $mappedStatus,
                    'response_data' => array_merge($post->response_data ?? [], ['status_check' => $statusData]),
                ]);
            }

            return response()->json([
                'publish_id' => $publishId,
                'tiktok_status' => $tiktokStatus,
                'status' => $mappedStatus,
                'details' => $statusData,
            ]);
        } catch (\Exception $e) {
            Log::error('TikTok status check error', [
                'publish_id' => $publishId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // SECTION 3: TIKTOK - HELPERS PRIVÉS
    // ============================================================

    /**
     * Récupérer les informations de l'utilisateur TikTok
     */
    private function fetchTikTokUserInfo(string $accessToken): array
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($accessToken)
                ->get(self::USER_URL, [
                    'fields' => 'open_id,avatar_url,display_name,username',
                ]);
            return $response->json('data.user', []);
        } catch (\Exception $e) {
            Log::warning('TikTok user info fetch failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function refreshTikTokToken(SocialConfig $config): bool
    {
        try {
            // Utiliser les credentials de l'utilisateur
            $clientKey = $config->client_id;
            $clientSecret = $config->client_secret;

            $response = Http::withOptions(['verify' => false])
                ->asForm()
                ->post(self::REFRESH_URL, [
                    'client_key' => $clientKey,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $config->refresh_token,
                ]);

            if (!$response->successful()) {
                Log::error('TikTok refresh token HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();

            if (!isset($data['access_token'])) {
                Log::warning('TikTok refresh token failed', $data);
                return false;
            }

            $config->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $config->refresh_token,
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 86400),
            ]);

            Log::info('TikTok token refreshed successfully', ['user_id' => $config->user_id]);
            return true;
        } catch (\Exception $e) {
            Log::error('TikTok refresh token exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Publier depuis une URL (PULL_FROM_URL)
     * Supporte PHOTO et VIDEO
     */
    private function publishFromUrlTikTok($config, string $mediaUrl, string $title, string $privacy, $request): array
    {
        $payload = [
            'post_mode' => 'DIRECT_POST',
            'media_type' => 'VIDEO',
            'post_info' => [
                'title' => substr($title, 0, 150),
                'description' => substr($title, 0, 150),
                'privacy_level' => $privacy,
                'disable_comment' => $request->boolean('disable_comment', false),
                'disable_duet' => $request->boolean('disable_duet', false),
                'disable_stitch' => $request->boolean('disable_stitch', false),
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'video_url' => $mediaUrl,
            ],
        ];

        Log::info('TikTok PULL_FROM_URL - Payload', ['payload' => $payload]);

        $response = Http::withOptions(['verify' => false])
            ->withToken($config->access_token)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->timeout(60)
            ->post(self::PUBLISH_INIT_URL, $payload);

        if (!$response->successful()) {
            $data = $response->json();
            throw new \Exception('TikTok API error: ' . ($data['error']['message'] ?? $response->body()));
        }

        $data = $response->json();

        if (isset($data['error']) && $data['error']['code'] !== 'ok') {
            throw new \Exception($data['error']['message'] ?? 'Erreur TikTok inconnue');
        }

        return [
            'success' => true,
            'publish_id' => $data['data']['publish_id'] ?? null,
            'method' => 'PULL_FROM_URL',
            'media_type' => 'VIDEO',
        ];
    }

    /**
     * Publier depuis un fichier local (FILE_UPLOAD) - Version INBOX
     */
    private function publishFromFileTikTok($config, string $filePath, string $title, string $privacy, $request): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Fichier vidéo introuvable: {$filePath}");
        }

        $fileSize = filesize($filePath);
        $minChunk = 5 * 1024 * 1024;   // 5 Mo minimum par chunk
        $preferredChunk = 10 * 1024 * 1024; // 10 Mo, dans la plage 5-64 Mo autorisée

        if ($fileSize < $minChunk) {
            // Upload en un seul bloc (obligatoire si < 5 Mo)
            $chunkSize = $fileSize;
            $totalChunks = 1;
        } else {
            $chunkSize = $preferredChunk;
            // IMPORTANT : floor, pas ceil — le dernier chunk absorbe le reste
            $totalChunks = (int) floor($fileSize / $chunkSize);
            if ($totalChunks < 1) {
                $totalChunks = 1;
            }
        }

        $initBody = [
            'source_info' => [
                'source' => 'FILE_UPLOAD',
                'video_size' => (int) $fileSize,
                'chunk_size' => (int) $chunkSize,
                'total_chunk_count' => (int) $totalChunks,
            ],
        ];

        Log::info('TikTok INBOX FILE_UPLOAD - Init', [
            'file_size' => $fileSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
        ]);

        $inboxInitUrl = 'https://open.tiktokapis.com/v2/post/publish/inbox/video/init/';

        $initResponse = Http::withOptions(['verify' => false])
            ->withToken($config->access_token)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->timeout(30)
            ->post($inboxInitUrl, $initBody);

        if (!$initResponse->successful()) {
            $data = $initResponse->json();
            throw new \Exception('TikTok init error: ' . ($data['error']['message'] ?? $initResponse->body()));
        }

        $initData = $initResponse->json();
        $errorCode = $initData['error']['code'] ?? 'ok';

        if ($errorCode !== 'ok') {
            throw new \Exception($initData['error']['message'] ?? 'Unknown error');
        }

        $publishId = $initData['data']['publish_id'] ?? null;
        $uploadUrl = $initData['data']['upload_url'] ?? null;

        if (!$publishId || !$uploadUrl) {
            throw new \Exception('TikTok init response incomplete: ' . json_encode($initData));
        }

        Log::info('TikTok INBOX init successful', [
            'publish_id' => $publishId,
            'upload_url' => $uploadUrl,
        ]);

        // ============================================================
        // Upload des chunks — le dernier absorbe TOUT le reste du fichier
        // ============================================================
        $handle = fopen($filePath, 'rb');
        $offset = 0;

        for ($i = 0; $i < $totalChunks; $i++) {
            $isLast = ($i === $totalChunks - 1);
            $bytesToRead = $isLast ? ($fileSize - $offset) : $chunkSize;

            $chunk = fread($handle, $bytesToRead);
            $chunkLen = strlen($chunk);
            $rangeEnd = $offset + $chunkLen - 1;

            Log::info("TikTok chunk {$i}/{$totalChunks}", [
                'range' => "bytes {$offset}-{$rangeEnd}/{$fileSize}",
                'chunk_size' => $chunkLen,
                'is_last' => $isLast,
            ]);

            $uploadResponse = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'Content-Type' => 'video/mp4',
                    'Content-Range' => "bytes {$offset}-{$rangeEnd}/{$fileSize}",
                    'Content-Length' => (string) $chunkLen,
                ])
                ->timeout(120)
                ->withBody($chunk, 'video/mp4')
                ->put($uploadUrl);

            // 201 = terminé (dernier chunk), 206 = partiel (chunks intermédiaires)
            if (!in_array($uploadResponse->status(), [200, 201, 206])) {
                fclose($handle);
                $data = $uploadResponse->json();
                throw new \Exception("Erreur upload chunk {$i}: " . ($data['error']['message'] ?? $uploadResponse->body()));
            }

            $offset += $chunkLen;
        }

        fclose($handle);

        Log::info('TikTok INBOX FILE_UPLOAD completed', [
            'publish_id' => $publishId,
            'chunks' => $totalChunks,
            'total_bytes' => $fileSize,
        ]);

        return [
            'success' => true,
            'publish_id' => $publishId,
            'method' => 'FILE_UPLOAD_INBOX',
            'inbox' => true,
            'message' => 'Vidéo uploadée avec succès. L\'utilisateur doit finaliser la publication dans l\'app TikTok.',
        ];
    }

    /**
     * Publier une photo sur TikTok (PULL_FROM_URL uniquement, obligatoire par TikTok)
     */
    private function publishPhotoTikTok($config, array $photoUrls, string $title, string $description, string $privacy, $request): array
    {
        if (empty($photoUrls)) {
            throw new \Exception('Aucune image fournie pour la publication TikTok.');
        }

        if (count($photoUrls) > 35) {
            throw new \Exception('Maximum 35 images par publication TikTok.');
        }

        $payload = [
            'post_info' => [
                'title' => substr($title, 0, 150),
                'description' => substr($description, 0, 4000),
                'privacy_level' => $privacy,
                'disable_comment' => $request->boolean('disable_comment', false),
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'photo_cover_index' => 0,
                'photo_images' => $photoUrls,
            ],
            'post_mode' => 'MEDIA_UPLOAD',
            'media_type' => 'PHOTO',
        ];

        Log::info('TikTok PHOTO - Payload', ['payload' => $payload]);

        $response = Http::withOptions(['verify' => false])
            ->withToken($config->access_token)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->timeout(60)
            ->post(self::PUBLISH_INIT_URL, $payload);

        $data = $response->json();

        if (!$response->successful() || (isset($data['error']) && $data['error']['code'] !== 'ok')) {
            throw new \Exception('TikTok Photo API error: ' . ($data['error']['message'] ?? $response->body()));
        }

        return [
            'success' => true,
            'publish_id' => $data['data']['publish_id'] ?? null,
            'method' => 'PHOTO_PULL_FROM_URL',
        ];
    }

    /**
     * S'assurer que les métadonnées de la vidéo sont présentes
     * Extrait la durée et les dimensions de la vidéo si elles ne sont pas déjà renseignées
     */
    private function ensureVideoMetadata($media, string $filePath): void
    {
        // Si les métadonnées sont déjà présentes, on ne fait rien
        if ($media->duration_seconds && $media->width && $media->height) {
            return;
        }

        // Vérifier que le fichier existe
        if (!file_exists($filePath)) {
            Log::warning('Fichier vidéo introuvable pour l\'extraction des métadonnées', [
                'media_id' => $media->id,
                'file_path' => $filePath
            ]);
            return;
        }

        // Vérifier que FFmpeg est disponible
        $ffprobePath = shell_exec('which ffprobe 2>/dev/null');
        if (empty($ffprobePath)) {
            Log::warning('FFmpeg (ffprobe) non disponible pour l\'extraction des métadonnées');
            return;
        }

        try {
            // Extraire la durée
            $duration = shell_exec(
                "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " .
                    escapeshellarg($filePath) . " 2>/dev/null"
            );
            $duration = trim($duration);

            // Extraire les dimensions
            $dimensions = shell_exec(
                "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 " .
                    escapeshellarg($filePath) . " 2>/dev/null"
            );
            $dimensions = trim($dimensions);

            // Préparer les données à mettre à jour
            $updateData = [];

            if (!empty($duration) && is_numeric($duration)) {
                $updateData['duration_seconds'] = (int) round((float) $duration);
            }

            if (!empty($dimensions)) {
                $parts = explode(',', $dimensions);
                if (count($parts) >= 2) {
                    $width = (int) trim($parts[0]);
                    $height = (int) trim($parts[1]);
                    if ($width > 0 && $height > 0) {
                        $updateData['width'] = $width;
                        $updateData['height'] = $height;
                    }
                }
            }

            // Mettre à jour le média si des données ont été extraites
            if (!empty($updateData)) {
                $media->update($updateData);
                Log::info('Métadonnées vidéo extraites avec succès', [
                    'media_id' => $media->id,
                    'duration' => $updateData['duration_seconds'] ?? null,
                    'width' => $updateData['width'] ?? null,
                    'height' => $updateData['height'] ?? null
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'extraction des métadonnées vidéo', [
                'media_id' => $media->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // ============================================================
    // SECTION 4: INSTAGRAM
    // ============================================================

    private function getUserInstagramConfig(int $userId): ?SocialConfig
    {
        return SocialConfig::where('user_id', $userId)
            ->where('platform', 'instagram')
            ->first();
    }

    public function connectInstagram(Request $request)
    {
        $user = $request->user();

        $config = $this->getUserInstagramConfig($user->id);

        if (!$config || !$config->client_id) {
            return response()->json([
                'success' => false,
                'error' => 'Veuillez d\'abord importer vos credentials Instagram.',
                'requires_import' => true,
            ], 400);
        }

        $state = 'instagram_' . $user->id . '_' . time() . '_' . bin2hex(random_bytes(8));
        cache()->put("instagram_state_{$user->id}", $state, now()->addMinutes(15));

        $appId = $config->client_id;
        $redirectUri = $config->redirect_uri ?? 'https://redacteur.benin-digital.app/api/social/instagram/callback';

        $authUrl = 'https://api.instagram.com/oauth/authorize?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', [
                'user_profile',
                'user_media',
            ]),
            'state' => $state,
        ]);

        return response()->json([
            'success' => true,
            'auth_url' => $authUrl,
            'state' => $state,
        ]);
    }

    public function importInstagramToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
            'user_id' => 'required|string',
            'username' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $accessToken = $request->access_token;
        $instagramUserId = $request->user_id;
        $username = $request->username ?? 'instagram_user';

        try {
            $testResponse = Http::withOptions(['verify' => false])
                ->get('https://graph.instagram.com/me', [
                    'fields' => 'id,username,account_type,media_count',
                    'access_token' => $accessToken,
                ]);

            if (!$testResponse->successful()) {
                Log::error('Token Instagram invalide', [
                    'status' => $testResponse->status(),
                    'body' => $testResponse->body()
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Token invalide ou expiré'
                ], 400);
            }

            $userInfo = $testResponse->json();

            $config = SocialConfig::firstOrNew([
                'user_id' => $user->id,
                'platform' => 'instagram',
            ]);

            $config->fill([
                'access_token' => $accessToken,
                'account_id' => $instagramUserId,
                'account_username' => $username,
                'account_name' => $username,
                'profile_picture_url' => null,
                'is_connected' => true,
                'token_expires_at' => now()->addDays(60),
                'metadata' => [
                    'account_type' => $userInfo['account_type'] ?? null,
                    'media_count' => $userInfo['media_count'] ?? 0,
                    'username' => $username,
                    'user_id' => $instagramUserId,
                    'imported_at' => now()->toIso8601String(),
                ],
            ])->save();

            LogModel::create([
                'user_id' => $user->id,
                'action' => 'instagram_token_imported',
                'details' => "Token Instagram importé pour: {$username}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token Instagram importé avec succès',
                'config' => $config,
                'user_info' => $userInfo,
            ]);
        } catch (\Exception $e) {
            Log::error('Import token Instagram error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function instagramCallback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');

        $frontend = 'https://redacteur.benin-digital.app/manager/social/instagram';

        if ($error) {
            return redirect($frontend . '?error=' . urlencode($request->get('error_description') ?? $error));
        }
        if (!$code || !$state) {
            return redirect($frontend . '?error=missing_code_or_state');
        }

        preg_match('/instagram_(\d+)_/', $state, $matches);
        $userId = $matches[1] ?? null;
        if (!$userId) {
            return redirect($frontend . '?error=invalid_state');
        }

        $storedState = cache()->get("instagram_state_{$userId}");
        if ($state !== $storedState) {
            Log::error('Instagram state mismatch', ['received' => $state, 'stored' => $storedState]);
            return redirect($frontend . '?error=state_mismatch');
        }
        cache()->forget("instagram_state_{$userId}");

        try {
            $config = SocialConfig::where('user_id', $userId)
                ->where('platform', 'instagram')
                ->first();

            if (!$config) {
                throw new \Exception('Configuration Instagram non trouvée');
            }

            $appId = $config->client_id;
            $appSecret = $config->client_secret;
            $redirectUri = $config->redirect_uri ?? 'https://redacteur.benin-digital.app/api/social/instagram/callback';

            $tokenResponse = Http::withOptions(['verify' => false])
                ->asForm()
                ->post('https://api.instagram.com/oauth/access_token', [
                    'client_id' => $appId,
                    'client_secret' => $appSecret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ]);

            if (!$tokenResponse->successful()) {
                Log::error('Instagram token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'body' => $tokenResponse->body()
                ]);
                return redirect($frontend . '?error=token_exchange_failed');
            }

            $tokenData = $tokenResponse->json();

            if (isset($tokenData['error_message'])) {
                return redirect($frontend . '?error=' . urlencode($tokenData['error_message']));
            }

            $accessToken = $tokenData['access_token'];
            $igUserId = $tokenData['user_id'];

            $longLivedResponse = Http::withOptions(['verify' => false])
                ->get('https://graph.instagram.com/access_token', [
                    'grant_type' => 'ig_exchange_token',
                    'client_secret' => $appSecret,
                    'access_token' => $accessToken,
                ]);

            if (!$longLivedResponse->successful()) {
                Log::error('Instagram long-lived token exchange failed', [
                    'status' => $longLivedResponse->status(),
                    'body' => $longLivedResponse->body()
                ]);
                return redirect($frontend . '?error=long_lived_token_failed');
            }

            $longLivedData = $longLivedResponse->json();
            $longLivedToken = $longLivedData['access_token'] ?? $accessToken;
            $expiresIn = $longLivedData['expires_in'] ?? 5184000;

            $userInfoResponse = Http::withOptions(['verify' => false])
                ->get('https://graph.instagram.com/me', [
                    'fields' => 'id,username,account_type,media_count',
                    'access_token' => $longLivedToken,
                ]);

            $userInfo = $userInfoResponse->json();

            if (isset($userInfo['error'])) {
                Log::error('Instagram user info failed', ['error' => $userInfo['error']]);
                return redirect($frontend . '?error=user_info_failed');
            }

            $config->fill([
                'access_token' => $longLivedToken,
                'account_id' => $userInfo['id'] ?? $igUserId,
                'account_username' => $userInfo['username'] ?? null,
                'account_name' => $userInfo['username'] ?? null,
                'profile_picture_url' => null,
                'is_connected' => true,
                'token_expires_at' => now()->addSeconds((int)$expiresIn),
                'metadata' => array_merge($config->metadata ?? [], [
                    'account_type' => $userInfo['account_type'] ?? null,
                    'media_count' => $userInfo['media_count'] ?? 0,
                    'connected_at' => now()->toIso8601String(),
                ]),
            ])->save();

            LogModel::create([
                'user_id' => $userId,
                'action' => 'instagram_connected',
                'details' => 'Compte Instagram connecté: ' . ($userInfo['username'] ?? $userId),
                'ip_address' => $request->ip(),
            ]);

            return redirect($frontend . '?success=connected');
        } catch (\Exception $e) {
            Log::error('Instagram callback exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect($frontend . '?error=' . urlencode($e->getMessage()));
        }
    }

    public function instagramStatus(Request $request)
    {
        $config = SocialConfig::where('user_id', $request->user()->id)
            ->where('platform', 'instagram')
            ->first();

        if (!$config || !$config->is_connected) {
            return response()->json(['is_connected' => false]);
        }

        $isExpired = $config->token_expires_at && $config->token_expires_at->isPast();

        return response()->json([
            'is_connected' => $config->is_connected && !$isExpired,
            'account_name' => $config->account_name ?? null,
            'account_username' => $config->account_username ?? null,
            'account_id' => $config->account_id ?? null,
            'profile_picture' => $config->profile_picture_url ?? null,
            'is_token_expired' => $isExpired,
            'expires_at' => $config->token_expires_at?->toIso8601String(),
            'metadata' => $config->metadata ?? null,
        ]);
    }

    public function disconnectInstagram(Request $request)
    {
        $user = $request->user();

        $deleted = SocialConfig::where('user_id', $user->id)
            ->where('platform', 'instagram')
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune configuration Instagram trouvée pour cet utilisateur.',
            ], 404);
        }

        LogModel::create([
            'user_id' => $user->id,
            'action' => 'instagram_disconnected',
            'details' => 'Compte Instagram déconnecté et configuration supprimée',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Compte Instagram déconnecté avec succès',
        ]);
    }

    public function publishToInstagram(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|exists:user_media,id',
            'caption' => 'nullable|string|max:2200',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $media = UserMedia::where('user_id', $user->id)
            ->where('id', $request->media_id)
            ->firstOrFail();

        $config = SocialConfig::where('user_id', $user->id)
            ->where('platform', 'instagram')
            ->where('is_connected', true)
            ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'error' => 'Compte Instagram non connecté',
                'requires_connect' => true,
            ], 400);
        }

        if ($config->token_expires_at && $config->token_expires_at->isPast()) {
            $refreshed = $this->refreshInstagramToken($config);
            if (!$refreshed) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token Instagram expiré. Veuillez reconnecter votre compte.',
                    'requires_connect' => true,
                ], 401);
            }
            $config->refresh();
        }

        try {
            $accountId = $config->account_id;
            $accessToken = $config->access_token;
            $caption = $request->caption ?? '';
            $mediaType = $media->type ?? 'image';

            // Construction de l'URL publique
            $ngrokUrl = config('app.ngrok_url', 'https://redacteur.benin-digital.app');
            $mediaUrl = $media->file_url;

            if (strpos($mediaUrl, 'localhost') !== false || strpos($mediaUrl, '127.0.0.1') !== false) {
                $mediaUrl = str_replace(['https://redacteur.benin-digital.app', 'http://127.0.0.1:8000'], $ngrokUrl, $mediaUrl);
            }

            if (strpos($mediaUrl, '/storage/') === 0) {
                $mediaUrl = $ngrokUrl . $mediaUrl;
            }

            Log::info('📤 Publication Instagram - URL média', [
                'type' => $mediaType,
                'url' => $mediaUrl,
                'media_id' => $media->id,
            ]);

            // PAYLOAD CORRIGÉ
            $containerParams = [
                'caption' => $caption,
                'access_token' => $accessToken,
            ];

            if ($mediaType === 'video') {
                // Vérifier la durée pour les Reels
                if ($media->duration_seconds && ($media->duration_seconds < 3 || $media->duration_seconds > 90)) {
                    throw new \Exception("Durée vidéo de {$media->duration_seconds}s incompatible avec les Reels (3s à 90s requis).");
                }

                $containerParams['media_type'] = 'REELS';
                $containerParams['video_url'] = $mediaUrl;
                $containerParams['share_to_feed'] = 'true';
            } else {
                $containerParams['media_type'] = 'IMAGE';
                $containerParams['image_url'] = $mediaUrl;
            }

            Log::info('📤 Payload Instagram container', $containerParams);

            // Création du container
            $containerResponse = Http::withOptions(['verify' => false])
                ->timeout(60)
                ->post(self::INSTAGRAM_GRAPH_URL . $accountId . '/media', $containerParams);

            $containerData = $containerResponse->json();

            if (isset($containerData['error'])) {
                Log::error('❌ Erreur création container Instagram', [
                    'error' => $containerData['error'],
                    'payload' => $containerParams,
                    'full_response' => $containerData,
                ]);
                throw new \Exception('Erreur création container: ' . ($containerData['error']['message'] ?? json_encode($containerData['error'])));
            }

            $containerId = $containerData['id'];
            Log::info('Container Instagram créé', ['container_id' => $containerId]);

            // Attendre que le container soit prêt (pour les vidéos surtout)
            if ($mediaType === 'video') {
                $this->waitForContainerReady($containerId, $accessToken);
            }

            // Publication
            $publishResponse = Http::withOptions(['verify' => false])
                ->timeout(60)
                ->post(self::INSTAGRAM_GRAPH_URL . $accountId . '/media_publish', [
                    'creation_id' => $containerId,
                    'access_token' => $accessToken,
                ]);

            $publishData = $publishResponse->json();

            if (isset($publishData['error'])) {
                throw new \Exception('Erreur publication: ' . ($publishData['error']['message'] ?? 'Unknown'));
            }

            $postId = $publishData['id'];

            $post = SocialPost::create([
                'user_id' => $user->id,
                'media_id' => $media->id,
                'social_config_id' => $config->id,
                'platform' => 'instagram',
                'caption' => $caption,
                'post_id' => $postId,
                'post_url' => "https://www.instagram.com/p/{$postId}",
                'status' => 'published',
                'published_at' => now(),
                'response_data' => $publishData,
            ]);

            $media->update([
                'published_to_instagram' => true,
                'published_at' => now(),
            ]);

            LogModel::create([
                'user_id' => $user->id,
                'action' => 'instagram_post_published',
                'details' => "Publication Instagram: {$media->file_name}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Publié sur Instagram avec succès',
                'post' => $post,
                'post_url' => $post->post_url,
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur publication Instagram', [
                'user_id' => $user->id,
                'media_id' => $media->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            LogModel::create([
                'user_id' => $user->id,
                'action' => 'instagram_post_failed',
                'details' => "Erreur Instagram: " . $e->getMessage(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function importTikTokCredentials(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_key' => 'required|string',
            'client_secret' => 'required|string',
            'redirect_uri' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $clientKey = $request->client_key;
        $clientSecret = $request->client_secret;
        $redirectUri = $request->redirect_uri ?? 'https://redacteur.benin-digital.app/api/social/tiktok/callback';

        try {
            $config = SocialConfig::firstOrNew([
                'user_id' => $user->id,
                'platform' => 'tiktok',
            ]);

            $config->fill([
                'client_id' => $clientKey,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'is_active' => true,
                'is_connected' => false,
                'metadata' => [
                    'client_key_imported' => true,
                    'imported_at' => now()->toIso8601String(),
                ],
            ])->save();

            $state = 'tiktok_' . $user->id . '_' . time() . '_' . bin2hex(random_bytes(8));
            $codeVerifier = bin2hex(random_bytes(64));
            $codeChallenge = strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');

            cache()->put("tiktok_state_{$user->id}", $state, now()->addMinutes(15));
            cache()->put("tiktok_code_verifier_{$user->id}", $codeVerifier, now()->addMinutes(15));

            $authUrl = 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
                'client_key' => $clientKey,
                'response_type' => 'code',
                'scope' => 'user.info.basic,user.info.profile,user.info.stats,video.list,video.publish',
                'redirect_uri' => $redirectUri,
                'state' => $state,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
            ]);

            LogModel::create([
                'user_id' => $user->id,
                'action' => 'tiktok_credentials_imported',
                'details' => "Credentials TikTok importés pour l'utilisateur",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Credentials TikTok importés avec succès.',
                'auth_url' => $authUrl,
                'config' => $config,
            ]);
        } catch (\Exception $e) {
            Log::error('Import TikTok credentials error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function refreshInstagramToken(SocialConfig $config): bool
    {
        try {
            $clientSecret = $config->client_secret;

            if (!$clientSecret) {
                Log::error('Instagram refresh failed: no client_secret found', ['user_id' => $config->user_id]);
                return false;
            }

            $response = Http::withOptions(['verify' => false])
                ->get('https://graph.instagram.com/refresh_access_token', [
                    'grant_type' => 'ig_refresh_token',
                    'access_token' => $config->access_token,
                ]);

            if (!$response->successful()) {
                Log::error('Instagram token refresh failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }

            $data = $response->json();

            if (!isset($data['access_token'])) {
                return false;
            }

            $config->update([
                'access_token' => $data['access_token'],
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 5184000),
            ]);

            Log::info('Instagram token refreshed successfully', ['user_id' => $config->user_id]);
            return true;
        } catch (\Exception $e) {
            Log::error('Instagram token refresh exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function waitForContainerReady(string $containerId, string $accessToken, int $maxAttempts = 20): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = Http::withOptions(['verify' => false])
                ->get(self::INSTAGRAM_GRAPH_URL . $containerId, [
                    'fields' => 'status_code',
                    'access_token' => $accessToken,
                ]);

            $data = $response->json();
            $status = $data['status_code'] ?? $data['status'] ?? null;

            if ($status === 'FINISHED') {
                return;
            }

            if ($status === 'ERROR' || $status === 'EXPIRED') {
                throw new \Exception('Le traitement du média Instagram a échoué: ' . ($data['error_message'] ?? 'Unknown'));
            }

            sleep(3);
        }

        throw new \Exception('Timeout: le container Instagram n\'est pas prêt après ' . ($maxAttempts * 3) . ' secondes');
    }

    /**
     * Publier un média sur un réseau social (générique)
     * POST /social/publish
     */
    public function publish(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|exists:user_media,id',
            'platform' => 'required|string|in:tiktok,instagram',
            'caption' => 'nullable|string|max:2200',
            'schedule_at' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $mediaId = $request->media_id;
        $platform = $request->platform;
        $caption = $request->caption ?? '';
        $scheduleAt = $request->schedule_at;

        $media = UserMedia::where('user_id', $user->id)
            ->where('id', $mediaId)
            ->first();

        if (!$media) {
            return response()->json(['error' => 'Média non trouvé'], 404);
        }

        $socialConfig = SocialConfig::where('user_id', $user->id)
            ->where('platform', $platform)
            ->where('is_connected', true)
            ->first();

        if (!$socialConfig) {
            return response()->json([
                'error' => "Compte {$platform} non configuré",
                'requires_config' => true,
                'platform' => $platform
            ], 400);
        }

        if ($scheduleAt) {
            $post = SocialPost::create([
                'user_id' => $user->id,
                'media_id' => $media->id,
                'social_config_id' => $socialConfig->id,
                'platform' => $platform,
                'caption' => $caption,
                'status' => 'scheduled',
                'scheduled_at' => $scheduleAt
            ]);

            LogModel::create([
                'user_id' => $user->id,
                'action' => "social_post_scheduled_{$platform}",
                'details' => "Publication programmée sur {$platform}: {$media->title}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Publication programmée sur {$platform}",
                'post' => $post
            ]);
        }

        // Rediriger vers la méthode de publication réelle
        if ($platform === 'tiktok') {
            $tiktokRequest = new Request([
                'media_id' => $media->id,
                'title' => $caption,
                'privacy' => 'PUBLIC_TO_EVERYONE',
                'disable_comment' => $request->boolean('disable_comment', false),
                'disable_duet' => $request->boolean('disable_duet', false),
                'disable_stitch' => $request->boolean('disable_stitch', false),
            ]);
            $tiktokRequest->setUserResolver(fn() => $user);

            return $this->publishToTikTok($tiktokRequest);
        }

        if ($platform === 'instagram') {
            $instagramRequest = new Request([
                'media_id' => $media->id,
                'caption' => $caption,
            ]);
            $instagramRequest->setUserResolver(fn() => $user);

            return $this->publishToInstagram($instagramRequest);
        }

        return response()->json([
            'success' => false,
            'error' => "Plateforme {$platform} non supportée"
        ], 400);
    }

    /**
     * Récupérer les publications de l'utilisateur
     * GET /social/posts
     */
    public function posts(Request $request)
    {
        $user = $request->user();

        $posts = SocialPost::where('user_id', $user->id)
            ->with(['media', 'config'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($posts);
    }

    /**
     * Récupérer le statut d'une publication
     * GET /social/posts/{id}/status
     */
    public function postStatus($id, Request $request)
    {
        $post = SocialPost::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($post);
    }
}
