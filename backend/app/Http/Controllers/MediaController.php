<?php
// app/Http/Controllers/MediaController.php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleMedia;
use App\Models\MediaPrompt;
use App\Models\Log as LogModel;
use App\Models\SystemApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MediaController extends Controller
{
    /**
     * Mapping des providers vers leurs modèles par défaut
     */
    private const DEFAULT_MODELS = [
        'mistral' => 'mistral-large-latest',
        'openai' => 'gpt-4o',
        'anthropic' => 'claude-3-sonnet-20241022',
        'deepseek' => 'deepseek-chat',
        'gemini' => 'gemini-2.0-flash',
    ];

    /**
     * Crée un client HTTP avec vérification SSL désactivée
     */
    private function getHttpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions([
            'verify' => false,
            'timeout' => 120,
            'connect_timeout' => 30,
        ]);
    }

    /**
     * Récupère la configuration LLM active
     */
    private function getActiveLlmConfig()
    {
        $cacheKey = 'active_llm_config';

        return Cache::remember($cacheKey, 300, function () {
            $activeKey = SystemApiKey::where('is_active', true)->first();

            if (!$activeKey) {
                throw new \Exception('Aucune clé API active configurée');
            }

            return [
                'provider' => $activeKey->provider,
                'api_key' => $activeKey->api_key,
                'model_name' => self::DEFAULT_MODELS[$activeKey->provider] ?? null,
                'config' => $activeKey->config ?? []
            ];
        });
    }

    /**
     * Appelle le LLM configuré
     */
    private function callLlm(string $prompt, string $modelName = null, int $maxTokens = 4000, float $temperature = 0.7): string
    {
        $config = $this->getActiveLlmConfig();
        $provider = $config['provider'];
        $apiKey = $config['api_key'];
        $model = $modelName ?? $config['model_name'] ?? self::DEFAULT_MODELS[$provider] ?? null;

        if (!$model) {
            throw new \Exception("Aucun modèle disponible pour le provider: {$provider}");
        }

        Log::info('Appel LLM', [
            'provider' => $provider,
            'model' => $model,
            'max_tokens' => $maxTokens
        ]);

        return match ($provider) {
            'mistral' => $this->callMistral($prompt, $apiKey, $model, $maxTokens, $temperature),
            'openai' => $this->callOpenAI($prompt, $apiKey, $model, $maxTokens, $temperature),
            'anthropic' => $this->callAnthropic($prompt, $apiKey, $model, $maxTokens, $temperature),
            'deepseek' => $this->callDeepSeek($prompt, $apiKey, $model, $maxTokens, $temperature),
            'gemini' => $this->callGemini($prompt, $apiKey, $model, $maxTokens, $temperature),
            default => throw new \Exception("Provider non supporté: {$provider}")
        };
    }

    private function callMistral(string $prompt, string $apiKey, string $model, int $maxTokens, float $temperature): string
    {
        $client = $this->getHttpClient();
        $response = $client->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post('https://api.mistral.ai/v1/chat/completions', [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ]);

        if (!$response->successful()) {
            Log::error('Erreur Mistral', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Erreur Mistral API: ' . $response->body());
        }

        return $response->json()['choices'][0]['message']['content'] ?? '';
    }

    private function callOpenAI(string $prompt, string $apiKey, string $model, int $maxTokens, float $temperature): string
    {
        $client = $this->getHttpClient();
        $response = $client->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ]);

        if (!$response->successful()) {
            Log::error('Erreur OpenAI', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Erreur OpenAI API: ' . $response->body());
        }

        return $response->json()['choices'][0]['message']['content'] ?? '';
    }

    private function callAnthropic(string $prompt, string $apiKey, string $model, int $maxTokens, float $temperature): string
    {
        $client = $this->getHttpClient();
        $response = $client->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json'
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ]);

        if (!$response->successful()) {
            Log::error('Erreur Anthropic', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Erreur Anthropic API: ' . $response->body());
        }

        return $response->json()['content'][0]['text'] ?? '';
    }

    private function callDeepSeek(string $prompt, string $apiKey, string $model, int $maxTokens, float $temperature): string
    {
        $client = $this->getHttpClient();
        $response = $client->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post('https://api.deepseek.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ]);

        if (!$response->successful()) {
            Log::error('Erreur DeepSeek', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Erreur DeepSeek API: ' . $response->body());
        }

        return $response->json()['choices'][0]['message']['content'] ?? '';
    }

    private function callGemini(string $prompt, string $apiKey, string $model, int $maxTokens, float $temperature): string
    {
        $client = $this->getHttpClient();
        $response = $client->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'maxOutputTokens' => $maxTokens,
                    'temperature' => $temperature
                ]
            ]
        );

        if (!$response->successful()) {
            Log::error('Erreur Gemini', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Erreur Gemini API: ' . $response->body());
        }

        return $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    // ============================================================
    // MÉTHODES EXISTANTES (Génération d'images et vidéos)
    // ============================================================

    /**
     * Générer des images pour un article
     */
    public function generateImages(Request $request, $articleId)
    {
        $article = $request->user()->articles()->findOrFail($articleId);

        $validator = Validator::make($request->all(), [
            'count' => 'nullable|integer|min:1|max:10',
            'aspect_ratio' => 'nullable|string|in:16:9,9:16,1:1',
            'style' => 'nullable|string|max:100',
            'custom_prompt' => 'nullable|string|max:1000',
            'provider' => 'nullable|string|in:openai,google,elevenlabs,simulation'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $count = $request->count ?? 2;
        $aspectRatio = $request->aspect_ratio ?? '16:9';
        $style = $request->style ?? 'moderne, professionnel';
        $customPrompt = $request->custom_prompt;

        $prompt = $this->buildImagePrompt($article, $customPrompt, $style);

        $mediaPrompt = MediaPrompt::create([
            'article_id' => $article->id,
            'user_id' => $request->user()->id,
            'media_type' => 'image',
            'original_prompt' => $customPrompt ?? 'Généré automatiquement',
            'optimized_prompt' => $prompt,
            'aspect_ratio' => $aspectRatio,
            'style' => $style,
            'count' => $count,
            'status' => 'pending'
        ]);

        $generatedImages = $this->simulateImageGeneration($article, $prompt, $count, $aspectRatio);

        $mediaRecords = [];
        foreach ($generatedImages as $index => $imageData) {
            $media = ArticleMedia::create([
                'article_id' => $article->id,
                'user_id' => $request->user()->id,
                'type' => 'image',
                'file_name' => $imageData['file_name'],
                'file_url' => $imageData['file_url'],
                'file_path' => $imageData['file_path'] ?? null,
                'mime_type' => 'image/png',
                'file_size' => $imageData['file_size'] ?? 0,
                'width' => $imageData['width'] ?? 1024,
                'height' => $imageData['height'] ?? 768,
                'position' => 'inline',
                'order_index' => $index,
                'provider' => $request->provider ?? 'simulation',
                'model_used' => 'simulated',
                'prompt_used' => $prompt,
                'status' => 'completed',
                'api_response' => $imageData['api_response'] ?? null,
                'metadata' => [
                    'aspect_ratio' => $aspectRatio,
                    'style' => $style,
                    'generated_at' => now()->toIso8601String()
                ]
            ]);

            $mediaRecords[] = $media;
        }

        $mediaPrompt->update([
            'results' => array_map(function ($media) {
                return $media->id;
            }, $mediaRecords),
            'is_success' => true,
            'status' => 'completed'
        ]);

        $article->update([
            'has_media' => true,
            'media_metadata' => [
                'has_images' => true,
                'image_count' => count($mediaRecords),
                'last_generated_at' => now()->toIso8601String()
            ]
        ]);

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'images_generated',
            'details' => "{$count} images générées pour l'article: {$article->titre}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$count} images générées avec succès",
            'prompt_id' => $mediaPrompt->id,
            'images' => array_map(function ($media) {
                return [
                    'id' => $media->id,
                    'url' => $media->file_url,
                    'file_name' => $media->file_name,
                    'width' => $media->width,
                    'height' => $media->height,
                    'is_hero' => $media->is_hero
                ];
            }, $mediaRecords)
        ]);
    }

    private function buildImagePrompt($article, $customPrompt, $style): string
    {
        if ($customPrompt) {
            return $customPrompt;
        }

        $titre = $article->titre;
        $sujet = $article->mot_cle_principal ?? $titre;

        return sprintf(
            "Crée une illustration professionnelle et moderne pour un article intitulé \"%s\". " .
                "Style: %s. " .
                "L'image doit être accrocheuse, adaptée à un blog professionnel. " .
                "Elle doit transmettre les concepts clés de l'article de manière visuelle et élégante. " .
                "Pas de texte dans l'image, juste une illustration évocatrice. " .
                "Format: haute qualité, tons professionnels, composition équilibrée.",
            $titre,
            $style
        );
    }

    private function simulateImageGeneration($article, $prompt, $count, $aspectRatio): array
    {
        $images = [];
        $dimensions = $this->getDimensions($aspectRatio);

        for ($i = 0; $i < $count; $i++) {
            // Utiliser un service alternatif
            // Option: UI Avatars (pour des images de profil)
            $seed = md5($prompt . $i . time());

            // Utiliser l'API de placehold.co
            $url = sprintf(
                'https://placehold.co/%dx%d/28b188/FFFFFF?text=Image+%d',
                $dimensions['width'],
                $dimensions['height'],
                $i + 1
            );

            $images[] = [
                'file_name' => sprintf('image_%d_%s.png', $i + 1, now()->timestamp),
                'file_url' => $url,
                'file_path' => null,
                'file_size' => 0,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'api_response' => [
                    'seed' => $seed,
                    'generated_at' => now()->toIso8601String(),
                    'service' => 'placehold'
                ]
            ];
        }

        return $images;
    }

    private function getDimensions($aspectRatio): array
    {
        return match ($aspectRatio) {
            '9:16' => ['width' => 768, 'height' => 1366],
            '1:1' => ['width' => 1024, 'height' => 1024],
            default => ['width' => 1366, 'height' => 768],
        };
    }

    /**
     * Générer une vidéo pour un article
     */
    public function generateVideo(Request $request, $articleId)
    {
        $article = $request->user()->articles()->findOrFail($articleId);

        $validator = Validator::make($request->all(), [
            'duration' => 'nullable|integer|min:5|max:30',
            'aspect_ratio' => 'nullable|string|in:16:9,9:16',
            'style' => 'nullable|string|max:100',
            'custom_prompt' => 'nullable|string|max:1000',
            'provider' => 'nullable|string|in:google,openai,elevenlabs,simulation'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $duration = $request->duration ?? 8;
        $aspectRatio = $request->aspect_ratio ?? '16:9';
        $style = $request->style ?? 'cinematographique, professionnel';
        $customPrompt = $request->custom_prompt;

        $prompt = $this->buildVideoPrompt($article, $customPrompt, $style);

        $mediaPrompt = MediaPrompt::create([
            'article_id' => $article->id,
            'user_id' => $request->user()->id,
            'media_type' => 'video',
            'original_prompt' => $customPrompt ?? 'Généré automatiquement',
            'optimized_prompt' => $prompt,
            'aspect_ratio' => $aspectRatio,
            'duration' => $duration,
            'style' => $style,
            'count' => 1,
            'status' => 'pending'
        ]);

        $videoData = $this->simulateVideoGeneration($article, $prompt, $duration, $aspectRatio);

        $media = ArticleMedia::create([
            'article_id' => $article->id,
            'user_id' => $request->user()->id,
            'type' => 'video',
            'file_name' => $videoData['file_name'],
            'file_url' => $videoData['file_url'],
            'file_path' => $videoData['file_path'] ?? null,
            'mime_type' => 'video/mp4',
            'file_size' => $videoData['file_size'] ?? 0,
            'width' => $videoData['width'] ?? 1366,
            'height' => $videoData['height'] ?? 768,
            'duration_seconds' => $duration,
            'position' => 'inline',
            'order_index' => 0,
            'provider' => $request->provider ?? 'simulation',
            'model_used' => 'simulated_video',
            'prompt_used' => $prompt,
            'status' => 'completed',
            'api_response' => $videoData['api_response'] ?? null,
            'metadata' => [
                'duration' => $duration,
                'aspect_ratio' => $aspectRatio,
                'style' => $style,
                'generated_at' => now()->toIso8601String()
            ]
        ]);

        $mediaPrompt->update([
            'results' => [$media->id],
            'is_success' => true,
            'status' => 'completed'
        ]);

        $article->update([
            'has_media' => true,
            'media_metadata' => array_merge(
                $article->media_metadata ?? [],
                [
                    'has_videos' => true,
                    'video_count' => 1,
                    'last_video_generated_at' => now()->toIso8601String()
                ]
            )
        ]);

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'video_generated',
            'details' => "Vidéo générée pour l'article: {$article->titre}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vidéo générée avec succès',
            'prompt_id' => $mediaPrompt->id,
            'video' => [
                'id' => $media->id,
                'url' => $media->file_url,
                'file_name' => $media->file_name,
                'duration' => $media->duration_seconds
            ]
        ]);
    }

    private function buildVideoPrompt($article, $customPrompt, $style): string
    {
        if ($customPrompt) {
            return $customPrompt;
        }

        $titre = $article->titre;
        $contenu = Str::limit(strip_tags($article->contenu_md ?? ''), 200);

        return sprintf(
            "Crée une vidéo professionnelle et engageante pour un article intitulé \"%s\". " .
                "Style: %s. " .
                "Contenu de l'article: %s. " .
                "La vidéo doit être visuellement attrayante, avec des transitions fluides et un rythme dynamique. " .
                "Elle doit captiver l'attention et transmettre l'essence de l'article.",
            $titre,
            $style,
            $contenu
        );
    }

    private function simulateVideoGeneration($article, $prompt, $duration, $aspectRatio): array
    {
        $dimensions = $this->getDimensions($aspectRatio);

        return [
            'file_name' => sprintf('video_%s.mp4', now()->timestamp),
            'file_url' => 'https://sample-videos.com/video321/mp4/720/big_buck_bunny_720p_1mb.mp4',
            'file_path' => null,
            'file_size' => rand(1000000, 5000000),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'api_response' => [
                'generated_at' => now()->toIso8601String(),
                'duration' => $duration
            ]
        ];
    }

    // ============================================================
    // MÉTHODES DE GESTION DES MÉDIAS
    // ============================================================

    /**
     * Définir une image comme image principale (hero)
     */
    public function setHeroImage(Request $request, $articleId, $mediaId)
    {
        $article = $request->user()->articles()->findOrFail($articleId);
        $media = ArticleMedia::where('article_id', $articleId)
            ->where('id', $mediaId)
            ->where('type', 'image')
            ->firstOrFail();

        ArticleMedia::where('article_id', $articleId)
            ->where('type', 'image')
            ->update(['is_hero' => false]);

        $media->update(['is_hero' => true, 'position' => 'hero']);

        $article->update([
            'hero_image_url' => $media->file_url,
            'hero_image_id' => $media->id
        ]);

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'hero_image_set',
            'details' => "Image principale définie pour l'article: {$article->titre}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Image principale définie avec succès'
        ]);
    }

    /**
     * Supprimer un média
     */
    public function deleteMedia(Request $request, $articleId, $mediaId)
    {
        $media = ArticleMedia::where('article_id', $articleId)
            ->where('id', $mediaId)
            ->firstOrFail();

        $article = $request->user()->articles()->findOrFail($articleId);

        if ($media->is_hero) {
            $article->update([
                'hero_image_url' => null,
                'hero_image_id' => null
            ]);
        }

        $media->delete();

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'media_deleted',
            'details' => "Média supprimé de l'article: {$article->titre}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Média supprimé avec succès'
        ]);
    }

    /**
     * Récupérer tous les médias d'un article
     */
    public function getArticleMedia(Request $request, $articleId)
    {
        $article = $request->user()->articles()->findOrFail($articleId);

        $media = ArticleMedia::where('article_id', $articleId)
            ->orderBy('type')
            ->orderBy('order_index')
            ->get();

        return response()->json([
            'media' => $media->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'url' => $item->file_url,
                    'file_name' => $item->file_name,
                    'is_hero' => $item->is_hero,
                    'is_integrated' => $item->is_integrated,
                    'metadata' => $item->metadata,
                    'created_at' => $item->created_at
                ];
            })
        ]);
    }

    // ============================================================
    // NOUVELLE MÉTHODE : INTÉGRATION AVEC LLM
    // ============================================================

    /**
     * Intégrer les médias dans l'article en utilisant le LLM
     */
    public function integrateMediaWithLlm(Request $request, $articleId)
    {
        $article = $request->user()->articles()->findOrFail($articleId);

        $validator = Validator::make($request->all(), [
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'exists:article_media,id',
            'position' => 'nullable|string|in:top,bottom,inline'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $position = $request->position ?? 'inline';

        $mediaItems = ArticleMedia::whereIn('id', $request->media_ids)
            ->where('article_id', $articleId)
            ->get();

        if ($mediaItems->isEmpty()) {
            return response()->json(['error' => 'Aucun média trouvé'], 404);
        }

        try {
            $prompt = $this->buildMediaIntegrationPrompt($article, $mediaItems, $position);
            $response = $this->callLlm($prompt, null, 4000, 0.3);
            $newContent = $this->extractArticleFromResponse($response);

            if (empty($newContent)) {
                throw new \Exception('Impossible d\'extraire le contenu de la réponse du LLM');
            }

            $article->update(['contenu_md' => $newContent]);

            foreach ($mediaItems as $media) {
                $media->update(['is_integrated' => true]);
            }

            LogModel::create([
                'user_id' => $request->user()->id,
                'action' => 'media_integrated_with_llm',
                'details' => "Médias intégrés avec LLM pour l'article: {$article->titre}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Médias intégrés avec succès via LLM',
                'article' => $article,
                'media_count' => $mediaItems->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur intégration médias avec LLM', [
                'article_id' => $articleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'intégration des médias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construire le prompt pour l'intégration des médias
     */
    private function buildMediaIntegrationPrompt($article, $mediaItems, $position): string
    {
        $titre = $article->titre;
        $contenuActuel = $article->contenu_md;

        $mediasInfo = [];
        foreach ($mediaItems as $index => $media) {
            $type = $media->type === 'image' ? 'Image' : 'Vidéo';
            $filename = $media->file_name ?? "média_{$index}";
            $url = $media->file_url;
            $suggestionAlt = $this->generateAltSuggestion($media, $article);

            $mediasInfo[] = [
                'id' => $media->id,
                'type' => $type,
                'url' => $url,
                'filename' => $filename,
                'suggestion_alt' => $suggestionAlt,
                'width' => $media->width ?? 800,
                'height' => $media->height ?? 600,
            ];
        }

        $mediasJson = json_encode($mediasInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $positionDesc = match ($position) {
            'top' => 'en tête de l\'article (avant tout le contenu)',
            'bottom' => 'à la fin de l\'article (après tout le contenu)',
            default => 'de manière naturelle et pertinente dans le corps de l\'article (par exemple après un H2 qui parle du sujet de l\'image)'
        };

        return <<<PROMPT
        Tu es un expert en intégration de médias dans des articles. Ta mission est d'insérer des images et/ou vidéos dans l'article ci-dessous.

        [ARTICLE]
        Titre: {$titre}

        Contenu actuel:

        [MÉDIAS À INTÉGRER]
        Voici les médias disponibles avec leurs informations:
        {$mediasJson}

        [INSTRUCTIONS]
        1. **NE MODIFIE PAS** le texte original de l'article. Tu ne dois que  les balises média.
        2. Pour chaque média, génère une description (alt) pertinente en lien avec le sujet de l'article.
        3. Pour les images, utilise le format Markdown: `![description](url)`
        - Si l'image est large, ajoute `<!-- width="800" -->` après l'image pour la redimensionner
        4. Pour les vidéos, utilise le format: `<video controls><source src="url" type="video/mp4"></video>`
        5. Place chaque média à l'endroit le plus pertinent du texte:
        - Si c'est une image qui illustre une section spécifique, place-la après le H2 correspondant
        - Si c'est une image générale, place-la après l'introduction
        - Pour le positionnement "{$positionDesc}"
        6. **NE CHANGE PAS** le contenu textuel, ne réécris pas les paragraphes.
        7. **NE MET PAS** de caractère # au début de ta réponse.
        8. Retourne UNIQUEMENT le contenu Markdown complet de l'article avec les médias insérés.

        [FORMAT DE RÉPONSE]
        Retourne uniquement le contenu Markdown de l'article avec les médias ajoutés aux bons endroits. Ne met pas de texte avant ou après, et ne commence pas par #.

        Contenu de l'article avec médias insérés:
        PROMPT;
    }

    /**
     * Générer une suggestion de description (alt) pour un média
     */
    private function generateAltSuggestion($media, $article): string
    {
        $base = "Illustration pour l'article: {$article->titre}";

        if ($media->type === 'image') {
            return $base . ' - Image d\'illustration';
        }

        return $base . ' - Vidéo d\'illustration';
    }

    /**
     * Extraire le contenu de l'article de la réponse du LLM
     */
    private function extractArticleFromResponse(string $response): string
    {
        // Nettoyer la réponse
        $response = trim($response);

        // Si la réponse commence par un marqueur de code, l'enlever
        if (str_starts_with($response, '```markdown')) {
            $response = substr($response, 11);
        }
        if (str_starts_with($response, '```')) {
            $response = substr($response, 3);
        }
        if (str_ends_with($response, '```')) {
            $response = substr($response, 0, -3);
        }

        // Si la réponse contient "Contenu de l'article avec médias insérés:", prendre ce qui suit
        if (str_contains($response, 'Contenu de l\'article avec médias insérés:')) {
            $parts = explode('Contenu de l\'article avec médias insérés:', $response);
            $response = trim(end($parts));
        }

        // SUPPRIMER LE "#" AU DÉBUT SI PRÉSENT
        $response = ltrim($response, "#");
        $response = trim($response);

        // Si la réponse commence par un # suivi d'un espace, l'enlever aussi
        $response = preg_replace('/^#\s+/', '', $response);

        return trim($response);
    }
}
