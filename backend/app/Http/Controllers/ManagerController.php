<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Article;
use App\Models\Log as LogModel;
use App\Models\SystemApiKey;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ManagerController extends Controller
{
    /**
     * Mapping des providers vers leurs modèles par défaut
     * Les noms sont fixes et validés par les APIs
     */
    private const DEFAULT_MODELS = [
        'mistral' => 'mistral-large-latest',
        'openai' => 'gpt-4o',
        'anthropic' => 'claude-3-sonnet-20241022',
        'deepseek' => 'deepseek-chat',
        'gemini' => 'gemini-2.0-flash',
        'pollinations' => 'openai',
    ];

    /**
     * Mapping des providers vers leurs URL d'API
     */
    private const API_URLS = [
        'mistral' => 'https://api.mistral.ai/v1/chat/completions',
        'openai' => 'https://api.openai.com/v1/chat/completions',
        'anthropic' => 'https://api.anthropic.com/v1/messages',
        'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
        'gemini' => 'https://generativelanguage.googleapis.com/v1beta/models/',
        'pollinations' => 'https://gen.pollinations.ai/v1/chat/completions',
    ];

    /**
     * Récupère la configuration LLM active
     */
    private function getActiveLlmConfig()
    {
        $cacheKey = 'active_llm_config';

        return Cache::remember($cacheKey, 300, function () {
            // Récupérer la clé active avec capacité text_generation
            // On cherche UNIQUEMENT les providers qui ont text_generation
            $activeKey = SystemApiKey::where('is_active', true)
                ->whereJsonContains('capabilities', 'text_generation')
                ->first();

            if (!$activeKey) {
                throw new \Exception('Aucune clé API active avec capacité text_generation. Veuillez configurer un LLM de texte (OpenAI, Mistral, etc.)');
            }

            return [
                'provider' => $activeKey->provider,
                'api_key' => $activeKey->api_key,
                'model_name' => $activeKey->model_name ?? self::DEFAULT_MODELS[$activeKey->provider] ?? null,
                'config' => $activeKey->config ?? []
            ];
        });
    }

    /**
     * Crée un client HTTP avec vérification SSL désactivée
     */
    private function getHttpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions([
            'verify' => false,
            'timeout' => 60,
            'connect_timeout' => 30,
        ]);
    }

    /**
     * Appelle le LLM configuré
     */
    private function callLlm(string $prompt, string $modelName = null, int $maxTokens = 2000, float $temperature = 0.7): string
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

        // Seulement les providers de TEXTE supportés
        return match ($provider) {
            'mistral' => $this->callMistral($prompt, $apiKey, $model, $maxTokens, $temperature),
            'openai' => $this->callOpenAI($prompt, $apiKey, $model, $maxTokens, $temperature),
            'anthropic' => $this->callAnthropic($prompt, $apiKey, $model, $maxTokens, $temperature),
            'deepseek' => $this->callDeepSeek($prompt, $apiKey, $model, $maxTokens, $temperature),
            'gemini' => $this->callGemini($prompt, $apiKey, $model, $maxTokens, $temperature),
            // ❌ On SUPPRIME pollinations de la liste
            default => throw new \Exception("Provider non supporté pour la génération de texte: {$provider}")
        };
    }

    /**
     * Appelle Pollinations via l'API OpenAI-compatible
     */
    private function callPollinations(string $prompt, string $apiKey, string $model, int $maxTokens, float $temperature): string
    {
        $client = $this->getHttpClient();

        // Pollinations supporte les modèles OpenAI-compatibles
        // Le modèle 'openai' ou 'mistral' fonctionne
        $modelName = $model === 'openai' ? 'openai' : 'openai'; // Par défaut

        $response = $client->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post('https://gen.pollinations.ai/v1/chat/completions', [
            'model' => $modelName,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ]);

        if (!$response->successful()) {
            Log::error('Erreur Pollinations (texte)', [
                'status' => $response->status(),
                'model' => $modelName,
                'body' => $response->body()
            ]);
            throw new \Exception('Erreur Pollinations API: ' . $response->body());
        }

        return $response->json()['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Appelle le LLM et retourne du JSON parsé
     */
    private function callLlmJson(string $prompt, string $modelName = null, int $maxTokens = 2000, float $temperature = 0.2): array
    {
        $response = $this->callLlm($prompt, $modelName, $maxTokens, $temperature);

        // Nettoyer la réponse pour extraire le JSON
        $response = trim($response);
        if (str_starts_with($response, '```json')) {
            $response = substr($response, 7);
        }
        if (str_starts_with($response, '```')) {
            $response = substr($response, 3);
        }
        if (str_ends_with($response, '```')) {
            $response = substr($response, 0, -3);
        }
        $response = trim($response);

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Erreur de décodage JSON: ' . json_last_error_msg() . '. Réponse brute: ' . substr($response, 0, 500));
        }

        return $result;
    }

    // ============================================================
    // IMPLÉMENTATIONS SPÉCIFIQUES PAR PROVIDER
    // ============================================================

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
            Log::error('Erreur Mistral', [
                'status' => $response->status(),
                'model' => $model,
                'body' => $response->body()
            ]);
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
            Log::error('Erreur OpenAI', [
                'status' => $response->status(),
                'model' => $model,
                'body' => $response->body()
            ]);
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
            Log::error('Erreur Anthropic', [
                'status' => $response->status(),
                'model' => $model,
                'body' => $response->body()
            ]);
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
            Log::error('Erreur DeepSeek', [
                'status' => $response->status(),
                'model' => $model,
                'body' => $response->body()
            ]);
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
            Log::error('Erreur Gemini', [
                'status' => $response->status(),
                'model' => $model,
                'body' => $response->body()
            ]);
            throw new \Exception('Erreur Gemini API: ' . $response->body());
        }

        return $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    // ============================================================
    // SCRAPING
    // ============================================================

    private function scrapeSite(string $url, int $maxPages = 5): array
    {
        $pages = [];
        $baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);

        Log::info('Début scraping', ['url' => $url, 'baseUrl' => $baseUrl]);

        $extractText = function ($pageUrl) {
            try {
                Log::info('Extraction texte', ['url' => $pageUrl]);

                $client = $this->getHttpClient();

                $response = $client->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
                    'Connection' => 'keep-alive',
                ])->get($pageUrl);

                Log::info('Réponse HTTP', [
                    'url' => $pageUrl,
                    'status' => $response->status(),
                    'content_length' => strlen($response->body())
                ]);

                if (!$response->successful()) {
                    Log::warning('Réponse non réussie', ['url' => $pageUrl, 'status' => $response->status()]);
                    return '';
                }

                $html = $response->body();

                if (empty($html)) {
                    Log::warning('HTML vide', ['url' => $pageUrl]);
                    return '';
                }

                $dom = new \DOMDocument();
                $internalErrors = libxml_use_internal_errors(true);
                $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                libxml_use_internal_errors($internalErrors);

                $xpath = new \DOMXPath($dom);
                $tagsToRemove = ['script', 'style', 'nav', 'footer', 'header', 'aside', 'noscript', 'svg', 'iframe'];
                foreach ($tagsToRemove as $tag) {
                    foreach ($xpath->query("//{$tag}") as $node) {
                        $node->parentNode->removeChild($node);
                    }
                }

                $text = $dom->textContent;
                $text = preg_replace('/\s+/', ' ', $text);
                $text = trim($text);
                $text = substr($text, 0, 4000);

                Log::info('Texte extrait', [
                    'url' => $pageUrl,
                    'length' => strlen($text)
                ]);

                return $text;
            } catch (\Exception $e) {
                Log::error('Erreur extraction', [
                    'url' => $pageUrl,
                    'error' => $e->getMessage()
                ]);
                return '';
            }
        };

        Log::info('Extraction page d\'accueil', ['url' => $url]);
        $homeText = $extractText($url);
        if ($homeText) {
            $pages[$url] = $homeText;
            Log::info('Page d\'accueil extraite', ['length' => strlen($homeText)]);
        } else {
            Log::warning('Page d\'accueil vide', ['url' => $url]);
        }

        if (empty($pages)) {
            Log::warning('Tentative avec file_get_contents', ['url' => $url]);
            try {
                $context = stream_context_create([
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                    'http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"]
                ]);
                $html = @file_get_contents($url, false, $context);
                if ($html !== false) {
                    $dom = new \DOMDocument();
                    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                    $text = $dom->textContent;
                    $text = preg_replace('/\s+/', ' ', $text);
                    $text = trim(substr($text, 0, 4000));
                    if ($text) {
                        $pages[$url] = $text;
                        Log::info('Page d\'accueil extraite via file_get_contents', ['length' => strlen($text)]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Erreur file_get_contents', ['error' => $e->getMessage()]);
            }
        }

        if (empty($pages)) {
            Log::error('Aucune page extraite');
            return [];
        }

        try {
            Log::info('Recherche liens internes');
            $client = $this->getHttpClient();

            $response = $client->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ])->get($url);

            if ($response->successful()) {
                $html = $response->body();
                $dom = new \DOMDocument();
                @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                $xpath = new \DOMXPath($dom);

                $links = [];
                $priorities = ['service', 'solution', 'blog', 'digital', 'securite', 'cloud', 'business'];

                foreach ($xpath->query("//a/@href") as $href) {
                    $hrefValue = $href->nodeValue;
                    if (empty($hrefValue) || $hrefValue === '#' || str_starts_with($hrefValue, 'mailto:') || str_starts_with($hrefValue, 'tel:')) {
                        continue;
                    }

                    if (str_starts_with($hrefValue, '/') && strlen($hrefValue) > 1) {
                        $fullUrl = rtrim($baseUrl, '/') . $hrefValue;
                        $fullUrl = str_replace('//', '/', $fullUrl);
                        $fullUrl = str_replace('https:/', 'https://', $fullUrl);
                        $fullUrl = str_replace('http:/', 'http://', $fullUrl);
                        if (!isset($pages[$fullUrl]) && !in_array($fullUrl, $links)) {
                            $links[] = $fullUrl;
                        }
                    } elseif (str_starts_with($hrefValue, $baseUrl) && $hrefValue !== $baseUrl && $hrefValue !== $url) {
                        if (!isset($pages[$hrefValue]) && !in_array($hrefValue, $links)) {
                            $links[] = $hrefValue;
                        }
                    }
                }

                $links = array_unique($links);
                Log::info('Liens trouvés', ['count' => count($links)]);

                usort($links, function ($a, $b) use ($priorities) {
                    $scoreA = 0;
                    $scoreB = 0;
                    foreach ($priorities as $i => $keyword) {
                        if (stripos($a, $keyword) !== false) $scoreA += count($priorities) - $i;
                        if (stripos($b, $keyword) !== false) $scoreB += count($priorities) - $i;
                    }
                    return $scoreB - $scoreA;
                });

                $links = array_slice($links, 0, $maxPages - 1);

                foreach ($links as $link) {
                    if (!isset($pages[$link])) {
                        $text = $extractText($link);
                        if ($text) {
                            $pages[$link] = $text;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Erreur extraction liens', ['error' => $e->getMessage()]);
        }

        Log::info('Scraping terminé', ['total_pages' => count($pages)]);
        return $pages;
    }

    // ============================================================
    // MÉTHODES DE L'API
    // ============================================================

    /**
     * Analyser un site
     */
    public function analyzeSite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:255',
            'description' => 'nullable|string',
            'provider' => 'nullable|string|max:50',
            'model_name' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        try {
            Log::info('Analyse site', ['url' => $request->url]);

            $pages = $this->scrapeSite($request->url);

            if (empty($pages)) {
                Log::error('Aucune page extraite', ['url' => $request->url]);
                return response()->json([
                    'error' => 'Impossible de récupérer le contenu du site. Vérifiez que le site est accessible.',
                    'details' => 'Le site ' . $request->url . ' n\'a retourné aucun contenu.'
                ], 400);
            }

            $contenuBrut = "";
            if ($request->description) {
                $contenuBrut .= "[DESCRIPTION MANUELLE]\n{$request->description}\n\n";
            }
            $contenuBrut .= "[CONTENU EXTRAIT]\n";
            foreach ($pages as $pageUrl => $texte) {
                $contenuBrut .= "\n--- {$pageUrl} ---\n" . substr($texte, 0, 1500) . "\n";
            }
            $contenuBrut = substr($contenuBrut, 0, 6000);

            $prompt = $this->buildAnalyzePrompt($request->url, $contenuBrut);

            // Utiliser le modèle spécifié ou celui par défaut
            $modelName = $request->model_name ?? null;
            $contexteJson = $this->callLlmJson($prompt, $modelName);
            $contexteJson['url_analysee'] = $request->url;
            $contexteJson['model_used'] = $request->model_name ?? 'default';
            $contexteJson['provider_used'] = $request->provider ?? 'default';

            $site = Site::create([
                'user_id' => $user->id,
                'url' => $request->url,
                'description' => $request->description,
                'contexte_json' => $contexteJson,
                'analyzed_at' => now(),
            ]);

            LogModel::create([
                'user_id' => $user->id,
                'action' => 'site_analyzed',
                'details' => "Site analysé: {$request->url} avec le modèle " . ($request->model_name ?? 'par défaut'),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Site analysé avec succès',
                'site' => $site
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur analyse site', [
                'url' => $request->url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erreur lors de l\'analyse du site',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construire le prompt pour l'analyse
     */
    private function buildAnalyzePrompt(string $url, string $contenuBrut): string
    {
        return <<<PROMPT
Tu es un expert en analyse éditoriale et SEO.

[CONTENU DU SITE]
URL : {$url}
{$contenuBrut}

[FORMAT JSON OBLIGATOIRE - REPONDS UNIQUEMENT AVEC CE JSON, SANS AUTRE TEXTE]
{
  "secteur_activite": "...",
  "ton_editorial": {
    "principal": "...",
    "niveau_langue": "...",
    "langue_principale_du_site": "francais",
    "style_phrases": "...",
    "usage_pronoms": "..."
  },
  "structure_articles": {
    "longueur_moyenne_mots": "...",
    "titres_typiques": "...",
    "sous_titres": "..."
  },
  "vocabulaire_cles": {
    "mots_specifiques": [],
    "expressions_recurrentes": [],
    "mots_cles_seo_visibles": []
  },
  "themes_abordes": [],
  "themes_suggeres_pour_articles": [
    {
      "titre": "Theme d'article 1 (formule comme une question ou un angle pratique)",
      "mot_cle_principal": "mot-cle SEO principal pour cet article"
    },
    {
      "titre": "Theme d'article 2",
      "mot_cle_principal": "mot-cle associe"
    },
    {
      "titre": "Theme d'article 3",
      "mot_cle_principal": "mot-cle associe"
    },
    {
      "titre": "Theme d'article 4",
      "mot_cle_principal": "mot-cle associe"
    },
    {
      "titre": "Theme d'article 5",
      "mot_cle_principal": "mot-cle associe"
    }
  ],
  "types_de_contenu": [],
  "ton_des_titres": "...",
  "longueur_typique_paragraphe": "...",
  "public_cible_detecte": [],
  "usage_de_la_premiere_personne": "...",
  "presence_commentaires_ou_interaction": "...",
  "call_to_action_typique": "...",
  "particularites": "..."
}

[INSTRUCTIONS POUR LES THEMES SUGGERES]
- Propose exactement 5 themes d'articles pertinents et originaux pour ce site.
- Chaque theme doit etre une question ou un angle d'article specifique et actionnable.
- Les themes doivent etre varies et couvrir differents aspects du secteur d'activite.
- Formule les themes de maniere attrayante pour un lecteur decideur (chef d'entreprise, DSI).
- Pour chaque theme, fournis aussi un mot-cle principal SEO approprie.
- Retourne uniquement le JSON valide, sans texte supplementaire.
PROMPT;
    }

    /**
     * Générer un article
     */
    public function generateArticle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_id' => 'required|exists:sites,id',
            'sujet' => 'required|string|max:200',
            'mot_cle_principal' => 'required|string|max:100',
            'longueur_cible' => 'nullable|integer|min:300|max:5000',
            'provider' => 'nullable|string|max:50',
            'model_name' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $site = $request->user()->sites()->findOrFail($request->site_id);
        $contexteJson = $site->contexte_json;

        if (!$contexteJson) {
            return response()->json(['error' => 'Le site n\'a pas encore été analysé'], 400);
        }

        try {
            $prompt = $this->buildGeneratePrompt(
                $contexteJson,
                $request->sujet,
                $request->mot_cle_principal,
                $request->longueur_cible ?? 800
            );

            // Utiliser le modèle spécifié ou celui par défaut
            $modelName = $request->model_name ?? null;
            $rawResponse = $this->callLlm($prompt, $modelName, 4000, 0.7);
            $articleData = $this->parseArticleResponse($rawResponse);

            $article = Article::create([
                'site_id' => $site->id,
                'user_id' => $request->user()->id,
                'titre' => $articleData['titre'],
                'meta_desc' => $articleData['meta_desc'],
                'slug' => $articleData['slug'],
                'contenu_md' => $articleData['contenu_md'],
                'categorie' => $articleData['categorie'],
                'tags' => $articleData['tags'],
                'mot_cle_principal' => $request->mot_cle_principal,
                'statut' => 'brouillon',
            ]);

            LogModel::create([
                'user_id' => $request->user()->id,
                'action' => 'article_generated',
                'details' => "Article généré: {$article->titre} avec le modèle " . ($request->model_name ?? 'par défaut'),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Article généré avec succès',
                'article' => $article
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur génération article', [
                'site_id' => $request->site_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erreur lors de la génération de l\'article',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construire le prompt pour la génération d'article
     */
    private function buildGeneratePrompt(array $contexte, string $sujet, string $motClePrincipal, int $longueurCible): string
    {
        $ton = $contexte['ton_editorial'] ?? [];
        $vocab = $contexte['vocabulaire_cles'] ?? [];
        $siteName = $contexte['url_analysee'] ?? 'le site';

        $safeStr = function ($val, $fallback = 'non_detecte') {
            if (is_array($val)) {
                return !empty($val) ? implode(', ', $val) : $fallback;
            }
            return $val ?: $fallback;
        };

        return <<<PROMPT
Tu es un rédacteur web expert en SEO. Tu rédiges des articles pour {$siteName}.

[CONTEXTE EDITORIAL]
Secteur: {$safeStr($contexte['secteur_activite'] ?? null)}
Ton: {$safeStr($ton['principal'] ?? null)}
Niveau langue: {$safeStr($ton['niveau_langue'] ?? null)}
Style phrases: {$safeStr($ton['style_phrases'] ?? null)}
Usage pronoms: {$safeStr($ton['usage_pronoms'] ?? null)}
Public cible: {$safeStr($contexte['public_cible_detecte'] ?? null)}
CTA: {$safeStr($contexte['call_to_action_typique'] ?? null)}
Expressions clés: {$safeStr($vocab['expressions_recurrentes'] ?? null)}
Mots-clés SEO site: {$safeStr($vocab['mots_cles_seo_visibles'] ?? null)}

[BRIEF ARTICLE]
Sujet: {$sujet}
Mot-clé principal: {$motClePrincipal}
Longueur cible: {$longueurCible} mots
Intention: informationnelle

[FORMAT DE SORTIE OBLIGATOIRE - RESPECTE STRICTEMENT]
---SEO_META_START---
TITRE H1: [titre avec mot-cle principal, max 65 caracteres, sans ** ni *]
META DESC: [140-155 caracteres, mot-cle principal au debut, sans ** ni *]
URL SLUG: /[slug-3-5-mots-tirets]
---SEO_META_END---

---WP_META_START---
CATEGORIE: [categorie WordPress, ex: "Blog", "Actualites", "Technologies", sans ** ni *]
TAGS: [exactement 5 tags maximum, separes par des virgules, sans ** ni *]
---WP_META_END---

---ARTICLE_START---
## Introduction
[100-150 mots, mot-cle principal dans les 50 premiers mots]

## H2: [premiere sous-partie avec mot-cle secondaire]
[200-250 mots]

## H2: [deuxieme sous-partie]
[200-250 mots]

## H2: [troisieme sous-partie]
[200-250 mots]

## Conclusion
[80-120 mots, avec CTA: {$safeStr($contexte['call_to_action_typique'] ?? 'Contactez-nous pour en savoir plus')}]
---ARTICLE_END---

---CHECKLIST_START---
H1_CONTIENT_MOT_CLE: oui/non
META_DESC_LONGUEUR: [nombre]
MOT_CLE_PREMIER_PARA: oui/non
CTA_PRESENT: oui/non
---CHECKLIST_END---

[REGLES]
- Ne pas inventer de chiffres ou témoignages
- Pas de "En conclusion"
- Toujours "vous" pas "tu"
- Respecter le format exact
- NE PAS utiliser ** ou * dans TITRE H1, META DESC, CATEGORIE, TAGS
- Maximum 5 tags
- Longueur totale: {$longueurCible} mots
PROMPT;
    }

    /**
     * Parser la réponse du LLM pour l'article
     */
    private function parseArticleResponse(string $raw): array
    {
        $result = [
            'titre' => '',
            'meta_desc' => '',
            'slug' => '',
            'categorie' => '',
            'tags' => [],
            'contenu_md' => '',
            'checklist' => []
        ];

        if (preg_match('/---SEO_META_START---(.*?)---SEO_META_END---/s', $raw, $matches)) {
            $block = $matches[1];
            foreach (explode("\n", $block) as $line) {
                if (str_contains($line, 'TITRE H1:')) {
                    $result['titre'] = trim(str_replace('TITRE H1:', '', $line));
                } elseif (str_contains($line, 'META DESC:')) {
                    $result['meta_desc'] = trim(str_replace('META DESC:', '', $line));
                } elseif (str_contains($line, 'URL SLUG:')) {
                    $slug = trim(str_replace('URL SLUG:', '', $line));
                    $result['slug'] = ltrim($slug, '/');
                }
            }
        }

        if (preg_match('/---WP_META_START---(.*?)---WP_META_END---/s', $raw, $matches)) {
            $block = $matches[1];
            foreach (explode("\n", $block) as $line) {
                if (str_contains($line, 'CATEGORIE:')) {
                    $result['categorie'] = trim(str_replace('CATEGORIE:', '', $line));
                } elseif (str_contains($line, 'TAGS:')) {
                    $tags = trim(str_replace('TAGS:', '', $line));
                    $result['tags'] = array_map('trim', explode(',', $tags));
                    $result['tags'] = array_slice($result['tags'], 0, 5);
                }
            }
        }

        if (preg_match('/---ARTICLE_START---(.*?)---ARTICLE_END---/s', $raw, $matches)) {
            $result['contenu_md'] = trim($matches[1]);
        }

        if (preg_match('/---CHECKLIST_START---(.*?)---CHECKLIST_END---/s', $raw, $matches)) {
            foreach (explode("\n", trim($matches[1])) as $line) {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $result['checklist'][trim($key)] = trim($value);
                }
            }
        }

        return $result;
    }

    /**
     * Publier un article sur WordPress
     */
    public function publishArticle($id, Request $request)
    {
        try {
            $article = $request->user()
                ? $request->user()->articles()->with('site')->findOrFail($id)
                : Article::with('site')->findOrFail($id);

            if ($article->statut === 'publie') {
                return response()->json([
                    'success' => false,
                    'message' => 'Article déjà publié'
                ], 400);
            }

            $wpConfig = $article->site->wordpressConfig;

            if (!$wpConfig || !$wpConfig->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => 'Aucune configuration WordPress pour ce site',
                    'requires_config' => true,
                    'site_id' => $article->site->id
                ], 400);
            }

            $result = $this->publishToWordPress($article, $wpConfig);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Erreur lors de la publication'
                ], 500);
            }

            $article->update([
                'statut' => 'publie',
                'publie_le' => now(),
                'wp_url' => $result['url'] ?? null,
                'wp_post_id' => $result['id'] ?? null,
            ]);

            $wpConfig->increment('use_count');
            $wpConfig->update(['last_used_at' => now()]);

            LogModel::create([
                'user_id' => $request->user()->id,
                'action' => 'article_published',
                'details' => "Article publié sur WordPress: {$article->titre}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Article publié avec succès',
                'url' => $result['url'] ?? null,
                'wp_id' => $result['id'] ?? null,
                'article' => [
                    'id' => $article->id,
                    'titre' => $article->titre,
                    'statut' => $article->statut,
                    'publie_le' => $article->publie_le,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Exception dans publishArticle', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publier sur WordPress via l'API REST
     */
    private function publishToWordPress($article, $wpConfig): array
    {
        $wpUrl = $wpConfig->wp_url;
        $username = $wpConfig->wp_username;
        $password = $wpConfig->wp_app_password;

        $titre = trim($article->titre);
        $metaDesc = substr($article->meta_desc ?? '', 0, 155);
        $slug = $article->slug ?? '';
        $contenuHtml = $this->markdownToHtml($article->contenu_md ?? '');

        $auth = base64_encode("{$username}:{$password}");
        $data = [
            'title' => $titre,
            'content' => $contenuHtml,
            'status' => 'publish',
            'slug' => $slug,
            'excerpt' => $metaDesc,
        ];

        if ($article->categorie) {
            $catId = $this->getOrCreateCategory($wpUrl, $auth, $article->categorie);
            if ($catId) {
                $data['categories'] = [$catId];
            }
        }

        if (!empty($article->tags)) {
            $tagIds = $this->getOrCreateTags($wpUrl, $auth, $article->tags);
            if (!empty($tagIds)) {
                $data['tags'] = $tagIds;
            }
        }

        try {
            $client = $this->getHttpClient();

            $response = $client->withHeaders([
                'Authorization' => "Basic {$auth}",
                'Content-Type' => 'application/json'
            ])->post("{$wpUrl}/wp-json/wp/v2/posts", $data);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'id' => $result['id'] ?? null,
                    'url' => $result['link'] ?? null,
                ];
            }

            Log::error('Erreur publication WordPress', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => "HTTP {$response->status()}: " . substr($response->body(), 0, 200)
            ];
        } catch (\Exception $e) {
            Log::error('Exception publication WordPress', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convertir Markdown en HTML
     */
    private function markdownToHtml(string $md): string
    {
        $html = $md;

        // Images : ![alt](url) → <img src="url" alt="alt" />
        $html = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%;height:auto;" />', $html);

        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        $lines = explode("\n", $html);
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && !preg_match('/^<h[23]|<ul|<li|<strong|<em|<img/', $line)) {
                $result[] = "<p>{$line}</p>";
            } elseif ($line) {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Récupérer ou créer une catégorie WordPress
     */
    private function getOrCreateCategory(string $wpUrl, string $auth, string $categoryName)
    {
        try {
            $client = $this->getHttpClient();

            $response = $client->withHeaders([
                'Authorization' => "Basic {$auth}"
            ])->get("{$wpUrl}/wp-json/wp/v2/categories", [
                'search' => $categoryName
            ]);

            if ($response->successful() && !empty($response->json())) {
                return $response->json()[0]['id'];
            }
        } catch (\Exception $e) {
            Log::warning('Erreur recherche catégorie', ['error' => $e->getMessage()]);
        }

        try {
            $client = $this->getHttpClient();

            $response = $client->withHeaders([
                'Authorization' => "Basic {$auth}",
                'Content-Type' => 'application/json'
            ])->post("{$wpUrl}/wp-json/wp/v2/categories", [
                'name' => $categoryName
            ]);

            if ($response->successful()) {
                return $response->json()['id'] ?? null;
            }
        } catch (\Exception $e) {
            Log::warning('Erreur création catégorie', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Récupérer ou créer des tags WordPress
     */
    private function getOrCreateTags(string $wpUrl, string $auth, array $tagNames): array
    {
        $tagIds = [];
        $tagNames = array_slice(array_map('trim', $tagNames), 0, 5);

        foreach ($tagNames as $tagName) {
            if (strlen($tagName) > 40) {
                continue;
            }

            try {
                $client = $this->getHttpClient();

                $response = $client->withHeaders([
                    'Authorization' => "Basic {$auth}"
                ])->get("{$wpUrl}/wp-json/wp/v2/tags", [
                    'search' => $tagName
                ]);

                if ($response->successful() && !empty($response->json())) {
                    $tagIds[] = $response->json()[0]['id'];
                    continue;
                }

                $response = $client->withHeaders([
                    'Authorization' => "Basic {$auth}",
                    'Content-Type' => 'application/json'
                ])->post("{$wpUrl}/wp-json/wp/v2/tags", [
                    'name' => $tagName
                ]);

                if ($response->successful()) {
                    $tagIds[] = $response->json()['id'] ?? null;
                }
            } catch (\Exception $e) {
                Log::warning('Erreur tag', ['tag' => $tagName, 'error' => $e->getMessage()]);
            }
        }

        return array_filter($tagIds);
    }

    // ============================================================
    // MÉTHODES EXISTANTES (inchangées)
    // ============================================================

    public function listSites(Request $request)
    {
        $sites = $request->user()->sites()->orderBy('created_at', 'desc')->paginate(10);
        return response()->json($sites);
    }

    public function getSite($id, Request $request)
    {
        $site = $request->user()->sites()->findOrFail($id);
        return response()->json($site);
    }

    public function deleteSite($id, Request $request)
    {
        $site = $request->user()->sites()->findOrFail($id);
        $site->delete();

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'site_deleted',
            'details' => "Site supprimé: {$site->url}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Site supprimé']);
    }

    public function listArticles(Request $request)
    {
        $articles = $request->user()->articles()
            ->with('site')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return response()->json($articles);
    }

    public function getArticle($id, Request $request)
    {
        $article = $request->user()->articles()->with('site')->findOrFail($id);
        return response()->json($article);
    }

    public function updateArticle($id, Request $request)
    {
        $article = $request->user()->articles()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'titre' => 'sometimes|string|max:200',
            'meta_desc' => 'sometimes|string|max:160',
            'contenu_md' => 'sometimes|string',
            'categorie' => 'nullable|string|max:100',
            'tags' => 'nullable|array|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $article->update($request->only(['titre', 'meta_desc', 'contenu_md', 'categorie', 'tags']));

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'article_updated',
            'details' => "Article mis à jour: {$article->titre}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Article mis à jour',
            'article' => $article
        ]);
    }

    public function scheduleArticle($id, Request $request)
    {
        $article = $request->user()->articles()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date|after:now',
        ], [
            'scheduled_at.required' => 'La date et heure de publication est requise.',
            'scheduled_at.date' => 'Le format de la date est invalide.',
            'scheduled_at.after' => 'La date de publication doit être dans le futur. Veuillez sélectionner une date et heure ultérieure.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($article->statut === 'publie') {
            return response()->json([
                'success' => false,
                'message' => 'Cet article est déjà publié, impossible de le programmer'
            ], 400);
        }

        $article->update([
            'statut' => 'programme',
            'programme_le' => $request->scheduled_at,
        ]);

        $scheduledTask = \App\Models\ScheduledTask::updateOrCreate(
            ['article_id' => $article->id],
            [
                'scheduled_at' => $request->scheduled_at,
                'status' => 'pending',
            ]
        );

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'article_scheduled',
            'details' => "Article programmé: {$article->titre} le {$request->scheduled_at}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Article programmé avec succès',
            'scheduled_at' => $request->scheduled_at,
            'scheduled_task_id' => $scheduledTask->id,
            'article' => [
                'id' => $article->id,
                'titre' => $article->titre,
                'statut' => $article->statut,
                'programme_le' => $article->programme_le,
            ]
        ]);
    }

    public function cancelSchedule($id, Request $request)
    {
        $article = $request->user()->articles()->findOrFail($id);

        if ($article->statut !== 'programme') {
            return response()->json([
                'success' => false,
                'message' => 'Cet article n\'est pas programmé'
            ], 400);
        }

        $article->update([
            'statut' => 'brouillon',
            'programme_le' => null,
        ]);

        \App\Models\ScheduledTask::where('article_id', $article->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'schedule_cancelled',
            'details' => "Programmation annulée pour: {$article->titre}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Programmation annulée avec succès',
            'article' => [
                'id' => $article->id,
                'titre' => $article->titre,
                'statut' => $article->statut,
            ]
        ]);
    }

    public function updateSchedule($id, Request $request)
    {
        $article = $request->user()->articles()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date|after:now',
        ], [
            'scheduled_at.required' => 'La date et heure de publication est requise.',
            'scheduled_at.date' => 'Le format de la date est invalide.',
            'scheduled_at.after' => 'La date de publication doit être dans le futur. Veuillez sélectionner une date et heure ultérieure.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($article->statut !== 'programme') {
            return response()->json([
                'success' => false,
                'message' => 'Cet article n\'est pas programmé'
            ], 400);
        }

        $article->update([
            'programme_le' => $request->scheduled_at,
        ]);

        \App\Models\ScheduledTask::where('article_id', $article->id)
            ->where('status', 'pending')
            ->update([
                'scheduled_at' => $request->scheduled_at,
            ]);

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'schedule_updated',
            'details' => "Programmation modifiée pour: {$article->titre} le {$request->scheduled_at}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Date de programmation modifiée avec succès',
            'scheduled_at' => $request->scheduled_at,
            'article' => [
                'id' => $article->id,
                'titre' => $article->titre,
                'statut' => $article->statut,
                'programme_le' => $article->programme_le,
            ]
        ]);
    }

    public function deleteArticle($id, Request $request)
    {
        $article = $request->user()->articles()->findOrFail($id);
        $article->delete();

        LogModel::create([
            'user_id' => $request->user()->id,
            'action' => 'article_deleted',
            'details' => "Article supprimé: {$article->titre}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Article supprimé avec succès']);
    }

    public function getSiteThemes($id, Request $request)
    {
        $site = $request->user()->sites()->findOrFail($id);

        $themes = [];
        if ($site->contexte_json && isset($site->contexte_json['themes_suggeres_pour_articles'])) {
            $themes = $site->contexte_json['themes_suggeres_pour_articles'];
        }

        return response()->json([
            'themes' => $themes,
            'has_themes' => count($themes) > 0
        ]);
    }

    public function scheduledArticles(Request $request)
    {
        $articles = $request->user()->articles()
            ->where('statut', 'programme')
            ->where('programme_le', '>', now())
            ->orderBy('programme_le', 'asc')
            ->get();

        return response()->json($articles);
    }
}
