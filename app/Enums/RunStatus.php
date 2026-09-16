<?php

namespace App\Enums;

enum RunStatus: string
{
    case Queued = 'queued';
    case Scraping = 'scraping';
    case Enriching = 'enriching';
    case Done = 'done';
    case Failed = 'failed';
}
