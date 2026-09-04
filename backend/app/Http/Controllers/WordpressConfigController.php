<?php
// app/Http/Controllers/WordpressConfigController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteWordpressConfig;
use App\Models\ApiUsageLog;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class WordpressConfigController extends Controller
{
    /**
     * Récupérer la configuration WordPress pour un site spécifique
     */
    public function show($siteId, Request $request)
    {
        $site = $request->user()->sites()->findOrFail($siteId);
        
        $config = $site->wordpressConfig;
        
        if (!$config) {
            return response()->json(['message' => 'Aucune configuration WordPress pour ce site'], 404);
        }
        
        return response()->json([
            'id' => $config->id,
            'site_id' => $config->site_id,
            'wp_url' => $config->wp_url,
            'wp_username' => $config->wp_username,
            'wp_app_password_masked' => $config->getMaskedPassword(),
            'is_active' => $config->is_active,
            'last_test_status' => $config->last_test_status,
            'last_test_at' => $config->last_test_at,
            'last_used_at' => $config->last_used_at,
            'use_count' => $config->use_count
        ]);
    }

    /**
     * Créer ou mettre à jour la configuration WordPress pour un site
     */
    public function storeOrUpdate($siteId, Request $request)
    {
        $site = $request->user()->sites()->findOrFail($siteId);

        $validator = Validator::make($request->all(), [
            'wp_url' => 'required|url|max:255',
            'wp_username' => 'required|string|max:100',
            'wp_app_password' => 'required|string|min:10',
            'test_connection' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $config = SiteWordpressConfig::updateOrCreate(
                ['site_id' => $site->id],
                [
                    'wp_url' => rtrim($request->wp_url, '/'),
                    'wp_username' => $request->wp_username,
                    'wp_app_password' => $request->wp_app_password,
                    'is_active' => true
                ]
            );

            // Tester la connexion si demandé
            $testResult = null;
            if ($request->test_connection) {
                $testResult = $this->testWordpressConnection($config);
                $config->update([
                    'last_test_at' => now(),
                    'last_test_status' => $testResult['success']
                ]);
            }

            // Mettre à jour le flag sur le site
            $site->update([
                'has_wp_config' => true,
                'wp_config_updated_at' => now()
            ]);

            Log::create([
                'user_id' => $request->user()->id,
                'action' => 'wp_config_' . ($config->wasRecentlyCreated ? 'created' : 'updated'),
                'details' => "Configuration WordPress pour le site: {$site->url}",
                'ip_address' => $request->ip(),
            ]);

            DB::commit();

            $response = [
                'message' => 'Configuration WordPress enregistrée avec succès',
                'config' => [
                    'id' => $config->id,
                    'wp_url' => $config->wp_url,
                    'wp_username' => $config->wp_username,
                    'wp_app_password_masked' => $config->getMaskedPassword(),
                    'is_active' => $config->is_active
                ]
            ];

            if ($testResult) {
                $response['connection_test'] = $testResult;
            }

            return response()->json($response, $config->wasRecentlyCreated ? 201 : 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de l\'enregistrement', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer la configuration WordPress d'un site
     */
    public function destroy($siteId, Request $request)
    {
        $site = $request->user()->sites()->findOrFail($siteId);
        
        $config = $site->wordpressConfig;
        
        if (!$config) {
            return response()->json(['message' => 'Aucune configuration trouvée'], 404);
        }

        DB::beginTransaction();
        try {
            $config->delete();
            
            $site->update([
                'has_wp_config' => false,
                'wp_config_updated_at' => now()
            ]);

            Log::create([
                'user_id' => $request->user()->id,
                'action' => 'wp_config_deleted',
                'details' => "Configuration WordPress supprimée pour le site: {$site->url}",
                'ip_address' => $request->ip(),
            ]);

            DB::commit();

            return response()->json(['message' => 'Configuration WordPress supprimée avec succès']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }

    /**
     * Tester la connexion WordPress
     */
    public function test($siteId, Request $request)
    {
        $site = $request->user()->sites()->findOrFail($siteId);
        
        $config = $site->wordpressConfig;
        
        if (!$config) {
            return response()->json(['message' => 'Aucune configuration WordPress pour ce site'], 404);
        }

        $result = $this->testWordpressConnection($config);
        
        // Mettre à jour le statut du test
        $config->update([
            'last_test_at' => now(),
            'last_test_status' => $result['success']
        ]);

        return response()->json($result);
    }

    /**
     * Tester réellement la connexion WordPress
     */
    private function testWordpressConnection(SiteWordpressConfig $config): array
    {
        $wpUrl = $config->wp_url;
        $username = $config->wp_username;
        $password = $config->wp_app_password;

        if (!$wpUrl || !$username || !$password) {
            return [
                'success' => false,
                'message' => 'Configuration WordPress incomplète'
            ];
        }

        try {
            $response = Http::withOptions(['verify' => false])
                ->withBasicAuth($username, $password)
                ->timeout(15)
                ->get($wpUrl . '/wp-json/wp/v2/users/me');

            if ($response->successful()) {
                $userData = $response->json();
                return [
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'user' => $userData['name'] ?? $username,
                    'site' => $wpUrl
                ];
            }

            return [
                'success' => false,
                'message' => 'Erreur de connexion: ' . ($response->json()['message'] ?? $response->status()),
                'status_code' => $response->status()
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'Impossible de joindre le site WordPress',
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du test: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lister tous les sites avec leur configuration WordPress
     */
    public function listAllWithConfig(Request $request)
    {
        $sites = $request->user()->sites()
            ->with('wordpressConfig')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        // Masquer les mots de passe
        $sites->getCollection()->transform(function ($site) {
            if ($site->wordpressConfig) {
                $site->wordpressConfig->wp_app_password_masked = $site->wordpressConfig->getMaskedPassword();
                unset($site->wordpressConfig->wp_app_password);
            }
            return $site;
        });
        
        return response()->json($sites);
    }

    /**
     * Obtenir la configuration active pour la publication
     * (utilisé par le service de publication)
     */
    public static function getActiveForSite($siteId)
    {
        $site = Site::find($siteId);
        if (!$site) return null;
        
        return $site->wordpressConfig()->where('is_active', true)->first();
    }
}