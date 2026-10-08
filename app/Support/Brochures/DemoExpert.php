<?php

namespace App\Support\Brochures;

/**
 * Données de démonstration pour /expert_demo (médecin fictif).
 * Même structure que les brouillons de /demo/{slug} (voir BrochureDraft).
 */
class DemoExpert
{
    public const NAME = 'Camille Laurent';

    public static function data(): array
    {
        return BrochureDraft::normalize([
            'palette'     => 'turquoise',
            'model'       => 'spine',
            'honorific'   => 'Docteur',
            'credentials' => 'Médecin du sport, MSc Sports Performance, D.I.U. Échographie musculo-squelettique',
            'expertise'   => [2, 9, 7, 8, 3], // ids de ExpertiseCatalog
            'lede'        => 'J’accompagne les sportifs amateurs et confirmés, de la prévention à la reprise après blessure, avec une approche fondée sur l’échographie et la rééducation active.',
            'places'      => [
                [
                    'name'    => 'Institut de médecine du sport',
                    'address' => '15 avenue de la République',
                    'city'    => '69002 Lyon',
                ],
                [
                    'name'    => '',
                    'address' => '',
                    'city'    => 'Annecy',
                ],
            ],
            'doctolib'    => 'https://www.doctolib.fr/',
            'linkedin'    => 'https://www.linkedin.com/',
            'facebook'    => 'https://www.facebook.com/',
            'titles'      => [
                'formation' => 'Formation',
                'parcours'  => 'Parcours',
            ],
            'formation'   => [
                ['year' => '2019', 'title' => 'D.I.U. Échographie musculo-squelettique', 'place' => 'Université Claude Bernard Lyon 1'],
                ['year' => '2017', 'title' => 'MSc Sports Performance', 'place' => 'Loughborough University, Royaume-Uni'],
                ['year' => '2016', 'title' => 'Capacité de médecine et biologie du sport', 'place' => 'Faculté de médecine Lyon Est'],
                ['year' => '2014', 'title' => 'Doctorat en médecine', 'place' => 'Faculté de médecine de Grenoble'],
            ],
            'parcours'    => [
                ['year' => 'Depuis 2022', 'title' => 'Médecin du sport, consultation et échographie', 'place' => 'Institut de médecine du sport, Lyon'],
                ['year' => 'Depuis 2020', 'title' => 'Médecin de l’équipe régionale de ski alpin', 'place' => 'Comité de ski Mont-Blanc, Annecy'],
                ['year' => '2018–2022', 'title' => 'Praticien attaché en médecine du sport', 'place' => 'Hôpital Édouard Herriot, Lyon'],
                ['year' => '2016–2018', 'title' => 'Assistant spécialiste en médecine physique et de réadaptation', 'place' => 'Centre hospitalier Annecy Genevois'],
                ['year' => '2010–2014', 'title' => 'Interne des hôpitaux', 'place' => 'CHU Grenoble Alpes'],
            ],
        ]);
    }

    /** Vidéos de démonstration (bloc OrthoMaster). */
    public static function videos(): array
    {
        return [
            [
                'title'       => 'Entorse de la cheville : quand reprendre le sport ?',
                'description' => 'Les étapes de la reprise et les signes qui doivent alerter.',
                'duration'    => '6:12',
                'url'         => '#',
            ],
            [
                'title'       => 'L’échographie au service du sportif',
                'description' => 'Ce que l’échographie apporte au diagnostic des lésions musculaires.',
                'duration'    => '4:38',
                'url'         => '#',
            ],
            [
                'title'       => 'Tendinopathie d’Achille : le point en 5 minutes',
                'description' => 'Mécanisme, examen clinique et principes de la rééducation.',
                'duration'    => '5:04',
                'url'         => '#',
            ],
        ];
    }
}