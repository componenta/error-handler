<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Config\Config;
use Componenta\Error\ConfigKey;
use Componenta\Error\Internal\TypedService;
use Componenta\Error\Reporter\Policy\NonReportableErrors;
use Psr\Container\ContainerInterface;

final readonly class NonReportableErrorsFactory
{
    public function __invoke(ContainerInterface $container): NonReportableErrors
    {
        $config = TypedService::get($container, Config::class, Config::class);

        return new NonReportableErrors($config->array(ConfigKey::NON_REPORTABLE_ERRORS, []));
    }
}
