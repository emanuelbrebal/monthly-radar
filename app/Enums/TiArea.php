<?php

namespace App\Enums;

enum TiArea: string
{
    case Development = 'development';
    case Data = 'data';
    case Infrastructure = 'infrastructure';
    case Security = 'security';
    case Support = 'support';
    case ItGovernance = 'it_governance';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Development => 'Desenvolvimento',
            self::Data => 'Dados',
            self::Infrastructure => 'Infraestrutura',
            self::Security => 'Segurança',
            self::Support => 'Suporte',
            self::ItGovernance => 'Governança de TI',
            self::All => 'Todas',
        };
    }
}
