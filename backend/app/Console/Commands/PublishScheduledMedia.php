<?php
// app/Console/Commands/PublishScheduledMedia.php

namespace App\Console\Commands;

use App\Models\ScheduledTask;
use App\Models\UserMedia;
use App\Models\SocialConfig;
use App\Models\Log as LogModel;
use App\Http\Controllers\SocialConfigController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublishScheduledMedia extends Command
{
    protected $signature = 'media:publish-scheduled';
    protected $description = 'Publie les medias programmes dont l\'heure est arrivee';

    public function handle()
    {
        $this->info('[INFO] Recherche des medias a publier...');

        $tasks = ScheduledTask::where('status', 'pending')
            ->where('type', 'media')
            ->whereNotNull('media_id')
            ->where('scheduled_at', '<=', now())
            ->with('media')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('[OK] Aucun media a publier.');
            return 0;
        }

        $this->info("[INFO] {$tasks->count()} media(s) a publier.");
        $published = 0;
        $errors = 0;

        foreach ($tasks as $task) {
            $this->info("[INFO] Traitement de la tache #{$task->id}");

            $media = $task->media;
            $platform = $task->platform;

            if (!$media) {
                $task->update([
                    'status' => 'failed',
                    'error_message' => 'Media introuvable',
                    'processed_at' => now()
                ]);
                $this->error("[ERREUR] Media introuvable pour la tache #{$task->id}");
                $errors++;
                continue;
            }

            $this->info("   - Media: {$media->file_name} (ID: {$media->id})");
            $this->info("   - Plateforme: {$platform}");
            $this->info("   - URL: {$media->file_url}");

            $field = "published_to_{$platform}";
            if ($media->$field) {
                $task->update(['status' => 'executed', 'processed_at' => now()]);
                $this->info("[INFO] Media deja publie sur {$platform}");
                continue;
            }

            $socialConfig = SocialConfig::where('user_id', $task->user_id)
                ->where('platform', $platform)
                ->where('is_connected', true)
                ->first();

            if (!$socialConfig) {
                $task->update([
                    'status' => 'failed',
                    'error_message' => "Compte {$platform} non configure",
                    'processed_at' => now()
                ]);
                $this->error("[ERREUR] Compte {$platform} non configure");
                $errors++;
                continue;
            }

            $this->info("   - Compte social trouve: ID {$socialConfig->id}");

            try {
                $request = new Request();
                $request->merge([
                    'media_id' => $media->id,
                    'caption' => $task->caption ?? '',
                ]);

                if ($media->user) {
                    $request->setUserResolver(function () use ($media) {
                        return $media->user;
                    });
                }

                $controller = new SocialConfigController();
                
                $this->info("   - Appel de la methode publishTo" . ucfirst($platform));
                
                if ($platform === 'instagram') {
                    $response = $controller->publishToInstagram($request);
                } elseif ($platform === 'tiktok') {
                    $response = $controller->publishToTikTok($request);
                } else {
                    throw new \Exception("Plateforme {$platform} non supportee");
                }

                $result = json_decode($response->getContent(), true);
                
                $this->info("   - Reponse: " . json_encode($result));

                if (isset($result['success']) && $result['success'] === true) {
                    $task->update([
                        'status' => 'executed',
                        'processed_at' => now(),
                        'metadata' => array_merge($task->metadata ?? [], ['result' => $result])
                    ]);

                    LogModel::create([
                        'user_id' => $task->user_id,
                        'action' => "scheduled_media_published_{$platform}",
                        'details' => "Publication programmee sur {$platform}: {$media->file_name}",
                        'ip_address' => 'system'
                    ]);

                    $this->info("[OK] Publie sur {$platform}");
                    $published++;
                } else {
                    $error = $result['error'] ?? $result['message'] ?? 'Erreur inconnue';
                    $task->update([
                        'status' => 'failed',
                        'error_message' => $error,
                        'processed_at' => now()
                    ]);
                    $this->error("[ERREUR] Echec: " . $error);
                    $errors++;
                }

            } catch (\Exception $e) {
                $task->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'processed_at' => now()
                ]);
                $this->error("[ERREUR] Exception: " . $e->getMessage());
                Log::error('Erreur publication programmee media', [
                    'media_id' => $media->id,
                    'platform' => $platform,
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }

        $this->info("[INFO] Resume: {$published} publies, {$errors} erreurs");
        return $errors > 0 ? 1 : 0;
    }
}