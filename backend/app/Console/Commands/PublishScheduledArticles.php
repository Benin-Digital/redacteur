<?php
// app/Console/Commands/PublishScheduledArticles.php

namespace App\Console\Commands;

use App\Models\ScheduledTask;
use App\Models\Article;
use App\Http\Controllers\ManagerController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledArticles extends Command
{
    protected $signature = 'articles:publish-scheduled';
    protected $description = 'Publie les articles programmés dont l\'heure est arrivée';

    public function handle()
    {
        $this->info('🔍 Recherche des articles à publier...');

        //  Filtrer UNIQUEMENT les tâches de type 'article' avec un article_id
        $tasks = ScheduledTask::where('status', 'pending')
            ->where('type', 'article')
            ->whereNotNull('article_id')
            ->where('scheduled_at', '<=', now())
            ->with('article.user')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info(' Aucun article à publier.');
            return 0;
        }

        $this->info(" {$tasks->count()} article(s) à publier.");
        $published = 0;
        $errors = 0;

        foreach ($tasks as $task) {
            $article = $task->article;
            
            if (!$article) {
                $task->update([
                    'status' => 'failed',
                    'error_message' => 'Article introuvable',
                    'processed_at' => now()
                ]);
                $this->error(" Article introuvable pour la tâche #{$task->id}");
                $errors++;
                continue;
            }

            if ($article->statut === 'publie') {
                $task->update(['status' => 'executed', 'processed_at' => now()]);
                $this->info(" Article déjà publié: {$article->titre}");
                continue;
            }

            $this->info(" Publication de l'article: {$article->titre}");

            try {
                $controller = new ManagerController();
                $request = new \Illuminate\Http\Request();
                $request->setUserResolver(function () use ($article) {
                    return $article->user;
                });

                $response = $controller->publishArticle($article->id, $request);
                $result = $response->getData();

                if ($result->success ?? false) {
                    $task->update([
                        'status' => 'executed',
                        'processed_at' => now()
                    ]);
                    $this->info(" Publié: {$article->titre}");
                    $published++;
                } else {
                    $task->update([
                        'status' => 'failed',
                        'error_message' => $result->error ?? 'Erreur inconnue',
                        'processed_at' => now()
                    ]);
                    $this->error(" Échec: {$article->titre} - " . ($result->error ?? 'Erreur inconnue'));
                    $errors++;
                }

            } catch (\Exception $e) {
                $task->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'processed_at' => now()
                ]);
                $this->error(" Exception: {$article->titre} - " . $e->getMessage());
                Log::error('Erreur publication programmée article', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }

        $this->info(" Résumé: {$published} publiés, {$errors} erreurs");
        return $errors > 0 ? 1 : 0;
    }
}