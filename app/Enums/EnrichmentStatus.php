<?php

namespace App\Enums;

enum EnrichmentStatus: string
{
    case Pending = 'pending';
    case Skipped = 'skipped';
    case Done = 'done';
    case Failed = 'failed';
}
