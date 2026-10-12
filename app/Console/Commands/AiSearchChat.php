<?php

namespace App\Console\Commands;

use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\GeminiException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Conversation avec l'assistant depuis le terminal, pour tester sans l'app.
 */
class AiSearchChat extends Command
{
    protected $signature = 'ai-search:chat {--user=1 : users.id au nom duquel la session est ouverte}';

    protected $description = 'Converse avec l\'assistant de recherche vidéo dans le terminal';

    public function handle(AiSearchService $service): int
    {
        $userId = (int) $this->option('user');
        $sessionId = null;
        $options = [];

        $this->line('Numéro = choisir une option · v = voir les résultats · n = nouvelle recherche · q = quitter');

        while (true) {
            $text = trim((string) $this->ask('Vous'));

            if ($text === 'q') {
                return self::SUCCESS;
            }
            if ($text === 'n') {
                $sessionId = null;
                $options = [];
                $this->line('— nouvelle recherche —');
                continue;
            }

            $input = match (true) {
                $text === 'v'                                => ['action' => 'show'],
                ctype_digit($text) && isset($options[$text]) => ['option_id' => $options[$text]],
                default                                      => ['message' => $text],
            };

            $start = microtime(true);
            try {
                $result = $service->handle($userId, $sessionId, $input);
            } catch (GeminiException $e) {
                $this->error($e->getMessage());
                continue;
            } catch (\Throwable $e) {
                $this->error(class_basename($e) . ' : ' . $e->getMessage());
                continue;
            }
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            $sessionId = $result['session_id'];
            $this->newLine();
            $this->info('Assistant : ' . $result['message']);

            if ($result['type'] === 'question') {
                $options = [];
                foreach ($result['options'] as $i => $option) {
                    $number = (string) ($i + 1);
                    $options[$number] = $option['id'];
                    $count = $option['count'] !== null ? " ({$option['count']})" : '';
                    $this->line("  {$number}) {$option['label']}{$count}");
                }
                // total null : la question ne porte pas sur une liste de résultats (édition introuvable).
                if ($result['total'] !== null) {
                    $this->line("  v) Voir les {$result['total']} résultats");
                }
            } else {
                $options = [];
                $rows = array_map(fn ($video) => [
                    $video->id_content,
                    Str::limit($video->title, 60),
                    $video->matched_chapter
                        ? sprintf('%d:%02d %s', intdiv($video->matched_chapter['seconds'], 60), $video->matched_chapter['seconds'] % 60, Str::limit($video->matched_chapter['label'], 40))
                        : '',
                ], $result['videos']);
                if ($rows) {
                    $this->table(['id', 'Titre', 'Chapitre'], $rows);
                }
            }

            $u = $result['usage'];
            $this->line(sprintf(
                '<fg=gray>tour %d · %d ms · tokens entrée %d, sortie %d, réflexion %d</>',
                $result['meta']['round'], $elapsed, $u['input_tokens'], $u['output_tokens'], $u['thought_tokens']
            ));
            $this->newLine();
        }
    }
}