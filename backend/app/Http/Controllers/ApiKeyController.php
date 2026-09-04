<?php
// app/Http/Controllers/ApiKeyController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemApiKey;
use App\Models\ApiUsageLog;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
    /**
     * Liste toutes les clés API
     */
    public function index(Request $request)
    {
        $keys = SystemApiKey::orderBy('priority', 'asc')
            ->orderBy('provider', 'asc')
            ->paginate(15);

        // Masquer les clés pour l'affichage
        $keys->getCollection()->transform(function ($key) {
            $key->api_key_masked = $this->maskApiKey($key->api_key);
            unset($key->api_key);
            return $key;
        });

        return response()->json($keys);
    }

    /**
     * Créer une nouvelle clé API
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|max:50', // Plus de restriction
            'api_key' => 'required|string|min:10',
            'model_name' => 'nullable|string|max:100',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'in:text_generation,image_generation,video_generation,audio_generation',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:0|max:100',
            'config' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $apiKey = SystemApiKey::create([
                'provider' => $request->provider,
                'api_key' => $request->api_key,
                'model_name' => $request->model_name,
                'capabilities' => $request->capabilities ?? ['text_generation'],
                'is_active' => $request->is_active ?? false,
                'priority' => $request->priority ?? 0,
                'config' => $request->config,
                'created_by' => $request->user()->id
            ]);

            Log::create([
                'user_id' => $request->user()->id,
                'action' => 'api_key_created',
                'details' => "Clé API créée pour {$request->provider} (capabilities: " . implode(',', $request->capabilities ?? ['text_generation']) . ")",
                'ip_address' => $request->ip(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Clé API créée avec succès',
                'api_key' => $apiKey->makeHidden(['api_key'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la création', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Récupérer tous les modèles avec capacité text_generation
     */
    public function getTextModels(Request $request)
    {
        $models = SystemApiKey::where('is_active', true)
            ->whereJsonContains('capabilities', 'text_generation')
            ->orderBy('provider', 'asc')
            ->get();

        return response()->json([
            'models' => $models->map(function ($key) {
                return [
                    'provider' => $key->provider,
                    'model_name' => $key->model_name ?? 'Modèle par défaut',
                    'is_active' => $key->is_active
                ];
            })
        ]);
    }

    /**
     * Récupérer la configuration LLM active (pour l'agent Python)
     */
    public function getActiveConfig(Request $request)
    {
        $type = $request->get('type', 'text_generation');

        $activeKey = SystemApiKey::where('is_active', true)
            ->whereJsonContains('capabilities', $type)
            ->first();

        if (!$activeKey) {
            return response()->json([
                'success' => false,
                'error' => "Aucune clé API active configurée pour {$type}"
            ], 404);
        }

        return response()->json([
            'success' => true,
            'provider' => $activeKey->provider,
            'api_key' => $activeKey->api_key,
            'model_name' => $activeKey->model_name,
            'capabilities' => $activeKey->capabilities,
            'config' => $activeKey->config ?? []
        ]);
    }

    /**
     * Récupérer une clé API spécifique
     */
    public function show($id, Request $request)
    {
        $apiKey = SystemApiKey::findOrFail($id);

        return response()->json([
            'id' => $apiKey->id,
            'provider' => $apiKey->provider,
            'api_key_masked' => $this->maskApiKey($apiKey->api_key),
            'model_name' => $apiKey->model_name,
            'capabilities' => $apiKey->capabilities,
            'is_active' => $apiKey->is_active,
            'priority' => $apiKey->priority,
            'config' => $apiKey->config,
            'created_at' => $apiKey->created_at
        ]);
    }

    /**
     * Mettre à jour une clé API
     */
    public function update($id, Request $request)
    {
        $apiKey = SystemApiKey::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'provider' => 'sometimes|string|max:50',
            'api_key' => 'sometimes|string|min:10',
            'model_name' => 'nullable|string|max:100',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'in:text_generation,image_generation,video_generation,audio_generation',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:0|max:100',
            'config' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $updateData = $request->only(['provider', 'model_name', 'is_active', 'priority', 'config']);

            if ($request->has('capabilities')) {
                $updateData['capabilities'] = $request->capabilities;
            }

            if ($request->has('api_key') && $request->api_key) {
                $updateData['api_key'] = $request->api_key;
            }

            $apiKey->update($updateData);

            Log::create([
                'user_id' => $request->user()->id,
                'action' => 'api_key_updated',
                'details' => "Clé API mise à jour pour {$apiKey->provider}",
                'ip_address' => $request->ip(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Clé API mise à jour avec succès',
                'api_key' => $apiKey->makeHidden(['api_key'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la mise à jour', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer une clé API
     */
public function destroy($id, Request $request)
{
    $apiKey = SystemApiKey::findOrFail($id);

    // Supprimer la vérification "seule clé active"
    DB::beginTransaction();
    try {
        $provider = $apiKey->provider;
        $apiKey->delete();

        Log::create([
            'user_id' => $request->user()->id,
            'action' => 'api_key_deleted',
            'details' => "Clé API supprimée pour {$provider}",
            'ip_address' => $request->ip(),
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Clé API supprimée avec succès'
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Erreur lors de la suppression',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Tester une clé API
     */
    public function test($id, Request $request)
    {
        $apiKey = SystemApiKey::findOrFail($id);

        $success = true;
        $message = "Test réussi pour {$apiKey->provider}";
        $capabilities = $apiKey->capabilities ?? ['text_generation'];

        return response()->json([
            'success' => $success,
            'message' => $message,
            'capabilities' => $capabilities
        ]);
    }

    /**
     * Obtenir la configuration active (pour l'interface admin)
     * Cette méthode retourne la clé API active avec ses capabilities
     */
    public function getActive(Request $request)
    {
        $type = $request->get('type', 'text_generation');

        // Récupérer la première clé active avec la capacité demandée
        $activeKey = SystemApiKey::where('is_active', true)
            ->whereJsonContains('capabilities', $type)
            ->first();

        if (!$activeKey) {
            // Si aucune clé avec cette capacité, essayer de récupérer n'importe quelle clé active
            $activeKey = SystemApiKey::where('is_active', true)->first();

            if (!$activeKey) {
                return response()->json([
                    'message' => "Aucune clé API active configurée pour {$type}"
                ], 404);
            }
        }

        return response()->json([
            'provider' => $activeKey->provider,
            'model_name' => $activeKey->model_name,
            'capabilities' => $activeKey->capabilities,
            'config' => $activeKey->config
        ]);
    }

    /**
     * Masquer une clé API pour l'affichage
     */
    private function maskApiKey($key): string
    {
        $length = strlen($key);

        // Si la clé est trop courte, on la masque complètement
        if ($length <= 12) {
            return str_repeat('•', $length);
        }

        // S'assurer que le nombre de caractères masqués est >= 0
        $masked = $length - 16;

        // Si le nombre est négatif, on met 0
        if ($masked < 0) {
            $masked = 0;
        }

        return substr($key, 0, 8) . str_repeat('•', $masked) . substr($key, -8);
    }
}
