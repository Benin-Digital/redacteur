<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Site;
use App\Models\Article;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Mail\NewManagerCredentials;

class AdminController extends Controller
{
    /**
     * Lister tous les managers
     */
    public function listManagers(Request $request)
    {
        $managers = User::where('role', 'manager')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return response()->json($managers);
    }

    /**
     * Créer un nouveau manager (mot de passe généré automatiquement)
     */
    public function createManager(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'firstname' => 'nullable|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'email' => 'required|email:strict|max:100|unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Générer un mot de passe aléatoire fort
        $tempPassword = Str::password(12, true, true, false, false);

        DB::beginTransaction();
        try {
            $manager = User::create([
                'name' => $request->name,
                'firstname' => $request->firstname,
                'email' => strtolower($request->email),
                'password' => Hash::make($tempPassword),
                'role' => 'manager',
                'is_active' => true,
            ]);

            // Envoyer l'email avec les identifiants
            try {
                Mail::to($manager->email)->send(new NewManagerCredentials($manager, $tempPassword));
            } catch (\Exception $e) {
                Log::error('Erreur envoi email nouveau manager', [
                    'email' => $manager->email,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            Log::create([
                'user_id' => $request->user()->id,
                'action' => 'manager_created',
                'details' => "Manager créé: {$manager->email}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Manager créé avec succès. Les identifiants ont été envoyés par email.',
                'manager' => $manager->makeHidden(['password'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création du manager',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer un manager spécifique
     */
    public function getManager($id, Request $request)
    {
        $manager = User::where('role', 'manager')->findOrFail($id);
        
        return response()->json($manager->makeHidden(['password']));
    }

    /**
     * Mettre à jour un manager
     */
    public function updateManager($id, Request $request)
    {
        $manager = User::where('role', 'manager')->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'firstname' => 'sometimes|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'email' => 'sometimes|email:strict|max:100|unique:users,email,' . $manager->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $manager->update($request->only(['name', 'firstname', 'email']));

        Log::create([
            'user_id' => $request->user()->id,
            'action' => 'manager_updated',
            'details' => "Manager mis à jour: {$manager->email}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Manager mis à jour',
            'manager' => $manager->makeHidden(['password'])
        ]);
    }

    /**
     * Activer/désactiver un manager (sans suppression)
     */
    public function toggleManagerStatus($id, Request $request)
    {
        $manager = User::where('role', 'manager')->findOrFail($id);
        
        // Empêcher la désactivation de son propre compte
        if ($manager->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier votre propre statut'], 403);
        }
        
        $newStatus = !$manager->is_active;
        $manager->update(['is_active' => $newStatus]);

        Log::create([
            'user_id' => $request->user()->id,
            'action' => 'manager_status_toggled',
            'details' => "Manager " . ($newStatus ? 'activé' : 'désactivé') . ": {$manager->email}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => $newStatus ? 'Manager activé avec succès' : 'Manager désactivé avec succès',
            'is_active' => $newStatus
        ]);
    }

    /**
     * Supprimer un manager
     */
    public function deleteManager($id, Request $request)
    {
        $manager = User::where('role', 'manager')->findOrFail($id);
        
        // Empêcher la suppression de son propre compte
        if ($manager->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte'], 403);
        }
        
        $manager->delete();

        Log::create([
            'user_id' => $request->user()->id,
            'action' => 'manager_deleted',
            'details' => "Manager supprimé: {$manager->email}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Manager supprimé']);
    }

    /**
     * Lister tous les sites (supervision)
     */
    public function listAllSites(Request $request)
    {
        $sites = Site::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json($sites);
    }

    /**
     * Supprimer n'importe quel site
     */
    public function deleteAnySite($id, Request $request)
    {
        $site = Site::findOrFail($id);
        $site->delete();

        Log::create([
            'user_id' => $request->user()->id,
            'action' => 'site_deleted_by_admin',
            'details' => "Site supprimé par admin: {$site->url}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Site supprimé']);
    }

    /**
     * Lister tous les articles (supervision)
     */
    public function listAllArticles(Request $request)
    {
        $articles = Article::with(['user', 'site'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json($articles);
    }

    /**
     * Supprimer n'importe quel article
     */
    public function deleteAnyArticle($id, Request $request)
    {
        $article = Article::findOrFail($id);
        $article->delete();

        Log::create([
            'user_id' => $request->user()->id,
            'action' => 'article_deleted_by_admin',
            'details' => "Article supprimé par admin: {$article->titre}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Article supprimé']);
    }

    /**
     * Récupérer tous les logs
     */
    public function getLogs(Request $request)
    {
        $logs = Log::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);
        
        return response()->json($logs);
    }

    /**
     * Récupérer les logs d'un utilisateur spécifique
     */
    public function getUserLogs($userId, Request $request)
    {
        $logs = Log::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(50);
        
        return response()->json($logs);
    }

    /**
     * Statistiques globales
     */
    public function getStats(Request $request)
    {
        $stats = [
            'total_users' => User::count(),
            'total_managers' => User::where('role', 'manager')->count(),
            'active_managers' => User::where('role', 'manager')->where('is_active', true)->count(),
            'inactive_managers' => User::where('role', 'manager')->where('is_active', false)->count(),
            'total_sites' => Site::count(),
            'total_articles' => Article::count(),
            'articles_publies' => Article::where('statut', 'publie')->count(),
            'articles_programmes' => Article::where('statut', 'programme')->count(),
            'articles_brouillon' => Article::where('statut', 'brouillon')->count(),
        ];

        return response()->json($stats);
    }
}