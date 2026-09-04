<?php
// app/Http/Controllers/UserMediaController.php

namespace App\Http\Controllers;

use App\Models\UserMedia;
use App\Models\Log as LogModel;
use App\Models\SystemApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class UserMediaController extends Controller
{
    // ============================================================
    // GÉNÉRATION D'IMAGES (DYNAMIQUE)
    // ============================================================

    /**
     * Générer une image avec le provider choisi
     */
    public function generateImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string|min:3|max:1000',
            'aspect_ratio' => 'nullable|string|in:16:9,9:16,1:1',
            'style' => 'nullable|string|max:100',
            'count' => 'nullable|integer|min:1|max:4',
            'provider' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $prompt = $request->prompt;
        $aspectRatio = $request->aspect_ratio ?? '16:9';
        $style = $request->style ?? 'moderne, professionnel';
        $count = $request->count ?? 1;
        $providerName = $request->provider ?? 'pollinations';

        try {
            $config = $this->getProviderConfig($providerName, 'image');

            Log::info('Génération d\'image', [
                'user_id' => $user->id,
                'provider' => $providerName,
                'prompt' => $prompt
            ]);

            $generatedImages = $this->generateWithProvider($providerName, 'image', $prompt, $count, $aspectRatio, $config);

            $mediaRecords = [];
            foreach ($generatedImages as $imageData) {
                $media = UserMedia::create([
                    'user_id' => $user->id,
                    'type' => 'image',
                    'title' => substr($prompt, 0, 100),
                    'prompt_used' => $prompt,
                    'file_name' => $imageData['file_name'],
                    'file_url' => $imageData['file_url'],
                    'file_path' => $imageData['file_path'] ?? null,
                    'mime_type' => 'image/png',
                    'file_size' => $imageData['file_size'] ?? 0,
                    'width' => $imageData['width'] ?? 1024,
                    'height' => $imageData['height'] ?? 768,
                    'provider' => $providerName,
                    'model_used' => $imageData['model_used'] ?? $config['model_name'] ?? 'unknown',
                    'status' => 'completed',
                    'metadata' => [
                        'aspect_ratio' => $aspectRatio,
                        'style' => $style,
                        'generated_at' => now()->toIso8601String()
                    ]
                ]);

                $mediaRecords[] = $media;
            }

            LogModel::create([
                'user_id' => $user->id,
                'action' => 'image_generated',
                'details' => "Image générée avec {$providerName}: {$prompt}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$count} image(s) générée(s) avec {$providerName}",
                'images' => array_map([$this, 'formatMediaResponse'], $mediaRecords)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur génération image', [
                'user_id' => $user->id,
                'provider' => $providerName,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // GÉNÉRATION DE VIDÉOS (DYNAMIQUE)
    // ============================================================

    /**
     * Générer une vidéo avec le provider choisi
     */
    public function generateVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string|min:3|max:1000',
            'duration' => 'nullable|integer|min:5|max:30',
            'aspect_ratio' => 'nullable|string|in:16:9,9:16',
            'style' => 'nullable|string|max:100',
            'provider' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $prompt = $request->prompt;
        $duration = $request->duration ?? 8;
        $aspectRatio = $request->aspect_ratio ?? '16:9';
        $style = $request->style ?? 'cinematographique, professionnel';
        $providerName = $request->provider ?? 'pollinations';

        try {
            $config = $this->getProviderConfig($providerName, 'video');

            Log::info('Génération de vidéo', [
                'user_id' => $user->id,
                'provider' => $providerName,
                'prompt' => $prompt
            ]);

            $videoData = $this->generateWithProvider($providerName, 'video', $prompt, 1, $aspectRatio, $config, $duration);
            $videoData = $videoData[0];

            $media = UserMedia::create([
                'user_id' => $user->id,
                'type' => 'video',
                'title' => substr($prompt, 0, 100),
                'prompt_used' => $prompt,
                'file_name' => $videoData['file_name'],
                'file_url' => $videoData['file_url'],
                'file_path' => $videoData['file_path'] ?? null,
                'mime_type' => 'video/mp4',
                'file_size' => $videoData['file_size'] ?? 0,
                'width' => $videoData['width'] ?? 1366,
                'height' => $videoData['height'] ?? 768,
                'duration_seconds' => $duration,
                'provider' => $providerName,
                'model_used' => $videoData['model_used'] ?? $config['model_name'] ?? 'unknown',
                'status' => 'completed',
                'metadata' => [
                    'duration' => $duration,
                    'aspect_ratio' => $aspectRatio,
                    'style' => $style,
                    'generated_at' => now()->toIso8601String()
                ]
            ]);

            LogModel::create([
                'user_id' => $user->id,
                'action' => 'video_generated',
                'details' => "Vidéo générée avec {$providerName}: {$prompt}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Vidéo générée avec {$providerName}",
                'video' => $this->formatMediaResponse($media)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur génération vidéo', [
                'user_id' => $user->id,
                'provider' => $providerName,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // CONFIGURATION DES PROVIDERS
    // ============================================================

    /**
     * Récupérer la configuration d'un provider
     */
    private function getProviderConfig(string $provider, string $type): array
    {
        $capability = $type === 'image' ? 'image_generation' : 'video_generation';

        // Récupérer la clé du provider dans la base de données
        $apiKey = SystemApiKey::where('provider', $provider)
            ->where('is_active', true)
            ->whereJsonContains('capabilities', $capability)
            ->first();

        if (!$apiKey) {
            throw new \Exception("Aucune clé API active pour le provider '{$provider}' avec la capacité '{$type}'. Veuillez configurer une clé dans l'admin.");
        }

        return [
            'provider' => $apiKey->provider,
            'api_key' => $apiKey->api_key,
            'model_name' => $apiKey->model_name,
            'config' => $apiKey->config ?? []
        ];
    }

    private function getDefaultModel(string $provider, string $type): ?string
    {
        $defaults = [
            'pollinations' => ['image' => 'flux', 'video' => 'wan'],
            'openai' => ['image' => 'dall-e-3'],
            'replicate' => ['image' => 'stability-ai/sdxl'],
            'stability' => ['image' => 'stable-diffusion-xl-1024-v1-0'],
            'google' => ['image' => 'imagen-3', 'video' => 'veo-3.1'],
            'runway' => ['video' => 'gen-3-alpha'],
            'pika' => ['video' => 'pika-2.0'],
        ];

        return $defaults[$provider][$type] ?? null;
    }

    // ============================================================
    // GÉNÉRATEUR UNIVERSEL
    // ============================================================

    private function generateWithProvider(
        string $provider,
        string $type,
        string $prompt,
        int $count,
        string $aspectRatio,
        array $config,
        int $duration = 8
    ): array {
        $dimensions = $this->getDimensions($aspectRatio);
        $model = $config['model_name'] ?? $this->getDefaultModel($provider, $type);
        $apiKey = $config['api_key'] ?? null;

        // Appeler la méthode correspondante au provider
        $methodName = 'generateWith' . ucfirst($provider);

        if (method_exists($this, $methodName)) {
            return $this->$methodName($prompt, $count, $type, $dimensions, $config, $duration);
        }

        // Provider personnalisé avec configuration générique
        return $this->generateWithGenericProvider($provider, $prompt, $count, $type, $dimensions, $config, $duration);
    }

    // ============================================================
    // POLLINATIONS (AVEC DÉSACTIVATION SSL)
    // ============================================================

    private function generateWithPollinations(string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        $baseUrl = 'https://gen.pollinations.ai';
        $results = [];
        $apiKey = $config['api_key'];

        if (!$apiKey || $apiKey === 'no-key-needed') {
            throw new \Exception('Clé API Pollinations invalide. Veuillez configurer une clé valide dans l\'admin.');
        }

        // Requête avec authentification Bearer
        $http = Http::withOptions([
            'verify' => false,
            'timeout' => 120,
        ])->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ]);

        if ($type === 'image') {
            $model = $config['model_name'] ?? 'flux';
            for ($i = 0; $i < $count; $i++) {
                $url = sprintf(
                    $baseUrl . '/image/%s?model=%s&width=%d&height=%d',
                    urlencode($prompt),
                    $model,
                    $dimensions['width'],
                    $dimensions['height']
                );

                $response = $http->get($url);

                if ($response->successful()) {
                    $content = $response->body();
                    $fileName = sprintf('image_%d_%s.png', $i + 1, now()->timestamp);
                    $path = 'temp/' . $fileName;
                    Storage::disk('public')->put($path, $content);

                    $results[] = [
                        'file_name' => $fileName,
                        'file_url' => asset('storage/' . $path),
                        'file_path' => $path,
                        'file_size' => strlen($content),
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height'],
                        'model_used' => $model,
                    ];
                } else {
                    $status = $response->status();
                    $errorBody = $response->body();
                    Log::error('Erreur Pollinations', [
                        'status' => $status,
                        'body' => $errorBody,
                        'url' => $url,
                        'api_key_masked' => substr($apiKey, 0, 8) . '...'
                    ]);

                    if ($status === 401) {
                        throw new \Exception('Clé API Pollinations invalide ou expirée. Veuillez vérifier votre clé dans l\'admin.');
                    }

                    throw new \Exception("Erreur Pollinations: {$status} - " . substr($errorBody, 0, 100));
                }
            }
        } else {
            // Vidéo
            $model = $config['model_name'] ?? 'wan';
            $url = sprintf(
                $baseUrl . '/video/%s?model=%s&duration=%d&width=%d&height=%d',
                urlencode($prompt),
                $model,
                $duration,
                $dimensions['width'],
                $dimensions['height']
            );

            $response = $http->get($url);

            if ($response->successful()) {
                $content = $response->body();
                $fileName = sprintf('video_%s.mp4', now()->timestamp);
                $path = 'temp/' . $fileName;
                Storage::disk('public')->put($path, $content);

                $results[] = [
                    'file_name' => $fileName,
                    'file_url' => asset('storage/' . $path),
                    'file_path' => $path,
                    'file_size' => strlen($content),
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'model_used' => $model,
                ];
            } else {
                $status = $response->status();
                throw new \Exception("Erreur Pollinations video: {$status}");
            }
        }

        return $results;
    }

    // ============================================================
    // OPENAI (AVEC DÉSACTIVATION SSL)
    // ============================================================

    private function generateWithOpenai(string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        if ($type !== 'image') {
            throw new \Exception('OpenAI ne supporte pas la génération de vidéos pour le moment.');
        }

        $apiKey = $config['api_key'];
        $model = $config['model_name'] ?? 'dall-e-3';
        $size = $this->getOpenAiSize($dimensions['width'], $dimensions['height']);
        $results = [];

        $http = Http::withOptions([
            'verify' => false,
            'timeout' => 120,
        ]);

        $response = $http->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/images/generations', [
            'prompt' => $prompt,
            'model' => $model,
            'n' => $count,
            'size' => $size,
            'quality' => 'standard',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            foreach ($data['data'] as $index => $imageData) {
                $imageContent = Http::withOptions(['verify' => false])->timeout(60)->get($imageData['url'])->body();
                $fileName = sprintf('image_%d_%s.png', $index + 1, now()->timestamp);
                $path = 'temp/' . $fileName;
                Storage::disk('public')->put($path, $imageContent);

                $results[] = [
                    'file_name' => $fileName,
                    'file_url' => asset('storage/' . $path),
                    'file_path' => $path,
                    'file_size' => strlen($imageContent),
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'model_used' => $model,
                ];
            }
            return $results;
        }

        $error = $response->json()['error']['message'] ?? 'Erreur OpenAI';
        throw new \Exception($error);
    }

    // ============================================================
    // REPLICATE (AVEC DÉSACTIVATION SSL)
    // ============================================================

    private function generateWithReplicate(string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        if ($type !== 'image') {
            throw new \Exception('Replicate ne supporte pas la génération de vidéos pour le moment.');
        }

        $apiKey = $config['api_key'];
        $model = $config['model_name'] ?? 'stability-ai/sdxl';
        $results = [];

        $http = Http::withOptions([
            'verify' => false,
            'timeout' => 120,
        ]);

        $response = $http->withHeaders([
            'Authorization' => 'Token ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.replicate.com/v1/predictions', [
            'version' => $model,
            'input' => [
                'prompt' => $prompt,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'num_outputs' => $count,
            ]
        ]);

        if ($response->successful()) {
            $prediction = $response->json();
            $predictionId = $prediction['id'];
            $result = $this->waitForReplicate($predictionId, $apiKey);
            $urls = $result['output'] ?? [];

            foreach ($urls as $index => $url) {
                $imageContent = Http::withOptions(['verify' => false])->timeout(60)->get($url)->body();
                $fileName = sprintf('image_%d_%s.png', $index + 1, now()->timestamp);
                $path = 'temp/' . $fileName;
                Storage::disk('public')->put($path, $imageContent);

                $results[] = [
                    'file_name' => $fileName,
                    'file_url' => asset('storage/' . $path),
                    'file_path' => $path,
                    'file_size' => strlen($imageContent),
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'model_used' => $model,
                ];
            }
            return $results;
        }

        throw new \Exception('Erreur Replicate');
    }

    private function waitForReplicate(string $predictionId, string $apiKey, int $maxAttempts = 60): array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['Authorization' => 'Token ' . $apiKey])
                ->get("https://api.replicate.com/v1/predictions/{$predictionId}");

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['status'] ?? 'processing';
                if ($status === 'succeeded') return $data;
                if ($status === 'failed') throw new \Exception('Échec Replicate');
            }
            sleep(2);
        }
        throw new \Exception('Timeout Replicate');
    }

    // ============================================================
    // STABILITY AI (AVEC DÉSACTIVATION SSL)
    // ============================================================

    private function generateWithStability(string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        if ($type !== 'image') {
            throw new \Exception('Stability AI ne supporte pas la génération de vidéos pour le moment.');
        }

        $apiKey = $config['api_key'];
        $model = $config['model_name'] ?? 'stable-diffusion-xl-1024-v1-0';
        $results = [];

        $http = Http::withOptions([
            'verify' => false,
            'timeout' => 120,
        ]);

        $response = $http->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.stability.ai/v1/generation/' . $model . '/text-to-image', [
            'text_prompts' => [['text' => $prompt, 'weight' => 1]],
            'cfg_scale' => 7,
            'height' => $dimensions['height'],
            'width' => $dimensions['width'],
            'samples' => $count,
            'steps' => 30,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            foreach ($data['artifacts'] as $index => $artifact) {
                $imageContent = base64_decode($artifact['base64']);
                $fileName = sprintf('image_%d_%s.png', $index + 1, now()->timestamp);
                $path = 'temp/' . $fileName;
                Storage::disk('public')->put($path, $imageContent);

                $results[] = [
                    'file_name' => $fileName,
                    'file_url' => asset('storage/' . $path),
                    'file_path' => $path,
                    'file_size' => strlen($imageContent),
                    'width' => $artifact['width'] ?? $dimensions['width'],
                    'height' => $artifact['height'] ?? $dimensions['height'],
                    'model_used' => $model,
                ];
            }
            return $results;
        }

        throw new \Exception('Erreur Stability AI');
    }

    // ============================================================
    // GENERIC PROVIDER (AVEC DÉSACTIVATION SSL)
    // ============================================================

    private function generateWithGenericProvider(string $provider, string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        $apiKey = $config['api_key'];
        $model = $config['model_name'] ?? 'default';
        $baseUrl = $config['config']['base_url'] ?? null;
        $authType = $config['config']['auth_type'] ?? 'bearer';

        if (!$baseUrl) {
            throw new \Exception("Le provider '{$provider}' n'a pas de base_url configurée. Veuillez configurer l'URL de l'API.");
        }

        $payload = [
            'prompt' => $prompt,
            'model' => $model,
            'count' => $count,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ];

        if ($type === 'video') {
            $payload['duration'] = $duration;
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($authType === 'bearer') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        } elseif ($authType === 'token') {
            $headers['Authorization'] = 'Token ' . $apiKey;
        } elseif ($authType === 'api-key') {
            $headers['X-API-Key'] = $apiKey;
        }

        $http = Http::withOptions([
            'verify' => false,
            'timeout' => 120,
        ]);

        $response = $http->withHeaders($headers)->post($baseUrl, $payload);

        if ($response->successful()) {
            $data = $response->json();
            $results = [];
            $urls = $data['data'] ?? $data['output'] ?? [$data['url'] ?? null];

            foreach ($urls as $index => $url) {
                if (!$url) continue;
                $imageContent = Http::withOptions(['verify' => false])->timeout(60)->get($url)->body();
                $fileName = sprintf('media_%d_%s.%s', $index + 1, now()->timestamp, $type === 'image' ? 'png' : 'mp4');
                $path = 'temp/' . $fileName;
                Storage::disk('public')->put($path, $imageContent);

                $results[] = [
                    'file_name' => $fileName,
                    'file_url' => asset('storage/' . $path),
                    'file_path' => $path,
                    'file_size' => strlen($imageContent),
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'model_used' => $model,
                ];
            }

            return $results;
        }

        Log::warning("Provider générique {$provider} a échoué", ['status' => $response->status()]);
        throw new \Exception("Erreur avec le provider '{$provider}': " . $response->status());
    }

    // ============================================================
    // GOOGLE (placeholder)
    // ============================================================

    private function generateWithGoogle(string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        throw new \Exception('Google (Imagen/Veo) n\'est pas encore implémenté. Veuillez utiliser un autre provider.');
    }

    // ============================================================
    // RUNWAY (placeholder)
    // ============================================================

    private function generateWithRunway(string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        throw new \Exception('Runway n\'est pas encore implémenté. Veuillez utiliser un autre provider.');
    }

    // ============================================================
    // PIKA (placeholder)
    // ============================================================

    private function generateWithPika(string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        throw new \Exception('Pika n\'est pas encore implémenté. Veuillez utiliser un autre provider.');
    }

    // ============================================================
    // ELEVENLABS (placeholder)
    // ============================================================

    private function generateWithElevenlabs(string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        throw new \Exception('ElevenLabs n\'est pas encore implémenté. Veuillez utiliser un autre provider.');
    }

    // ============================================================
    // SYNTHESIA (placeholder)
    // ============================================================

    private function generateWithSynthesia(string $prompt, int $count, string $type, array $dimensions, array $config, int $duration = 8): array
    {
        throw new \Exception('Synthesia n\'est pas encore implémenté. Veuillez utiliser un autre provider.');
    }

    // ============================================================
    // UTILITAIRES
    // ============================================================

    private function getDimensions(string $aspectRatio): array
    {
        return match ($aspectRatio) {
            '9:16' => ['width' => 768, 'height' => 1366],
            '1:1' => ['width' => 1024, 'height' => 1024],
            default => ['width' => 1366, 'height' => 768],
        };
    }

    private function getOpenAiSize(int $width, int $height): string
    {
        if ($width >= 1792 && $height >= 1024) return '1792x1024';
        if ($width >= 1024 && $height >= 1792) return '1024x1792';
        return '1024x1024';
    }

    /**
     * Supprimer un média
     */
    public function delete($id, Request $request)
    {
        $user = $request->user();

        // Récupérer le média appartenant à l'utilisateur
        $media = UserMedia::where('user_id', $user->id)->findOrFail($id);

        // Supprimer le fichier physique si présent
        if ($media->file_path && Storage::disk('public')->exists($media->file_path)) {
            Storage::disk('public')->delete($media->file_path);
        }

        // Supprimer l'enregistrement en base
        $media->delete();

        LogModel::create([
            'user_id' => $user->id,
            'action' => 'media_deleted',
            'details' => "Média supprimé: {$media->title} ({$media->file_name})",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Média supprimé avec succès'
        ]);
    }

    /**
     * Récupérer un média spécifique
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        $media = UserMedia::where('user_id', $user->id)->findOrFail($id);

        return response()->json($this->formatMediaResponse($media));
    }

    /**
     * Télécharger un média
     */
    public function download($id, Request $request)
    {
        $user = $request->user();

        $media = UserMedia::where('user_id', $user->id)->findOrFail($id);

        if (!$media->file_path && !$media->file_url) {
            return response()->json(['error' => 'Fichier non disponible'], 404);
        }

        // Si le fichier est stocké localement
        if ($media->file_path) {
            $fullPath = storage_path('app/public/' . $media->file_path);

            // Vérifier si le fichier existe
            if (file_exists($fullPath)) {
                return Response::download($fullPath, $media->file_name);
            }
        }

        // Si c'est une URL externe
        if ($media->file_url) {
            return response()->json([
                'download_url' => $media->file_url,
                'file_name' => $media->file_name
            ]);
        }

        return response()->json(['error' => 'Fichier non disponible'], 404);
    }

    /**
     * Uploader un fichier média
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:204800', // 200MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $file = $request->file('file');
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();

        // Déterminer le type de média
        $mediaType = $this->determineMediaType($mimeType, $extension);
        if (!$mediaType) {
            return response()->json([
                'success' => false,
                'error' => 'Type de fichier non supporté. Veuillez uploader une image ou une vidéo.'
            ], 400);
        }

        try {
            // Générer un nom unique
            $fileName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                . '_' . Str::random(8)
                . '.' . $extension;
            $path = "user_media/{$user->id}/" . date('Y/m/d');

            // Stocker le fichier
            $storedPath = $file->storeAs($path, $fileName, 'public');
            $fullUrl = asset('storage/' . $storedPath);

            // Récupérer les informations du fichier
            $fileSize = $file->getSize();
            $dimensions = $this->getFileDimensions($file, $mediaType);

            // Créer l'enregistrement en base
            $media = UserMedia::create([
                'user_id' => $user->id,
                'type' => $mediaType,
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'prompt_used' => null,
                'file_name' => $fileName,
                'file_url' => $fullUrl,
                'file_path' => $storedPath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'width' => $dimensions['width'] ?? null,
                'height' => $dimensions['height'] ?? null,
                'duration_seconds' => $dimensions['duration'] ?? null,
                'provider' => 'upload',
                'model_used' => 'user_upload',
                'status' => 'completed',
                'metadata' => [
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_at' => now()->toIso8601String()
                ]
            ]);

            LogModel::create([
                'user_id' => $user->id,
                'action' => 'media_uploaded',
                'details' => "Fichier uploadé: {$file->getClientOriginalName()}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fichier uploadé avec succès',
                'media' => $this->formatMediaResponse($media)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur upload', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déterminer le type de média à partir du MIME type
     */
    private function determineMediaType(string $mimeType, string $extension): ?string
    {
        $allowedMimeTypes = [
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            'video' => ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv', 'video/x-flv', 'video/webm'],
        ];

        $allowedExtensions = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            'video' => ['mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'],
        ];

        if (in_array($mimeType, $allowedMimeTypes['image']) || in_array(strtolower($extension), $allowedExtensions['image'])) {
            return 'image';
        }

        if (in_array($mimeType, $allowedMimeTypes['video']) || in_array(strtolower($extension), $allowedExtensions['video'])) {
            return 'video';
        }

        return null;
    }

    /**
     * Récupérer les dimensions d'un fichier
     */
    private function getFileDimensions($file, string $mediaType): array
    {
        $dimensions = ['width' => null, 'height' => null, 'duration' => null];

        try {
            $path = $file->getRealPath();

            if ($mediaType === 'image') {
                list($width, $height) = getimagesize($path);
                $dimensions['width'] = $width;
                $dimensions['height'] = $height;
            } elseif ($mediaType === 'video') {
                // Utiliser FFmpeg si disponible
                if (function_exists('shell_exec')) {
                    $duration = shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($path));
                    if ($duration) {
                        $dimensions['duration'] = (int) round((float) $duration);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Impossible de récupérer les dimensions du fichier', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
        }

        return $dimensions;
    }

    /**
     * Uploader plusieurs fichiers
     */
    public function uploadMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files.*' => 'required|file|max:204800', // 200MB max par fichier
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $uploaded = [];
        $errors = [];

        foreach ($request->file('files') as $file) {
            try {
                $result = $this->processSingleUpload($file, $user);
                if ($result['success']) {
                    $uploaded[] = $result['media'];
                } else {
                    $errors[] = $result['error'];
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        return response()->json([
            'success' => count($uploaded) > 0,
            'message' => count($uploaded) . ' fichier(s) uploadé(s) avec succès' . (count($errors) > 0 ? ', ' . count($errors) . ' erreur(s)' : ''),
            'uploaded' => array_map([$this, 'formatMediaResponse'], $uploaded),
            'errors' => $errors
        ]);
    }

    /**
     * Processus d'upload d'un seul fichier (pour uploadMultiple)
     */
    private function processSingleUpload($file, $user): array
    {
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();

        $mediaType = $this->determineMediaType($mimeType, $extension);
        if (!$mediaType) {
            return ['success' => false, 'error' => "Type non supporté: {$mimeType}"];
        }

        $fileName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            . '_' . Str::random(8)
            . '.' . $extension;
        $path = "user_media/{$user->id}/" . date('Y/m/d');

        $storedPath = $file->storeAs($path, $fileName, 'public');
        $fullUrl = asset('storage/' . $storedPath);

        $dimensions = $this->getFileDimensions($file, $mediaType);

        $media = UserMedia::create([
            'user_id' => $user->id,
            'type' => $mediaType,
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'prompt_used' => null,
            'file_name' => $fileName,
            'file_url' => $fullUrl,
            'file_path' => $storedPath,
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'duration_seconds' => $dimensions['duration'] ?? null,
            'provider' => 'upload',
            'model_used' => 'user_upload',
            'status' => 'completed',
            'metadata' => [
                'original_name' => $file->getClientOriginalName(),
                'uploaded_at' => now()->toIso8601String()
            ]
        ]);

        LogModel::create([
            'user_id' => $user->id,
            'action' => 'media_uploaded',
            'details' => "Fichier uploadé: {$file->getClientOriginalName()}",
        ]);

        return ['success' => true, 'media' => $media];
    }

    /**
     * Bibliothèque des médias de l'utilisateur avec filtres
     */
    public function library(Request $request)
    {
        $user = $request->user();

        // ============================================================
        // Construction de la requête de base
        // ============================================================

        $query = UserMedia::where('user_id', $user->id);

        // ============================================================
        // 1. FILTRE PAR TYPE (image/video)
        // ============================================================
        if ($request->has('type') && in_array($request->type, ['image', 'video'])) {
            $query->where('type', $request->type);
        }

        // ============================================================
        // 2. FILTRE PAR RECHERCHE
        // ============================================================
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('prompt_used', 'like', '%' . $search . '%')
                    ->orWhere('file_name', 'like', '%' . $search . '%');
            });
        }

        // ============================================================
        // 3. FILTRE PAR STATUT (publié ou non)
        // ============================================================
        if ($request->has('published')) {
            $published = filter_var($request->published, FILTER_VALIDATE_BOOLEAN);
            if ($published) {
                $query->where(function ($q) {
                    $q->where('published_to_tiktok', true)
                        ->orWhere('published_to_instagram', true);
                });
            } else {
                $query->where(function ($q) {
                    $q->where('published_to_tiktok', false)
                        ->where('published_to_instagram', false);
                });
            }
        }

        // ============================================================
        // 4. FILTRE PAR DATE
        // ============================================================
        if ($request->has('from_date')) {
            try {
                $fromDate = \Carbon\Carbon::parse($request->from_date);
                $query->whereDate('created_at', '>=', $fromDate);
            } catch (\Exception $e) {
                // Ignorer la date invalide
            }
        }

        if ($request->has('to_date')) {
            try {
                $toDate = \Carbon\Carbon::parse($request->to_date);
                $query->whereDate('created_at', '<=', $toDate);
            } catch (\Exception $e) {
                // Ignorer la date invalide
            }
        }

        // ============================================================
        // 5. TRI
        // ============================================================
        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $allowedSorts = ['created_at', 'file_size', 'title', 'type'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // ============================================================
        // Pagination
        // ============================================================
        $perPage = $request->per_page ?? 20;
        $media = $query->paginate($perPage);

        // ============================================================
        // Formater la réponse
        // ============================================================
        $data = $media->items();
        $formattedData = array_map([$this, 'formatMediaResponse'], $data);

        // ============================================================
        // Statistiques
        // ============================================================
        $totalCount = UserMedia::where('user_id', $user->id)->count();
        $publishedCount = UserMedia::where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('published_to_tiktok', true)
                    ->orWhere('published_to_instagram', true);
            })
            ->count();
        $scheduledCount = \App\Models\ScheduledTask::where('user_id', $user->id)
            ->where('type', 'media')
            ->where('status', 'pending')
            ->count();
        $imageCount = UserMedia::where('user_id', $user->id)->where('type', 'image')->count();
        $videoCount = UserMedia::where('user_id', $user->id)->where('type', 'video')->count();

        return response()->json([
            'data' => $formattedData,
            'total' => $media->total(),
            'current_page' => $media->currentPage(),
            'per_page' => $media->perPage(),
            'last_page' => $media->lastPage(),
            'stats' => [
                'total' => $totalCount,
                'published' => $publishedCount,
                'scheduled' => $scheduledCount,
                'images' => $imageCount,
                'videos' => $videoCount,
            ]
        ]);
    }

    /**
     * Obtenir les statistiques des médias de l'utilisateur
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $total = UserMedia::where('user_id', $user->id)->count();
        $published = UserMedia::where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('published_to_tiktok', true)
                    ->orWhere('published_to_instagram', true);
            })
            ->count();
        $scheduled = \App\Models\ScheduledTask::where('user_id', $user->id)
            ->where('type', 'media')
            ->where('status', 'pending')
            ->count();
        $images = UserMedia::where('user_id', $user->id)->where('type', 'image')->count();
        $videos = UserMedia::where('user_id', $user->id)->where('type', 'video')->count();

        $publishedTiktok = UserMedia::where('user_id', $user->id)
            ->where('published_to_tiktok', true)
            ->count();
        $publishedInstagram = UserMedia::where('user_id', $user->id)
            ->where('published_to_instagram', true)
            ->count();

        return response()->json([
            'total' => $total,
            'published' => $published,
            'unpublished' => $total - $published,
            'scheduled' => $scheduled,
            'images' => $images,
            'videos' => $videos,
            'by_platform' => [
                'tiktok' => $publishedTiktok,
                'instagram' => $publishedInstagram,
            ]
        ]);
    }

    /**
     * Programmer la publication d'un média
     */
    public function schedulePublication(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|in:tiktok,instagram,facebook,twitter',
            'scheduled_at' => 'required|date|after:now',
            'caption' => 'nullable|string|max:2200',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $errorMessage = 'Erreur de validation';

            if ($errors->has('scheduled_at')) {
                $errorMessage = 'La date de publication doit être dans le futur. Veuillez sélectionner une date et heure ultérieure.';
            } elseif ($errors->has('platform')) {
                $errorMessage = 'Veuillez sélectionner une plateforme valide.';
            } elseif ($errors->has('caption')) {
                $errorMessage = 'La légende ne doit pas dépasser 2200 caractères.';
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'errors' => $errors
            ], 422);
        }

        $user = $request->user();
        $media = UserMedia::where('user_id', $user->id)->findOrFail($id);
        $platform = $request->platform;
        $scheduledAt = $request->scheduled_at;
        $caption = $request->caption ?? '';

        try {
            $scheduledTask = \App\Models\ScheduledTask::create([
                'type' => 'media',
                'user_id' => $user->id,
                'media_id' => $media->id,
                'platform' => $platform,
                'caption' => $caption,
                'scheduled_at' => $scheduledAt,
                'status' => 'pending',
                'metadata' => [
                    'media_type' => $media->type,
                    'file_name' => $media->file_name,
                    'file_url' => $media->file_url,
                    'scheduled_by' => $user->id,
                    'created_at' => now()->toIso8601String()
                ]
            ]);

            LogModel::create([
                'user_id' => $user->id,
                'action' => "media_scheduled_to_{$platform}",
                'details' => "Média programmé sur {$platform}: {$media->title} pour le {$scheduledAt}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Publication programmée sur {$platform} pour le " . date('d/m/Y à H:i', strtotime($scheduledAt)),
                'task' => [
                    'id' => $scheduledTask->id,
                    'type' => $scheduledTask->type,
                    'media_id' => $scheduledTask->media_id,
                    'platform' => $scheduledTask->platform,
                    'caption' => $scheduledTask->caption,
                    'scheduled_at' => $scheduledTask->scheduled_at,
                    'status' => $scheduledTask->status
                ],
                'media' => [
                    'id' => $media->id,
                    'file_name' => $media->file_name,
                    'url' => $media->file_url
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur programmation publication', [
                'user_id' => $user->id,
                'media_id' => $media->id,
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la programmation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister les tâches programmées
     */
    public function listScheduledTasks(Request $request)
    {
        $user = $request->user();

        $tasks = \App\Models\ScheduledTask::where('user_id', $user->id)
            ->where('type', 'media')
            ->with('media')
            ->orderBy('scheduled_at', 'asc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'tasks' => $tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'user_id' => $task->user_id,
                    'media_id' => $task->media_id,
                    'media' => $task->media ? [
                        'id' => $task->media->id,
                        'file_name' => $task->media->file_name,
                        'url' => $task->media->file_url,
                        'type' => $task->media->type
                    ] : null,
                    'platform' => $task->platform,
                    'caption' => $task->caption,
                    'scheduled_at' => $task->scheduled_at->toIso8601String(),
                    'status' => $task->status,
                    'created_at' => $task->created_at->toIso8601String()
                ];
            }),
            'pagination' => [
                'total' => $tasks->total(),
                'per_page' => $tasks->perPage(),
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage()
            ]
        ]);
    }

    /**
     * Modifier une tâche programmée
     */
    public function updateScheduledTask(Request $request, $taskId)
    {
        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date|after:now',
            'platform' => 'nullable|string|in:tiktok,instagram,facebook,twitter',
            'caption' => 'nullable|string|max:2200',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        $task = \App\Models\ScheduledTask::where('user_id', $user->id)
            ->where('id', $taskId)
            ->where('type', 'media')
            ->firstOrFail();

        if ($task->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => 'Cette tâche ne peut pas être modifiée car elle n\'est plus en attente'
            ], 400);
        }

        $updateData = ['scheduled_at' => $request->scheduled_at];
        if ($request->has('platform')) {
            $updateData['platform'] = $request->platform;
        }
        if ($request->has('caption')) {
            $updateData['caption'] = $request->caption;
        }

        $task->update($updateData);

        LogModel::create([
            'user_id' => $user->id,
            'action' => 'scheduled_task_updated',
            'details' => "Tâche programmée modifiée: {$task->platform} - {$task->media_id}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tâche programmée modifiée avec succès',
            'task' => [
                'id' => $task->id,
                'media_id' => $task->media_id,
                'platform' => $task->platform,
                'caption' => $task->caption,
                'scheduled_at' => $task->scheduled_at->toIso8601String(),
                'status' => $task->status
            ]
        ]);
    }

    /**
     * Supprimer/annuler une tâche programmée
     */
    public function deleteScheduledTask(Request $request, $taskId)
    {
        $user = $request->user();

        $task = \App\Models\ScheduledTask::where('user_id', $user->id)
            ->where('id', $taskId)
            ->where('type', 'media')
            ->firstOrFail();

        if ($task->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => 'Cette tâche ne peut pas être annulée car elle n\'est plus en attente'
            ], 400);
        }

        $task->delete();

        LogModel::create([
            'user_id' => $user->id,
            'action' => 'scheduled_task_deleted',
            'details' => "Tâche programmée supprimée: {$task->platform} - {$task->media_id}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tâche programmée supprimée avec succès'
        ]);
    }

    private function formatMediaResponse(UserMedia $media): array
    {
        return [
            'id' => $media->id,
            'type' => $media->type,
            'url' => $media->file_url,
            'file_name' => $media->file_name,
            'file_size' => $media->file_size,
            'width' => $media->width,
            'height' => $media->height,
            'duration_seconds' => $media->duration_seconds,
            'provider' => $media->provider,
            'model_used' => $media->model_used,
            'prompt_used' => $media->prompt_used,
            'published_to_tiktok' => $media->published_to_tiktok,
            'published_to_instagram' => $media->published_to_instagram,
            'published_at' => $media->published_at?->toIso8601String(),
            'publication_results' => $media->publication_results,
            'is_published' => $media->published_to_tiktok || $media->published_to_instagram,
            'created_at' => $media->created_at->toIso8601String(),
        ];
    }

    // ============================================================
    // getActiveProviders
    // ============================================================

    public function getActiveProviders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:image,video'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type = $request->type;
        $capability = $type === 'image' ? 'image_generation' : 'video_generation';

        $providers = SystemApiKey::where('is_active', true)
            ->whereJsonContains('capabilities', $capability)
            ->select('id', 'provider', 'model_name', 'config')
            ->orderBy('priority', 'asc')
            ->get();

        $formattedProviders = $providers->map(function ($key) use ($type) {
            return [
                'id' => $key->id,
                'provider' => $key->provider,
                'model_name' => $key->model_name,
                'label' => $this->getProviderLabel($key->provider),
                'icon' => $this->getProviderIcon($key->provider),
                'is_free' => $key->provider === 'pollinations'
            ];
        });

        return response()->json([
            'success' => true,
            'type' => $type,
            'providers' => $formattedProviders
        ]);
    }

    private function getProviderLabel(string $provider): string
    {
        $labels = [
            'pollinations' => 'Pollinations.ai',
            'openai' => 'OpenAI (DALL-E)',
            'google' => 'Google (Imagen/Veo)',
            'elevenlabs' => 'ElevenLabs',
            'replicate' => 'Replicate',
            'stability' => 'Stability AI',
            'runway' => 'Runway',
            'pika' => 'Pika',
            'synthesia' => 'Synthesia',
        ];
        return $labels[$provider] ?? ucfirst($provider);
    }

    private function getProviderIcon(string $provider): string
    {
        $icons = [
            'pollinations' => 'bi-flower1',
            'openai' => 'bi-chat-square',
            'google' => 'bi-google',
            'elevenlabs' => 'bi-mic',
            'replicate' => 'bi-cpu',
            'stability' => 'bi-cloud',
            'runway' => 'bi-film',
            'pika' => 'bi-camera',
            'synthesia' => 'bi-person',
        ];
        return $icons[$provider] ?? 'bi-cpu';
    }
}
