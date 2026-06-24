<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // En las pruebas, redirige los archivos temporales de laravel-excel a un
        // directorio del sistema escribible. El dir por defecto
        // (storage/framework/cache/laravel-excel) es propiedad del usuario web
        // (apache) y PHPUnit corre como otro usuario → "Permission denied".
        $tmp = sys_get_temp_dir().'/laravel-excel-tests';
        if (! is_dir($tmp)) {
            @mkdir($tmp, 0777, true);
        }
        config(['excel.temporary_files.local_path' => $tmp]);
    }
}
