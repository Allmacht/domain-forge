<?php

namespace Yntech\DomainForge\Support;

enum IdStrategy: string
{
    case Uuid = 'uuid';
    case UuidV7 = 'uuidv7';
    case Ulid = 'ulid';
    case Int = 'int';

    public function phpType(): string
    {
        return match ($this) {
            self::Int => 'int',
            default => 'string',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Uuid => 'UUID v4',
            self::UuidV7 => 'UUID v7',
            self::Ulid => 'ULID',
            self::Int => 'Auto-increment (int)',
        };
    }
}