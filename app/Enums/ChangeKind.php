<?php

namespace App\Enums;

enum ChangeKind: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Failed = 'failed';
}
