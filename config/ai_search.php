<?php

return [

    /*
    | Interrupteur général : permet de couper l'assistant sans redéployer
    | (le bouton disparaît côté app dès que /status renvoie false).
    */
    'enabled' => (bool) env('AI_SEARCH_ENABLED', true),

    'gemini' => [
        'api_key'  => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        // Court volontairement : au-delà, l'utilisateur attend trop et on préfère échouer vite.
        'timeout'  => (int) env('GEMINI_TIMEOUT', 10),
    ],

    'embedding' => [
        // gemini-embedding-001 : texte seul, accepte taskType, moins cher que gemini-embedding-2.
        // Changer de modèle impose une réindexation complète (espaces vectoriels incompatibles),
        // ce que l'indexeur fait de lui-même car le modèle entre dans le hash.
        'model'      => env('AI_SEARCH_EMBED_MODEL', 'gemini-embedding-001'),
        // 768 : qualité quasi identique à 3072 pour 4 fois moins de stockage et de calcul.
        'dimensions' => (int) env('AI_SEARCH_EMBED_DIMS', 768),
        // Limite d'une requête batchEmbedContents.
        'batch_size' => 100,
    ],

    'chat' => [
        // Flash-Lite stable recommandé par Google pour les nouveaux projets ; sa réflexion
        // est déjà au niveau « minimal » par défaut, ce qui limite latence et coût.
        'model'             => env('AI_SEARCH_CHAT_MODEL', 'gemini-3.5-flash-lite'),
        // null = niveau par défaut du modèle. Ne renseigner que si le modèle accepte la valeur.
        'thinking_level'    => env('AI_SEARCH_CHAT_THINKING') ?: null,
        'max_output_tokens' => 1024,
        // Plus long que pour les embeddings : la génération prend 1 à 3 secondes.
        'timeout'           => (int) env('AI_SEARCH_CHAT_TIMEOUT', 15),
    ],

    // File dédiée : la file principale ne tourne qu'une fois par heure.
    'queue' => env('AI_SEARCH_QUEUE', 'ai-search'),

    'indexing' => [
        // Assez de mots pour garder le contexte, assez peu pour qu'un morceau reste centré sur une idée.
        'description_chunk_words' => 150,
    ],

    'retrieval' => [
        'vector_pool'    => 100,
        'keyword_pool'   => 100,
        'max_candidates' => 30,
        // Constante standard de la Reciprocal Rank Fusion : atténue l'écart entre les tout premiers rangs.
        'rrf_k'          => 60,
        // Un mot trouvé dans le titre ou le nom d'un intervenant compte plus qu'au fond de la description.
        'title_weight'   => 2.0,
        // Seuil sous lequel un résultat trouvé uniquement par similarité est ignoré.
        // null = pas de seuil. 0.65 calibré sur les tests : hors sujet ≤ 0.59, pertinent ≥ 0.67.
        'min_vector_score' => env('AI_SEARCH_MIN_VECTOR_SCORE') !== null
            ? (float) env('AI_SEARCH_MIN_VECTOR_SCORE')
            : null,
    ],

    'conversation' => [
        // À partir de ce nombre de vidéos pertinentes, l'assistant propose d'affiner.
        'show_threshold'      => 8,
        // Au-delà, on affiche quoi qu'il arrive : éviter qu'un utilisateur tourne en rond.
        'max_rounds'          => 3,
        'max_results'         => 20,
        'max_options'         => 5,
        'session_ttl_minutes' => 30,
        'max_message_length'  => 300,
        // Chaque tour coûte un appel LLM : plafond par utilisateur contre les abus.
        'rate_limit_per_hour' => 30,
    ],

    'health' => [
        // Erreurs consécutives avant de masquer l'assistant.
        'failure_threshold'       => 3,
        'cooldown_minutes'        => 5,
        // Crédit épuisé : inutile de réessayer avant que quelqu'un recharge le compte.
        'credit_cooldown_minutes' => 30,
    ],
];
