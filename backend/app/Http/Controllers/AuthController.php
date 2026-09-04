<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Mail\ResetPasswordMail;
use App\Mail\PasswordChangedMail;
use App\Mail\TwoFactorCodeMail;

class AuthController extends Controller
{
    /**
     * Tentative de connexion avec rate limiting et logging
     * POST /api/login
     */
    public function login(Request $request)
    {
        $rateLimitKey = 'login:' . $request->ip() . ':' . $request->email;
        $executed = RateLimiter::attempt(
            $rateLimitKey,
            5,
            function () {},
            300
        );

        if (!$executed) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'message' => "Trop de tentatives. Réessayez dans $seconds secondes."
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email:strict|max:100',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', strtolower($request->email))->first();

        if (!$user) {
            RateLimiter::hit($rateLimitKey);
            return response()->json(['message' => 'Identifiants incorrects'], 401);
        }

        // Vérifier le mot de passe AVANT les autres vérifications
        if (!Hash::check($request->password, $user->password)) {
            RateLimiter::hit($rateLimitKey);

            Log::create([
                'user_id' => $user->id,
                'action' => 'login_failed',
                'details' => json_encode(['reason' => 'Mot de passe incorrect']),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Identifiants incorrects'], 401);
        }

        // Vérifier si le compte est actif
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.',
                'account_disabled' => true
            ], 403);
        }

        // Les managers n'ont pas besoin d'approbation car l'admin les crée déjà approuvés
        // Cette vérification est supprimée car inutile

        // ============================================================
        // 🔐 2FA pour les administrateurs UNIQUEMENT
        // ============================================================
        if ($user->role === 'admin') {
            // Générer un code à 6 chiffres
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Stocker le code avec expiration (10 minutes)
            $user->update([
                'two_factor_code' => $code,
                'two_factor_expires_at' => now()->addMinutes(10),
                'two_factor_verified_at' => null,
            ]);

            // Envoyer le code par email
            try {
                Mail::to($user->email)->send(new TwoFactorCodeMail($code, $user));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Erreur envoi code 2FA', [
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
                return response()->json([
                    'message' => 'Erreur lors de l\'envoi du code de vérification'
                ], 500);
            }

            Log::create([
                'user_id' => $user->id,
                'action' => 'two_factor_code_sent',
                'details' => json_encode(['ip' => $request->ip()]),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Un code de vérification a été envoyé à votre adresse email.',
                'requires_two_factor' => true,
                'user_id' => $user->id,
                'email' => $user->email,
                'expires_in' => 600,
            ], 200);
        }

        // ============================================================
        // Connexion normale pour les managers (documentalistes)
        // ============================================================

        $isFirstLogin = is_null($user->password_changed_at);
        $user->update(['last_login_at' => now()]);

        // Les managers ont le rôle 'manager'
        $abilities = $user->role === 'admin' ? ['admin'] : ['manager'];
        $token = $user->createToken(
            'auth-token',
            $abilities,
            now()->addHours(24)
        )->plainTextToken;

        Log::create([
            'user_id' => $user->id,
            'action' => 'login_success',
            'details' => json_encode(['is_first_login' => $isFirstLogin]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Connexion réussie',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
            'is_first_login' => $isFirstLogin,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'firstname' => $user->firstname,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'is_approved' => $user->is_approved,
            ]
        ]);
    }

    // ============================================================
    // INSCRIPTION - UNIQUEMENT PAR L'ADMIN
    // ============================================================

    /**
     * Créer un manager (uniquement par l'admin)
     * POST /api/admin/users
     */
    public function createManager(Request $request)
    {
        // Vérifier que l'utilisateur connecté est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Accès non autorisé. Seul un administrateur peut créer des managers.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'firstname' => 'nullable|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'email' => 'required|email:strict|max:100|unique:users',
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(10)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Créer le manager avec compte actif et approuvé
        $user = User::create([
            'name' => trim($request->name),
            'firstname' => $request->firstname ? trim($request->firstname) : null,
            'email' => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
            'role' => 'manager',
            'is_active' => true,
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id, // L'admin qui crée
        ]);

        Log::create([
            'user_id' => $request->user()->id,
            'action' => 'manager_created',
            'details' => json_encode([
                'manager_id' => $user->id,
                'manager_email' => $user->email,
                'created_by' => $request->user()->email
            ]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Manager créé avec succès. Le compte est actif et approuvé.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'firstname' => $user->firstname,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'is_approved' => $user->is_approved,
                ]
            ]
        ], 201);
    }

    // ============================================================
    // 2FA - RENVOYER LE CODE
    // ============================================================

    /**
     * Renvoyer un nouveau code 2FA
     * POST /api/login/resend-code
     */
    public function resendTwoFactorCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($request->user_id);

        // Vérifier que l'utilisateur est un admin
        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Accès non autorisé'
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Votre compte est désactivé'
            ], 403);
        }

        // Générer un nouveau code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10),
            'two_factor_verified_at' => null,
        ]);

        try {
            Mail::to($user->email)->send(new TwoFactorCodeMail($code, $user));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur envoi code 2FA', [
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Erreur lors de l\'envoi du code de vérification'
            ], 500);
        }

        Log::create([
            'user_id' => $user->id,
            'action' => 'two_factor_code_resent',
            'details' => json_encode(['ip' => $request->ip()]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Un nouveau code de vérification a été envoyé à votre adresse email.',
            'expires_in' => 600,
        ]);
    }

    // ============================================================
    // 2FA - VÉRIFIER LE CODE
    // ============================================================

    /**
     * Vérifier le code 2FA pour l'administrateur
     * POST /api/login/verify-2fa
     */
    public function verifyTwoFactor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($request->user_id);

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Accès non autorisé'
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Votre compte est désactivé'
            ], 403);
        }

        // Vérifier le code
        if (!$user->two_factor_code || $user->two_factor_code !== $request->code) {
            Log::create([
                'user_id' => $user->id,
                'action' => 'two_factor_failed',
                'details' => json_encode(['reason' => 'Code incorrect', 'ip' => $request->ip()]),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Code invalide'
            ], 401);
        }

        // Vérifier l'expiration
        if (!$user->two_factor_expires_at || now()->gt($user->two_factor_expires_at)) {
            Log::create([
                'user_id' => $user->id,
                'action' => 'two_factor_expired',
                'details' => json_encode(['ip' => $request->ip()]),
                'ip_address' => $request->ip(),
            ]);

            $user->update([
                'two_factor_code' => null,
                'two_factor_expires_at' => null,
            ]);

            return response()->json([
                'message' => 'Le code a expiré. Veuillez demander un nouveau code.'
            ], 401);
        }

        // Code valide - Connexion réussie
        $user->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
            'two_factor_verified_at' => now(),
            'last_login_at' => now(),
        ]);

        $token = $user->createToken(
            'auth-token',
            ['admin'],
            now()->addHours(24)
        )->plainTextToken;

        Log::create([
            'user_id' => $user->id,
            'action' => 'login_success',
            'details' => json_encode(['two_factor_verified' => true, 'ip' => $request->ip()]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Connexion réussie',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'firstname' => $user->firstname,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'is_approved' => $user->is_approved,
            ]
        ]);
    }

    // ============================================================
    // DÉCONNEXION
    // ============================================================

    public function logout(Request $request)
    {
        $user = $request->user();

        Log::create([
            'user_id' => $user->id,
            'action' => 'logout',
            'details' => json_encode(['message' => 'Déconnexion']),
            'ip_address' => $request->ip(),
        ]);

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    // ============================================================
    // UTILISATEUR CONNECTÉ
    // ============================================================

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'firstname' => $user->firstname,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'is_approved' => $user->is_approved,
            'last_login_at' => $user->last_login_at,
            'password_changed_at' => $user->password_changed_at,
            'created_at' => $user->created_at,
        ]);
    }

    // ============================================================
    // PROFIL
    // ============================================================

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'firstname' => 'sometimes|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'email' => 'sometimes|email:strict|max:100|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only(['name', 'firstname', 'email']));

        Log::create([
            'user_id' => $user->id,
            'action' => 'profile_updated',
            'details' => json_encode(['message' => 'Profil utilisateur mis à jour']),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Profil mis à jour',
            'user' => $user->only(['id', 'name', 'firstname', 'email', 'role'])
        ]);
    }

    // ============================================================
    // CHANGER LE MOT DE PASSE
    // ============================================================

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'confirmed',
                PasswordRule::min(10)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect'], 401);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json(['message' => 'Le nouveau mot de passe doit être différent'], 422);
        }

        $wasFirstLogin = is_null($user->password_changed_at);

        $user->update([
            'password' => Hash::make($request->new_password),
            'password_changed_at' => now(),
        ]);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        try {
            Mail::to($user->email)->send(new PasswordChangedMail($user));
        } catch (\Exception $e) {
            Log::warning('Email de confirmation non envoyé', ['error' => $e->getMessage()]);
        }

        Log::create([
            'user_id' => $user->id,
            'action' => 'password_changed',
            'details' => json_encode(['was_first_login' => $wasFirstLogin]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Mot de passe mis à jour avec succès']);
    }

    // ============================================================
    // MOT DE PASSE OUBLIÉ
    // ============================================================

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email:strict|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', strtolower($request->email))
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Si cet email existe, un lien a été envoyé'], 200);
        }

        $token = Str::random(60);
        $hashedToken = hash('sha256', $token);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $hashedToken, 'created_at' => now()]
        );

        $resetUrl = "https://redacteur.benin-digital.app/reset-password?token={$token}&email=" . urlencode($user->email);

        try {
            Mail::to($user->email)->send(new ResetPasswordMail($resetUrl, $user));
        } catch (\Exception $e) {
            Log::error('Erreur envoi email réinitialisation', [
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors de l\'envoi du lien',
                'reset_link' => app()->environment('local') ? $resetUrl : null
            ], 500);
        }

        Log::create([
            'user_id' => $user->id,
            'action' => 'password_reset_requested',
            'details' => json_encode(['message' => 'Demande de réinitialisation de mot de passe']),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Un lien de réinitialisation a été envoyé à votre adresse email'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email:strict|max:100',
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(10)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', strtolower($request->email))
            ->first();

        if (!$resetRecord || !hash_equals($resetRecord->token, hash('sha256', $request->token))) {
            return response()->json(['message' => 'Token invalide'], 400);
        }

        if (now()->diffInMinutes($resetRecord->created_at) > 60) {
            return response()->json(['message' => 'Token expiré'], 400);
        }

        $user = User::where('email', strtolower($request->email))->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 404);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
        ]);

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $user->tokens()->delete();

        try {
            Mail::to($user->email)->send(new PasswordChangedMail($user, 'reset'));
        } catch (\Exception $e) {
            Log::warning('Email de confirmation non envoyé', ['error' => $e->getMessage()]);
        }

        Log::create([
            'user_id' => $user->id,
            'action' => 'password_reset_completed',
            'details' => json_encode(['message' => 'Mot de passe réinitialisé avec succès']),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès']);
    }
}
