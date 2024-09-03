<?php

declare(strict_types=1);

namespace Phenogram\Framework\Command;

use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 *
 * @NamedArgumentConstructor
 *
 * @Target({"METHOD"})
 *
 * @Attributes({
 *
 *     @Attribute("route", required=true, type="string"),
 *     @Attribute("name", type="string"),
 *     @Attribute("verbs", required=true, type="mixed"),
 *     @Attribute("defaults", type="array"),
 *     @Attribute("group", type="string"),
 *     @Attribute("middleware", type="array"),
 *     @Attribute("priority", type="int")
 * })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final readonly class AsTelegramBotCommand
{
    public function __construct(
        public string $command,
        public string $description,
    ) {
    }
}
