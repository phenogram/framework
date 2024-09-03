<?php

declare(strict_types=1);

namespace Phenogram\Framework;

enum BotStatus: string
{
    case starting = 'starting';
    case started = 'started';
    case stopping = 'stopping';
    case stopped = 'stopped';
}
