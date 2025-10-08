<?php

namespace App;

enum Status: int
{
    case ACTIVE = 1;
    case DISABLED= 0;
    case PENDING = 2;
    case DELETED = 3;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::DISABLED => 'Désactive',
            self::PENDING => 'En attente',
            self::DELETED => 'Supprimé',
        };
    }
}
