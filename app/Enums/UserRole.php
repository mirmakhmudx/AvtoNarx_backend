<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case ContentEditor = 'content_editor';
}
