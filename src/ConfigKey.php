<?php

declare(strict_types=1);

namespace Componenta\Error;

final class ConfigKey extends \Componenta\Config\ConfigKey
{
    public final const string HTTP_FALLBACK_GENERATOR = 'error.http.fallback_generator';
    public final const string HTTP_RENDERER = 'error.http.renderer';
    public final const string HTTP_LISTENERS = 'error.http.listeners';
    public final const string HTTP_GENERATORS = 'error.http.generators';

    public final const string CLI_FALLBACK_HANDLER = 'error.cli.fallback_handler';
    public final const string CLI_RENDERER = 'error.cli.renderer';
    public final const string CLI_LISTENERS = 'error.cli.listeners';

    public final const string ERROR_REPORTER = 'error.reporter';
    public final const string ERROR_ID_GENERATOR = 'error.id_generator';
    public final const string ERROR_LEVEL = 'error.http.error_level';
}
