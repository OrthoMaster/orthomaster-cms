<?php

namespace App\Support\Scat6;

/**
 * Textes SCAT6 (version française) utilisés par le serveur : validation et rapport PDF.
 *
 * Même contenu que l'application (src/scat6/core/content.ts) : toute modification d'un libellé
 * ou d'un code se fait dans les deux fichiers, sinon le PDF ne correspond plus à l'écran.
 */
final class Scat6Content
{
    /** Version du contenu enregistrée avec chaque examen (rapports anciens lisibles si le texte change). */
    public const CONTENT_VERSION = 'scat6-fr-om-2026.11';

    public const SPORTS = [
        'football'     => 'Football',
        'judo'         => 'Judo',
        'hockey_glace' => 'Hockey sur glace',
        'general'      => 'Général (autre sport)',
    ];

    public const EXAM_KINDS = [
        'post_choc' => 'SCAT6 après un choc',
        'retest'    => 'Re-test (J+1 / avant 72 h)',
        'baseline'  => 'Baseline (début de saison)',
    ];

    public const NEURO = [
        'normal'      => 'Normal',
        'anormal'     => 'Anormal',
        'non_realise' => 'Non réalisé',
    ];

    public const DIAGNOSIS = [
        'oui'     => 'Oui',
        'non'     => 'Non',
        'differe' => 'Différé',
    ];

    public const WORD_LIST_IDS = ['A', 'B', 'C'];

    /** Étapes que le médecin peut noter « non réalisé » (libellés du rapport). */
    public const STEPS = [
        'symptomes'        => 'Symptômes',
        'orientation'      => 'Orientation',
        'memoire'          => 'Mémoire immédiate',
        'concentration'    => 'Concentration',
        'equilibre'        => 'Équilibre (mBESS)',
        'tandem'           => 'Marche sur une ligne',
        'memoire-differee' => 'Mémoire différée',
    ];

    public const BACKGROUND = [
        'H.1' => 'Hospitalisation en raison d’une blessure à la tête ?',
        'H.2' => 'Maux de tête ou migraines diagnostiqués ou traités ?',
        'H.3' => 'Trouble d’apprentissage ou dyslexie ?',
        'H.4' => 'Trouble du déficit de l’attention / hyperactivité (TDA/H) ?',
        'H.5' => 'Dépression, anxiété ou autre trouble psychologique ?',
        'H.6' => 'Antécédents neurologiques ?',
    ];

    public const SYMPTOMS = [
        'S.1'  => 'Maux de tête',
        'S.2'  => 'Impression de « pression dans la tête »',
        'S.3'  => 'Douleur au cou',
        'S.4'  => 'Nausée ou vomissements',
        'S.5'  => 'Étourdissements',
        'S.6'  => 'Vision trouble',
        'S.7'  => 'Problèmes d’équilibre',
        'S.8'  => 'Sensibilité à la lumière',
        'S.9'  => 'Sensibilité au bruit',
        'S.10' => 'Sensation d’être au ralenti',
        'S.11' => 'Sensation d’être « dans un brouillard »',
        'S.12' => 'Sensation de « ne pas être dans son assiette »',
        'S.13' => 'Problème de concentration',
        'S.14' => 'Problème de mémoire',
        'S.15' => 'Fatigue ou manque d’énergie',
        'S.16' => 'Confusion',
        'S.17' => 'Somnolence',
        'S.18' => 'Émotivité accrue',
        'S.19' => 'Irritabilité',
        'S.20' => 'Tristesse',
        'S.21' => 'Nervosité ou anxiété',
        'S.22' => 'Difficulté à s’endormir (si applicable)',
    ];

    public const SYMPTOM_MAX_SEVERITY = 6;

    public const ORIENTATION = [
        'O.1' => 'Quel mois sommes-nous ?',
        'O.2' => 'Quelle est la date d’aujourd’hui ?',
        'O.3' => 'Quel jour de la semaine sommes-nous ?',
        'O.4' => 'En quelle année sommes-nous ?',
        'O.5' => 'Quelle heure est-il actuellement ? (à l’heure près)',
    ];

    public const WORD_LISTS = [
        'A' => ['Menton', 'Monnaie', 'Rideau', 'Lampe', 'Sucre', 'Feuille', 'Pêche', 'Oiseau', 'Viande', 'Bateau'],
        'B' => ['Bébé', 'Poisson', 'Parfum', 'Jambe', 'Tapis', 'Pomme', 'Fumée', 'Écran', 'Chaise', 'Balle'],
        'C' => ['Veste', 'Couteau', 'Chemin', 'Chapeau', 'Miroir', 'Beurre', 'Tissu', 'Film', 'Souris', 'Dessin'],
    ];

    public const MBESS_STANCES = ['Pieds joints', 'Tandem', 'Unipodal'];

    public const ATTESTATION = 'Je suis un professionnel de santé. J’ai personnellement effectué ou supervisé cette évaluation menée à l’aide du SCAT6.';

    public const DISCLAIMER = 'Le SCAT6 ne doit pas être utilisé seul pour poser ou exclure le diagnostic. Un athlète peut être commotionné même avec des scores normaux.';

    /** Consignes après une commotion diagnostiquée ou en attente (FFF 2025, SCAT6, CRT6). */
    public const CONSIGNES = [
        'surveillance' => [
            'Ne restez pas seul·e, surtout pendant les 3 premières heures.',
            'Repos cérébral : limitez les écrans, la lecture et les efforts.',
            'Pas de retour au sport aujourd’hui. Seul un médecin peut autoriser la reprise.',
        ],
        'interdits' => [
            'Pas d’alcool ni de drogue.',
            'Aucun médicament sans avis médical — en particulier ni aspirine, ni anti-inflammatoires, ni sédatifs, ni opiacés.',
            'Ne conduisez pas tant qu’un professionnel de santé ne l’a pas autorisé.',
        ],
        'appeler_si' => [
            'Maux de tête qui s’aggravent',
            'Vomissements répétés',
            'Confusion, somnolence, comportement inhabituel',
            'Convulsions, faiblesse d’un membre, vision double',
        ],
    ];

    private const GRTP = 'Reprise progressive en 6 étapes, au moins 24 h par étape sans symptôme ; retour à l’étape précédente si les symptômes réapparaissent.';

    /**
     * Règles de reprise de la fédération. Le niveau (pro / amateur, haut niveau) n'est pas demandé :
     * les deux cas sont listés. `age` null = inconnu → toutes les tranches d'âge.
     *
     * @return string[]
     */
    public static function returnLines(string $sport, ?int $age): array
    {
        switch ($sport) {
            case 'football':
                return array_values(array_filter([
                    'Football professionnel : examen par un médecin expert reconnu par la FFF dans les 72 h.',
                    'Football amateur : consulter un médecin généraliste ou un neurologue dans les 48 h.',
                    self::GRTP,
                    ($age === null || $age >= 20) ? 'Après une 1re commotion : pas de compétition avant 7 jours.' : null,
                    ($age === null || $age < 20) ? 'Moins de 20 ans : pas de reprise avant 3 semaines, avec l’accord d’un neurologue expert.' : null,
                    'Prudence renforcée avant 18 ans ou en cas de commotion dans les 12 derniers mois.',
                    'Récidive : délai plus long et avis spécialisé recommandé.',
                ]));
            case 'judo':
                return array_values(array_filter([
                    '48 h de repos strict (physique et intellectuel), jamais seul.',
                    'Examen médical à 48 h par un médecin référent FFJudo (SCAT6).',
                    'Suspicion levée : reprise avec certificat (CACI) et déverrouillage par le médecin.',
                    ($age === null || $age < 20) ? 'Moins de 20 ans : arrêt de 3 semaines, avec accord médical.' : null,
                    ($age === null || $age >= 20) ? 'Amateur : pas de reprise avant 10 jours d’arrêt.' : null,
                    ($age === null || $age >= 20) ? 'Haut niveau (SHN / PPF) : arrêt de 7 jours ; protocole de reprise PRJJ de J3 à J7 (5 jours) ; bilan de reprise à partir de J8.' : null,
                    'Récidive dans l’année : 3 semaines d’arrêt ; 3e commotion : 6 semaines et avis spécialisé.',
                ]));
            case 'hockey_glace':
                return [
                    'Pas de retour au jeu avant 7 jours minimum, avec autorisation médicale.',
                    self::GRTP,
                    'Rapport de commotion à transmettre à la FFHG (par les arbitres).',
                ];
            default:
                return [
                    'Pas de reprise le jour même ; au moins 24 h de repos relatif.',
                    self::GRTP,
                    'Autorisation médicale avant tout contact ou compétition.',
                    'Vérifier si la fédération du sport impose un délai minimal.',
                ];
        }
    }

    /* ===================== RETOUR AU SPORT ===================== */

    /** Les 6 étapes (consensus d'Amsterdam 2022). Même texte que src/scat6/core/rts.ts. */
    public const RTS_STEPS = [
        1 => ['title' => 'Activités limitées par les symptômes', 'detail' => 'Vie quotidienne qui n’aggrave pas les symptômes ; reprise progressive du travail ou de l’école.'],
        2 => ['title' => 'Exercice aérobie léger puis modéré', 'detail' => 'Marche rapide, vélo d’appartement ; effort progressif, sans musculation lourde.'],
        3 => ['title' => 'Exercices individuels spécifiques au sport', 'detail' => 'Course, changements de direction, technique ; aucun risque de choc à la tête.'],
        4 => ['title' => 'Entraînement sans contact', 'detail' => 'Intensité élevée, exercices plus complexes, avec l’équipe.'],
        5 => ['title' => 'Entraînement avec contact complet', 'detail' => 'Après accord médical.'],
        6 => ['title' => 'Retour à la compétition', 'detail' => 'Décision médicale.'],
    ];

    /**
     * Délai minimal avant la compétition (jours après le choc), selon la fédération et l'âge.
     * null = pas de délai fixe (décision médicale). Le niveau n'est pas connu : valeur la plus prudente
     * du cas courant, l'autre cas figure dans returnLines().
     */
    public static function minCompetitionDays(string $sport, ?int $age): ?int
    {
        $young = $age !== null && $age < 20;

        return match ($sport) {
            'football'     => $young ? 21 : 7,
            'judo'         => $young ? 21 : 10,
            'hockey_glace' => 7,
            default        => null,
        };
    }

    /** Où va le certificat de reprise, selon la fédération (règlements en vigueur, saison 2025-2026). */
    public const CERTIFICATE_ROUTING = [
        'football'     => 'Certificat à remettre au joueur et, si demandé, au médecin du club. Football professionnel : examen préalable par un médecin expert en commotion reconnu par la FFF.',
        'judo'         => 'Certificat d’absence de contre-indication (CACI) à transmettre à un médecin référent FFJudo pour le déverrouillage de l’arrêt temporaire de pratique.',
        'hockey_glace' => 'Certificat médical de reprise post-commotion à transmettre à la FFHG (medical@ffhg.eu) : la licence reste bloquée jusqu’à sa réception.',
        'general'      => 'À transmettre selon le règlement médical de la fédération concernée.',
    ];

    /** Âge à partir de l'année de naissance seule (peut avoir un an de trop avant l'anniversaire). */
    public static function ageFromYear(?int $year): ?int
    {
        return $year ? (int) now()->year - $year : null;
    }
}
