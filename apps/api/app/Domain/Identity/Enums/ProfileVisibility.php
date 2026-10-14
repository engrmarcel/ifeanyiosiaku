<?php

namespace App\Domain\Identity\Enums;

enum ProfileVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    case Private = 'private';
}
