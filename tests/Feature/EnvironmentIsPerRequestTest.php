<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use Tests\TestCase;

/**
 * The web server runs PHP threaded (Apache mpm_winnt, a ZTS build). `putenv()` writes the PROCESS environment that every thread
 * shares, so under load a request could lose DB_USERNAME or APP_ENV while another thread was loading `.env` — «Access denied for user
 * 'forge'», a 500 on about one request in thirty (17 of 600 parallel requests measured, 0 of 1200 after the fix) that the app showed as
 * «Something went wrong» on its first screen. bootstrap/app.php turns putenv off; this keeps it off.
 */
class EnvironmentIsPerRequestTest extends TestCase
{
    public function test_the_environment_is_never_written_to_the_shared_process(): void
    {
        $key = 'ZZ_PROBE_' . uniqid();

        Env::getRepository()->set($key, 'x');

        $this->assertFalse(getenv($key), 'putenv is shared by every thread of the web server and must stay disabled');
        $this->assertSame('x', Env::get($key), 'the value is still readable through env()');

        Env::getRepository()->clear($key);
    }
}
