<?php

namespace App\Services\Google;

use Google\Client;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Meet;
use Google\Service\Meet\EndActiveConferenceRequest;
use Google\Service\Meet\Space;
use Google\Service\Meet\SpaceConfig;
use RuntimeException;

/**
 * Création de salles Google Meet via l'API Meet v2 (spaces.create).
 *
 * Authentification : compte de service Google Cloud avec délégation au niveau du domaine
 * (autorisée une fois par admin@orthomaster.fr dans la console Admin). Le compte de service
 * agit au nom de GOOGLE_MEET_SUBJECT (info@orthomaster.fr), qui devient propriétaire des salles.
 *
 * accessType OPEN : toute personne qui a le lien entre sans attendre d'être admise
 * (personne n'est là côté OrthoMaster pour admettre les participants).
 */
class GoogleMeetService
{
    public const SCOPE = 'https://www.googleapis.com/auth/meetings.space.created';

    /**
     * @return array{name:string, url:string, code:string}
     */
    public function createSpace(): array
    {
        $config = new SpaceConfig();
        $config->setAccessType(config('services.google_meet.access_type', 'OPEN'));
        $config->setEntryPointAccess('ALL');

        $space = new Space();
        $space->setConfig($config);

        try {
            $created = $this->meet()->spaces->create($space);
        } catch (GoogleServiceException $e) {
            throw new RuntimeException('Google Meet : ' . $this->googleMessage($e), 0, $e);
        } catch (\Throwable $e) {
            // Erreur d'obtention du jeton (délégation absente, clé invalide…).
            throw new RuntimeException('Google Meet (authentification) : ' . $this->authHint($e->getMessage()), 0, $e);
        }

        if (!$created->getMeetingUri()) {
            throw new RuntimeException("Google Meet n'a pas renvoyé de lien.");
        }

        return [
            'name' => (string) $created->getName(),
            'url'  => (string) $created->getMeetingUri(),
            'code' => (string) $created->getMeetingCode(),
        ];
    }

    /**
     * Rend une salle inutilisable. L'API Meet ne permet pas de supprimer une salle :
     *  1. la réunion en cours éventuelle est terminée (tout le monde est sorti) ;
     *  2. l'accès passe à RESTRICTED : seul le propriétaire (info@) et les invités
     *     explicites peuvent encore entrer, l'ancien lien ne sert plus à personne d'autre.
     *
     * @param string $name identifiant Google de la salle (« spaces/xxxx »)
     */
    public function closeSpace(string $name): void
    {
        $meet = $this->meet();

        try {
            $meet->spaces->endActiveConference($name, new EndActiveConferenceRequest());
        } catch (GoogleServiceException $e) {
            // Aucune réunion en cours : c'est le cas normal, on continue.
        }

        $config = new SpaceConfig();
        $config->setAccessType('RESTRICTED');

        $space = new Space();
        $space->setConfig($config);

        try {
            $meet->spaces->patch($name, $space, ['updateMask' => 'config.accessType']);
        } catch (GoogleServiceException $e) {
            // Salle déjà disparue côté Google : rien à fermer.
            if ($e->getCode() === 404) {
                return;
            }
            throw new RuntimeException('Google Meet : ' . $this->googleMessage($e), 0, $e);
        } catch (\Throwable $e) {
            throw new RuntimeException('Google Meet (authentification) : ' . $this->authHint($e->getMessage()), 0, $e);
        }
    }

    /* ===================== OUTILS ===================== */

    private function meet(): Meet
    {
        if (!class_exists(Meet::class)) {
            throw new RuntimeException(
                "La classe Google\\Service\\Meet est absente : mettez à jour google/apiclient-services (composer update google/apiclient-services)."
            );
        }

        $path    = config('services.google_meet.credentials');
        $subject = config('services.google_meet.subject');

        if (!$path || !is_file($path)) {
            throw new RuntimeException("Fichier de clé du compte de service introuvable : {$path}");
        }
        if (!$subject) {
            throw new RuntimeException('GOOGLE_MEET_SUBJECT non renseigné (compte au nom duquel créer les salles).');
        }

        $client = new Client();
        $client->setApplicationName('OrthoMaster CMS');
        $client->setAuthConfig($path);
        $client->setScopes([self::SCOPE]);
        $client->setSubject($subject);

        return new Meet($client);
    }

    /** Message lisible d'une erreur de l'API Google (corps JSON). */
    private function googleMessage(GoogleServiceException $e): string
    {
        $errors = $e->getErrors();
        $msg    = $errors[0]['message'] ?? null;

        if (!$msg) {
            $body = json_decode($e->getMessage(), true);
            $msg  = $body['error']['message'] ?? $e->getMessage();
        }

        if (str_contains($msg, 'has not been used') || str_contains($msg, 'is disabled')) {
            $msg .= ' → activez « Google Meet REST API » dans le projet Google Cloud.';
        }

        return "[{$e->getCode()}] {$msg}";
    }

    /** Indice pour les erreurs de jeton les plus fréquentes. */
    private function authHint(string $message): string
    {
        if (str_contains($message, 'unauthorized_client')) {
            return $message . ' → la délégation au niveau du domaine n\'est pas (encore) autorisée pour ce compte de service et ce scope dans admin.google.com.';
        }
        if (str_contains($message, 'invalid_grant')) {
            return $message . ' → vérifiez que GOOGLE_MEET_SUBJECT est un vrai utilisateur Workspace (pas un alias ni un groupe).';
        }

        return $message;
    }
}