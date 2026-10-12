<?php

namespace App\Services\AiSearch;

use RuntimeException;

/**
 * Session introuvable, expirée ou appartenant à un autre utilisateur :
 * l'app doit repartir d'une conversation vierge.
 */
class SessionExpiredException extends RuntimeException
{
}
