<?php

namespace App\Services\AiSearch;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GeminiClient
{
    /** Codes HTTP pour lesquels une nouvelle tentative a une chance d'aboutir. */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    /* ===================== EMBEDDINGS ===================== */

    /**
     * Embeddings des morceaux à indexer.
     *
     * @param  array<int, array{title: string, text: string}>  $documents
     * @return array<int, float[]>  vecteurs normalisés, dans l'ordre de l'entrée
     */
    public function embedDocuments(array $documents): array
    {
        $inputs = array_map(function (array $doc) {
            // gemini-embedding-2 attend un format structuré ; pour 001 on préfixe simplement
            // le titre pour que chaque morceau garde le contexte de sa vidéo.
            if ($this->isEmbeddingV2()) {
                $title = $doc['title'] !== '' ? $doc['title'] : 'none';
                return "title: {$title} | text: {$doc['text']}";
            }

            return $doc['title'] !== '' ? "{$doc['title']}\n{$doc['text']}" : $doc['text'];
        }, array_values($documents));

        return $this->embed($inputs, 'RETRIEVAL_DOCUMENT');
    }

    /**
     * Embedding d'une requête utilisateur.
     *
     * @return float[]
     */
    public function embedQuery(string $query): array
    {
        $input = $this->isEmbeddingV2() ? "task: search result | query: {$query}" : $query;

        return $this->embed([$input], 'RETRIEVAL_QUERY')[0];
    }

    /**
     * @param  string[]  $inputs
     * @return array<int, float[]>
     */
    private function embed(array $inputs, string $taskType): array
    {
        $model = config('ai_search.embedding.model');
        $dimensions = config('ai_search.embedding.dimensions');
        $vectors = [];

        foreach (array_chunk($inputs, config('ai_search.embedding.batch_size')) as $batch) {
            $requests = array_map(function (string $text) use ($model, $dimensions, $taskType) {
                $request = [
                    'model'                => "models/{$model}",
                    'content'              => ['parts' => [['text' => $text]]],
                    'outputDimensionality' => $dimensions,
                ];

                // gemini-embedding-2 refuse taskType : la tâche est portée par le préfixe du texte.
                if (! $this->isEmbeddingV2()) {
                    $request['taskType'] = $taskType;
                }

                return $request;
            }, $batch);

            $json = $this->post(
                "models/{$model}:batchEmbedContents",
                ['requests' => $requests],
                config('ai_search.gemini.timeout')
            );

            foreach ($json['embeddings'] ?? [] as $embedding) {
                $vectors[] = self::normalize($embedding['values'] ?? []);
            }
        }

        // Un décalage ici associerait des vecteurs aux mauvais morceaux : on préfère échouer.
        if (count($vectors) !== count($inputs)) {
            throw new GeminiException('Nombre d\'embeddings inattendu : ' . count($vectors) . ' / ' . count($inputs));
        }

        return $vectors;
    }

    private function isEmbeddingV2(): bool
    {
        return str_starts_with(config('ai_search.embedding.model'), 'gemini-embedding-2');
    }

    /**
     * Norme L2 = 1, pour que le produit scalaire soit directement la similarité cosinus.
     * Obligatoire avec gemini-embedding-001 hors 3072 dimensions, sans effet sinon.
     *
     * @param  float[]  $vector
     * @return float[]
     */
    private static function normalize(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vector)));

        return $norm > 0 ? array_map(fn ($x) => $x / $norm, $vector) : $vector;
    }

    /* ===================== GÉNÉRATION ===================== */

    /**
     * Génération contrainte par un schéma JSON : la sortie est toujours un objet exploitable,
     * jamais du texte libre qu'il faudrait analyser.
     *
     * @param  array  $schema  JSON Schema de la réponse attendue
     * @return array{data: array, usage: array{input_tokens: int, output_tokens: int, thought_tokens: int}}
     */
    public function generateJson(string $systemInstruction, string $userText, array $schema): array
    {
        $model = config('ai_search.chat.model');

        $generationConfig = [
            'responseMimeType'   => 'application/json',
            'responseJsonSchema' => $schema,
            'maxOutputTokens'    => config('ai_search.chat.max_output_tokens'),
        ];
        if ($level = config('ai_search.chat.thinking_level')) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => $level];
        }

        $json = $this->post("models/{$model}:generateContent", [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => $userText]]]],
            'generationConfig'  => $generationConfig,
        ], config('ai_search.chat.timeout'));

        $candidate = $json['candidates'][0] ?? [];

        // Les parties marquées « thought » sont la réflexion du modèle, pas la réponse.
        $text = collect($candidate['content']['parts'] ?? [])
            ->reject(fn ($part) => $part['thought'] ?? false)
            ->pluck('text')
            ->implode('');

        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new GeminiException(
                'Réponse JSON invalide (finishReason : ' . ($candidate['finishReason'] ?? 'inconnu') . ')'
            );
        }

        $usage = $json['usageMetadata'] ?? [];

        return [
            'data'  => $data,
            'usage' => [
                'input_tokens'   => (int) ($usage['promptTokenCount'] ?? 0),
                'output_tokens'  => (int) ($usage['candidatesTokenCount'] ?? 0),
                'thought_tokens' => (int) ($usage['thoughtsTokenCount'] ?? 0),
            ],
        ];
    }

    /* ===================== HTTP ===================== */

    private function post(string $path, array $body, int $timeout): array
    {
        $apiKey = config('ai_search.gemini.api_key');
        if (empty($apiKey)) {
            throw new GeminiException('GEMINI_API_KEY manquant');
        }

        try {
            $response = Http::baseUrl(rtrim(config('ai_search.gemini.base_url'), '/'))
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->acceptJson()
                ->timeout($timeout)
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException
                        && in_array($e->response->status(), self::RETRYABLE_STATUSES, true)))
                ->post($path, $body);

            return $response->throw()->json() ?? [];
        } catch (RequestException $e) {
            throw new GeminiException(
                'Gemini a répondu ' . $e->response->status() . ' : ' . mb_substr($e->response->body(), 0, 500),
                $e->response->status(),
                $e
            );
        } catch (ConnectionException $e) {
            throw new GeminiException('Gemini injoignable : ' . $e->getMessage(), 0, $e);
        }
    }
}
