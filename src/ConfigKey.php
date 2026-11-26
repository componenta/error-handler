<?php

declare(strict_types=1);

namespace Componenta\Error;

final class ConfigKey extends \Componenta\Config\ConfigKey
{
    public final const string HTTP_FALLBACK_GENERATOR = 'error.http.fallback_generator';
    public final const string HTTP_RENDERER = 'error.http.renderer';
    public final const string HTTP_GENERATORS = 'error.http.generators';

    public final const string CLI_FALLBACK_HANDLER = 'error.cli.fallback_handler';
    public final const string CLI_RENDERER = 'error.cli.renderer';

    public final const string ERROR_LISTENERS = 'error.listeners';
    public final const string NON_REPORTABLE_ERRORS = 'error.non_reportable';
    public final const string ERROR_LEVEL = 'error.http.error_level';
}
