<?php
// app/Http/Controllers/LlmTestController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SystemApiKey;
use App\Models\Site;

class LlmTestController extends Controller
{
    /**
     * Modèles valides par provider
     */
    private array $validModels = [
        'mistral' => ['mistral-large-latest', 'mistral-small-latest', 'mistral-tiny-latest', 'open-mistral-7b', 'open-mixtral-8x7b', 'codestral-latest'],
        'openai' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo'],
        'anthropic' => ['claude-3-5-sonnet-20241022', 'claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307'],
        'deepseek' => ['deepseek-chat', 'deepseek-coder'],
        'gemini' => ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash']
    ];

    /**
     * Modèles par défaut par provider
     */
    private array $defaultModels = [
        'mistral' => 'mistral-large-latest',
        'openai' => 'gpt-4o',
        'anthropic' => 'claude-3-5-sonnet-20241022',
        'deepseek' => 'deepseek-chat',
        'gemini' => 'gemini-2.0-flash'
    ];

    /**
     * Valide et corrige le nom du modèle
     */
    private function validateModelName(string $provider, ?string $modelName): string
    {
        // Si pas de modèle, retourner le défaut
        if (empty($modelName)) {
            return $this->defaultModels[$provider] ?? 'mistral-large-latest';
        }

        // Nettoyer le nom du modèle (enlever les espaces, etc.)
        $cleanModel = trim($modelName);

        // Vérifier si le modèle est valide
        $validList = $this->validModels[$provider] ?? [];

        if (in_array($cleanModel, $validList)) {
            return $cleanModel;
        }

        // Log l'erreur et retourner le défaut
        Log::warning("Modèle invalide pour {$provider}: '{$cleanModel}', utilisation du modèle par défaut '{$this->defaultModels[$provider]}'");

        return $this->defaultModels[$provider] ?? 'mistral-large-latest';
    }

    /**
     * Tester la connexion directe avec le LLM actif
     */
    public function testDirectCall(Request $request)
    {
        // Récupérer la clé API active
        $activeKey = SystemApiKey::where('is_active', true)->first();

        if (!$activeKey) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune clé API active configurée'
            ], 400);
        }

        $provider = $activeKey->provider;
        $apiKey = $activeKey->api_key;

        // Valider et corriger le nom du modèle
        $modelName = $this->validateModelName($provider, $activeKey->model_name);

        $prompt = $request->input('prompt', 'Dis "Bonjour, je fonctionne correctement !" en français.');

        try {
            $startTime = microtime(true);

            $response = $this->callLlm($provider, $apiKey, $modelName, $prompt);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success' => true,
                'provider' => $provider,
                'model_used' => $modelName,
                'model_original' => $activeKey->model_name,
                'response' => $response,
                'duration_ms' => $duration,
                'prompt' => $prompt
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $provider,
                'model_attempted' => $modelName
            ], 500);
        }
    }

    /**
     * Tester l'analyse d'un site (similaire à l'agent Python)
     */
    public function testAnalyzeSite(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'description' => 'nullable|string'
        ]);

        $activeKey = SystemApiKey::where('is_active', true)->first();

        if (!$activeKey) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune clé API active configurée'
            ], 400);
        }

        $provider = $activeKey->provider;
        $apiKey = $activeKey->api_key;

        // Valider et corriger le nom du modèle
        $modelName = $this->validateModelName($provider, $activeKey->model_name);

        // Scraper le site
        $content = $this->scrapeWebsite($request->url);

        if (!$content) {
            return response()->json([
                'success' => false,
                'error' => 'Impossible de récupérer le contenu du site'
            ], 400);
        }

        // Construire le prompt d'analyse
        $prompt = $this->buildAnalysisPrompt($request->url, $content, $request->description);

        try {
            $response = $this->callLlm($provider, $apiKey, $modelName, $prompt);

            // Extraire le JSON de la réponse
            $jsonData = $this->extractJson($response);

            return response()->json([
                'success' => true,
                'provider' => $provider,
                'model_used' => $modelName,
                'analysis' => $jsonData,
                'raw_response' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $provider,
                'model_attempted' => $modelName
            ], 500);
        }
    }

    /**
     * Tester la génération d'article (similaire à l'agent Python)
     */
    public function testGenerateArticle(Request $request)
    {
        $request->validate([
            'sujet' => 'required|string',
            'mot_cle_principal' => 'required|string',
            'contexte' => 'required|array'
        ]);

        $activeKey = SystemApiKey::where('is_active', true)->first();

        if (!$activeKey) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune clé API active configurée'
            ], 400);
        }

        $provider = $activeKey->provider;
        $apiKey = $activeKey->api_key;

        // Valider et corriger le nom du modèle
        $modelName = $this->validateModelName($provider, $activeKey->model_name);

        // Construire le prompt de rédaction
        $prompt = $this->buildArticlePrompt(
            $request->contexte,
            $request->sujet,
            $request->mot_cle_principal,
            $request->longueur_cible ?? 800
        );

        try {
            $startTime = microtime(true);

            $response = $this->callLlm(
                $provider,
                $apiKey,
                $modelName,
                $prompt,
                4000
            );

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Extraire l'article structuré
            $article = $this->parseArticleResponse($response);

            return response()->json([
                'success' => true,
                'provider' => $provider,
                'model_used' => $modelName,
                'article' => $article,
                'duration_ms' => $duration
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $provider,
                'model_attempted' => $modelName
            ], 500);
        }
    }

    /**
     * Appel générique à un LLM
     */
    private function callLlm(string $provider, string $apiKey, string $model, string $prompt, int $maxTokens = 2000): string
    {
        switch ($provider) {
            case 'mistral':
                return $this->callMistral($apiKey, $model, $prompt, $maxTokens);
            case 'openai':
                return $this->callOpenAI($apiKey, $model, $prompt, $maxTokens);
            case 'anthropic':
                return $this->callAnthropic($apiKey, $model, $prompt, $maxTokens);
            case 'deepseek':
                return $this->callDeepSeek($apiKey, $model, $prompt, $maxTokens);
            case 'gemini':
                return $this->callGemini($apiKey, $model, $prompt, $maxTokens);
            default:
                throw new \Exception("Provider non supporté: {$provider}");
        }
    }

    private function callMistral(string $apiKey, string $model, string $prompt, int $maxTokens): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json'
        ])->withOptions([
            'verify' => false
        ])->timeout(60)->post('https://api.mistral.ai/v1/chat/completions', [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature' => 0.7
        ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? $response->body();
            throw new \Exception("Mistral API error ({$response->status()}): {$errorMessage}");
        }

        return $response->json()['choices'][0]['message']['content'];
    }

    private function callOpenAI(string $apiKey, string $model, string $prompt, int $maxTokens): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json'
        ])->withOptions([
            'verify' => false
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature' => 0.7
        ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            throw new \Exception("OpenAI API error ({$response->status()}): {$errorMessage}");
        }

        return $response->json()['choices'][0]['message']['content'];
    }

    private function callAnthropic(string $apiKey, string $model, string $prompt, int $maxTokens): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json'
        ])->withOptions([
            'verify' => false
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            throw new \Exception("Anthropic API error ({$response->status()}): {$errorMessage}");
        }

        return $response->json()['content'][0]['text'];
    }

    private function callDeepSeek(string $apiKey, string $model, string $prompt, int $maxTokens): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json'
        ])->withOptions([
            'verify' => false
        ])->timeout(60)->post('https://api.deepseek.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature' => 0.7
        ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            throw new \Exception("DeepSeek API error ({$response->status()}): {$errorMessage}");
        }

        return $response->json()['choices'][0]['message']['content'];
    }

    private function callGemini(string $apiKey, string $model, string $prompt, int $maxTokens): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::withOptions([
            'verify' => false
        ])->timeout(60)->post($url, [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature' => 0.7
            ]
        ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            throw new \Exception("Gemini API error ({$response->status()}): {$errorMessage}");
        }

        return $response->json()['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Scraper un site web
     */
    private function scrapeWebsite(string $url): ?string
    {
        try {
            $html = Http::withOptions(['verify' => false])->timeout(15)->get($url)->body();

            // Supprimer les balises script, style, etc.
            $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
            $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
            $html = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $html);
            $html = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $html);

            // Extraire le texte
            $text = strip_tags($html);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            // Limiter à 6000 caractères
            return substr($text, 0, 6000);
        } catch (\Exception $e) {
            Log::error("Scraping error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Construire le prompt d'analyse
     */
    private function buildAnalysisPrompt(string $url, string $content, ?string $description): string
    {
        $descriptionPart = $description ? "[DESCRIPTION MANUELLE]\n{$description}\n\n" : "";

        return "Tu es un expert en analyse editoriale et SEO.

[CONTENU DU SITE]
URL : {$url}
{$descriptionPart}[CONTENU EXTRAIT]
{$content}

[FORMAT JSON OBLIGATOIRE - REPONDS UNIQUEMENT AVEC CE JSON, SANS AUTRE TEXTE]
{
  \"url_analysee\": \"{$url}\",
  \"secteur_activite\": \"...\",
  \"ton_editorial\": {
    \"principal\": \"...\",
    \"niveau_langue\": \"...\"
  },
  \"themes_suggeres_pour_articles\": [
    {\"titre\": \"Theme 1\", \"mot_cle_principal\": \"mot-cle-1\"},
    {\"titre\": \"Theme 2\", \"mot_cle_principal\": \"mot-cle-2\"},
    {\"titre\": \"Theme 3\", \"mot_cle_principal\": \"mot-cle-3\"},
    {\"titre\": \"Theme 4\", \"mot_cle_principal\": \"mot-cle-4\"},
    {\"titre\": \"Theme 5\", \"mot_cle_principal\": \"mot-cle-5\"}
  ]
}

Retourne uniquement le JSON valide, sans texte supplementaire.";
    }

    /**
     * Construire le prompt de rédaction
     */
    private function buildArticlePrompt(array $contexte, string $sujet, string $motCle, int $longueur): string
    {
        return "Tu es un redacteur web expert en SEO.

[CONTEXTE EDITORIAL]
Secteur: " . ($contexte['secteur_activite'] ?? 'Non spécifié') . "
Ton: " . ($contexte['ton_editorial']['principal'] ?? 'Professionnel') . "

[BRIEF ARTICLE]
Sujet: {$sujet}
Mot-cle principal: {$motCle}
Longueur cible: {$longueur} mots

[FORMAT DE SORTIE - JSON SEULEMENT]
{
  \"titre\": \"Titre de l'article avec le mot-cle principal\",
  \"meta_desc\": \"Meta description de 140-155 caracteres\",
  \"slug\": \"slug-de-larticle\",
  \"categorie\": \"Blog\",
  \"tags\": [\"tag1\", \"tag2\", \"tag3\"],
  \"contenu_md\": \"# Titre\\n\\n## Introduction\\n\\nContenu markdown...\\n\\n## Conclusion\\n\\nContenu...\"
}

Retourne uniquement le JSON valide, sans autre texte.";
    }

    /**
     * Extraire le JSON d'une réponse
     */
    private function extractJson(string $response): array
    {
        $response = trim($response);

        // Supprimer les balises code
        if (str_starts_with($response, '```json')) {
            $response = substr($response, 7);
        }
        if (str_starts_with($response, '```')) {
            $response = substr($response, 3);
        }
        if (str_ends_with($response, '```')) {
            $response = substr($response, 0, -3);
        }

        $decoded = json_decode(trim($response), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Erreur JSON: " . json_last_error_msg() . " - Réponse brute: " . substr($response, 0, 200));
        }

        return $decoded;
    }

    /**
     * Parser la réponse d'article
     */
    private function parseArticleResponse(string $response): array
    {
        $json = $this->extractJson($response);

        return [
            'titre' => $json['titre'] ?? '',
            'meta_desc' => $json['meta_desc'] ?? '',
            'slug' => $json['slug'] ?? '',
            'categorie' => $json['categorie'] ?? 'Blog',
            'tags' => $json['tags'] ?? [],
            'contenu_md' => $json['contenu_md'] ?? ''
        ];
    }

    /**
     * Tester la publication directe d'un article sur WordPress
     */
    public function testPublishArticle(Request $request)
    {
        $request->validate([
            'wp_url' => 'required|url',
            'wp_username' => 'required|string',
            'wp_password' => 'required|string',
            'titre' => 'required|string',
            'contenu' => 'required|string',
            'meta_desc' => 'nullable|string',
            'slug' => 'nullable|string',
            'categorie' => 'nullable|string',
            'tags' => 'nullable|array'
        ]);

        $wpUrl = rtrim($request->wp_url, '/');
        $username = $request->wp_username;
        $password = $request->wp_password;

        // Nettoyer les données
        $titre = trim($request->titre);
        $metaDesc = trim($request->meta_desc ?? '') ?: substr($titre, 0, 155);
        $slug = $request->slug ?: \Illuminate\Support\Str::slug($titre);

        // Convertir le contenu Markdown en HTML (simple pour le test)
        $contenuHtml = $this->simpleMarkdownToHtml($request->contenu);

        // Construction du payload WordPress
        $data = [
            'title' => $titre,
            'content' => $contenuHtml,
            'status' => 'publish',
            'slug' => $slug,
            'excerpt' => $metaDesc,
        ];

        //  la catégorie si spécifiée
        if ($request->categorie) {
            $categoryId = $this->getOrCreateCategory($wpUrl, $username, $password, $request->categorie);
            if ($categoryId) {
                $data['categories'] = [$categoryId];
            }
        }

        //  les tags si spécifiés
        if ($request->tags && count($request->tags) > 0) {
            $tagIds = $this->getOrCreateTags($wpUrl, $username, $password, $request->tags);
            if (count($tagIds) > 0) {
                $data['tags'] = $tagIds;
            }
        }

        try {
            $startTime = microtime(true);

            $response = Http::withOptions(['verify' => false])
                ->withBasicAuth($username, $password)
                ->timeout(30)
                ->post($wpUrl . '/wp-json/wp/v2/posts', $data);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $result = $response->json();

                return response()->json([
                    'success' => true,
                    'message' => 'Article publié avec succès sur WordPress',
                    'post_id' => $result['id'],
                    'url' => $result['link'],
                    'duration_ms' => $duration
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Erreur WordPress: ' . ($response->json()['message'] ?? $response->status()),
                'status_code' => $response->status(),
                'response' => $response->body()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tester la publication directe en utilisant la configuration WordPress d'un site existant
     */
    public function testPublishArticleWithSiteId(Request $request)
    {
        $request->validate([
            'site_id' => 'required|exists:sites,id',
            'titre' => 'required|string',
            'contenu' => 'required|string',
            'meta_desc' => 'nullable|string',
            'slug' => 'nullable|string',
            'categorie' => 'nullable|string',
            'tags' => 'nullable|array'
        ]);

        // Récupérer le site et sa configuration WordPress
        $site = Site::with('wordpressConfig')->findOrFail($request->site_id);

        if (!$site->wordpressConfig || !$site->wordpressConfig->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune configuration WordPress active pour ce site'
            ], 400);
        }

        $config = $site->wordpressConfig;
        $wpUrl = rtrim($config->wp_url, '/');
        $username = $config->wp_username;
        $password = $config->wp_app_password; // Déjà déchiffré par l'accesseur

        // Nettoyer les données
        $titre = trim($request->titre);
        $metaDesc = trim($request->meta_desc ?? '') ?: substr($titre, 0, 155);
        $slug = $request->slug ?: \Illuminate\Support\Str::slug($titre);

        // Convertir le contenu Markdown en HTML
        $contenuHtml = $this->simpleMarkdownToHtml($request->contenu);

        // Construction du payload WordPress
        $data = [
            'title' => $titre,
            'content' => $contenuHtml,
            'status' => 'publish',
            'slug' => $slug,
            'excerpt' => $metaDesc,
        ];

        //  la catégorie si spécifiée
        if ($request->categorie) {
            $categoryId = $this->getOrCreateCategory($wpUrl, $username, $password, $request->categorie);
            if ($categoryId) {
                $data['categories'] = [$categoryId];
            }
        }

        //  les tags si spécifiés
        if ($request->tags && count($request->tags) > 0) {
            $tagIds = $this->getOrCreateTags($wpUrl, $username, $password, $request->tags);
            if (count($tagIds) > 0) {
                $data['tags'] = $tagIds;
            }
        }

        try {
            $startTime = microtime(true);

            $response = Http::withOptions(['verify' => false])
                ->withBasicAuth($username, $password)
                ->timeout(30)
                ->post($wpUrl . '/wp-json/wp/v2/posts', $data);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $result = $response->json();

                // Mettre à jour le compteur d'utilisation
                $config->increment('use_count');
                $config->update(['last_used_at' => now()]);

                return response()->json([
                    'success' => true,
                    'message' => 'Article publié avec succès sur WordPress',
                    'site_id' => $site->id,
                    'site_url' => $site->url,
                    'wp_config' => [
                        'url' => $wpUrl,
                        'username' => $username
                    ],
                    'post_id' => $result['id'],
                    'url' => $result['link'],
                    'duration_ms' => $duration
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Erreur WordPress: ' . ($response->json()['message'] ?? $response->status()),
                'status_code' => $response->status(),
                'response' => $response->body()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tester la publication d'un article généré par le LLM
     */
    public function testGenerateAndPublish(Request $request)
    {
        $request->validate([
            'site_id' => 'required|exists:sites,id',
            'sujet' => 'required|string',
            'mot_cle_principal' => 'required|string',
            'longueur_cible' => 'nullable|integer|min:300|max:5000'
        ]);

        // 1. Récupérer le site et son contexte
        $site = Site::with('wordpressConfig')->findOrFail($request->site_id);

        if (!$site->wordpressConfig || !$site->wordpressConfig->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune configuration WordPress active pour ce site'
            ], 400);
        }

        if (!$site->contexte_json) {
            return response()->json([
                'success' => false,
                'error' => 'Le site n\'a pas encore été analysé'
            ], 400);
        }

        // 2. Récupérer la clé API active
        $activeKey = SystemApiKey::where('is_active', true)->first();

        if (!$activeKey) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune clé API active configurée'
            ], 400);
        }

        $provider = $activeKey->provider;
        $apiKey = $activeKey->api_key;
        $modelName = $this->validateModelName($provider, $activeKey->model_name);

        // 3. Générer l'article via LLM
        $prompt = $this->buildArticlePrompt(
            $site->contexte_json,
            $request->sujet,
            $request->mot_cle_principal,
            $request->longueur_cible ?? 800
        );

        try {
            $startTime = microtime(true);

            $response = $this->callLlm($provider, $apiKey, $modelName, $prompt, 4000);
            $articleData = $this->parseArticleResponse($response);

            $generationDuration = round((microtime(true) - $startTime) * 1000, 2);

            // 4. Publier l'article généré
            $wpConfig = $site->wordpressConfig;
            $wpUrl = rtrim($wpConfig->wp_url, '/');

            $contenuHtml = $this->simpleMarkdownToHtml($articleData['contenu_md']);

            $publishData = [
                'title' => $articleData['titre'],
                'content' => $contenuHtml,
                'status' => 'publish',
                'slug' => $articleData['slug'],
                'excerpt' => $articleData['meta_desc'],
            ];

            //  la catégorie
            if (!empty($articleData['categorie'])) {
                $categoryId = $this->getOrCreateCategory($wpUrl, $wpConfig->wp_username, $wpConfig->wp_app_password, $articleData['categorie']);
                if ($categoryId) {
                    $publishData['categories'] = [$categoryId];
                }
            }

            //  les tags
            if (!empty($articleData['tags']) && count($articleData['tags']) > 0) {
                $tagIds = $this->getOrCreateTags($wpUrl, $wpConfig->wp_username, $wpConfig->wp_app_password, $articleData['tags']);
                if (count($tagIds) > 0) {
                    $publishData['tags'] = $tagIds;
                }
            }

            $publishStart = microtime(true);

            $publishResponse = Http::withOptions(['verify' => false])
                ->withBasicAuth($wpConfig->wp_username, $wpConfig->wp_app_password)
                ->timeout(30)
                ->post($wpUrl . '/wp-json/wp/v2/posts', $publishData);

            $publishDuration = round((microtime(true) - $publishStart) * 1000, 2);
            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);

            if ($publishResponse->successful()) {
                $result = $publishResponse->json();

                // Mettre à jour le compteur d'utilisation
                $wpConfig->increment('use_count');
                $wpConfig->update(['last_used_at' => now()]);

                return response()->json([
                    'success' => true,
                    'message' => 'Article généré et publié avec succès',
                    'provider' => $provider,
                    'model_used' => $modelName,
                    'generation' => [
                        'duration_ms' => $generationDuration,
                        'article' => $articleData
                    ],
                    'publication' => [
                        'duration_ms' => $publishDuration,
                        'post_id' => $result['id'],
                        'url' => $result['link']
                    ],
                    'total_duration_ms' => $totalDuration
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la publication: ' . ($publishResponse->json()['message'] ?? $publishResponse->status()),
                'generated_article' => $articleData
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Conversion simple Markdown vers HTML (pour le test)
     */
    private function simpleMarkdownToHtml(string $markdown): string
    {
        $html = $markdown;

        // Titres
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // Gras et italique
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Listes
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        // Paragraphes
        $lines = explode("\n", $html);
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !preg_match('/^<[^>]+>/', $line)) {
                $result[] = '<p>' . $line . '</p>';
            } else {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Récupérer ou créer une catégorie WordPress
     */
    private function getOrCreateCategory(string $wpUrl, string $username, string $password, string $categoryName): ?int
    {
        // Chercher la catégorie existante
        $searchResponse = Http::withOptions(['verify' => false])
            ->withBasicAuth($username, $password)
            ->get($wpUrl . '/wp-json/wp/v2/categories', [
                'search' => $categoryName
            ]);

        if ($searchResponse->successful()) {
            $categories = $searchResponse->json();
            if (!empty($categories)) {
                return $categories[0]['id'];
            }
        }

        // Créer une nouvelle catégorie
        $createResponse = Http::withOptions(['verify' => false])
            ->withBasicAuth($username, $password)
            ->post($wpUrl . '/wp-json/wp/v2/categories', [
                'name' => $categoryName
            ]);

        if ($createResponse->successful()) {
            return $createResponse->json()['id'];
        }

        return null;
    }

    /**
     * Récupérer ou créer des tags WordPress
     */
    private function getOrCreateTags(string $wpUrl, string $username, string $password, array $tagNames): array
    {
        $tagIds = [];

        foreach (array_slice($tagNames, 0, 5) as $tagName) {
            // Chercher le tag existant
            $searchResponse = Http::withOptions(['verify' => false])
                ->withBasicAuth($username, $password)
                ->get($wpUrl . '/wp-json/wp/v2/tags', [
                    'search' => $tagName
                ]);

            if ($searchResponse->successful()) {
                $tags = $searchResponse->json();
                if (!empty($tags)) {
                    $tagIds[] = $tags[0]['id'];
                    continue;
                }
            }

            // Créer un nouveau tag
            $createResponse = Http::withOptions(['verify' => false])
                ->withBasicAuth($username, $password)
                ->post($wpUrl . '/wp-json/wp/v2/tags', [
                    'name' => $tagName
                ]);

            if ($createResponse->successful()) {
                $tagIds[] = $createResponse->json()['id'];
            }
        }

        return $tagIds;
    }
}
