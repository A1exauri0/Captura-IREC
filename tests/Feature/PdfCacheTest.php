<?php

namespace Tests\Feature;

use App\Models\ArchivoSesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PdfCacheTest extends TestCase
{
    use RefreshDatabase;

    private string $tempCacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempCacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_cache_test_' . uniqid();
        @mkdir($this->tempCacheDir, 0775, true);

        config([
            'services.stellum_bridge.url'              => 'https://stellum.test/api/captura-bridge',
            'services.stellum_bridge.token'            => 'secret-token-test',
            'services.stellum_bridge.pdf_cache_dir'    => $this->tempCacheDir,
            'services.stellum_bridge.pdf_cache_max_mb' => 256,
            'services.stellum_bridge.pdf_cache_ttl_h'  => 12,
            'services.stellum_bridge.pdf_cache_xaccel' => false,
            'services.stellum_bridge.pdf_prefetch_buf' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempCacheDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tempCacheDir);

        parent::tearDown();
    }

    private function crearUsuario(): User
    {
        return User::create([
            'name'     => 'Capturista Cache',
            'user'     => 'cap_cache_' . uniqid(),
            'email'    => 'cap_cache_' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'estatus'  => true,
        ]);
    }

    private function pdfValidoBytes(): string
    {
        return "%PDF-1.4\n" . str_repeat('0123456789ABCDEF', 20) . "\n%%EOF";
    }

    public function test_ver_pdf_descarga_sin_token_en_url_cachea_y_reutiliza_en_segunda_peticion(): void
    {
        $user = $this->crearUsuario();
        $pdfBytes = $this->pdfValidoBytes();

        Http::fake([
            'https://stellum.test/api/captura-bridge/indice-pdfs/42/stream' => Http::response($pdfBytes, 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        // 1ra petición: baja de Stellum y guarda en caché
        $res1 = $this->actingAs($user)->get('/captura/ver-pdf?ruta=stellum:42');
        $res1->assertOk();
        $res1->assertHeader('Content-Type', 'application/pdf');
        $this->assertFileExists($this->tempCacheDir . DIRECTORY_SEPARATOR . '42.pdf');

        // 2da petición: sirve desde caché sin volver a llamar a Stellum
        $res2 = $this->actingAs($user)->get('/captura/ver-pdf?ruta=stellum:42');
        $res2->assertOk();
        $res2->assertHeader('Content-Type', 'application/pdf');

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://stellum.test/api/captura-bridge/indice-pdfs/42/stream'
                && !str_contains($request->url(), 'bridge_token')
                && $request->hasHeader('Authorization', 'Bearer secret-token-test');
        });
    }

    public function test_ver_pdf_usa_x_accel_redirect_cuando_esta_habilitado(): void
    {
        config(['services.stellum_bridge.pdf_cache_xaccel' => true]);

        $user = $this->crearUsuario();
        file_put_contents($this->tempCacheDir . DIRECTORY_SEPARATOR . '77.pdf', $this->pdfValidoBytes());

        Http::fake();

        $res = $this->actingAs($user)->get('/captura/ver-pdf?ruta=stellum:77');
        $res->assertOk();
        $res->assertHeader('X-Accel-Redirect', '/_internal_pdf_cache/77.pdf');
        $res->assertHeader('Content-Type', 'application/pdf');

        Http::assertNothingSent();
    }

    public function test_ver_pdf_devuelve_504_limpio_en_timeout_de_stellum(): void
    {
        $user = $this->crearUsuario();

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $res = $this->actingAs($user)->get('/captura/ver-pdf?ruta=stellum:99');
        $res->assertStatus(504);
        $this->assertFileDoesNotExist($this->tempCacheDir . DIRECTORY_SEPARATOR . '99.pdf');
    }

    public function test_comando_prefetch_descarga_pendientes_y_omite_completados(): void
    {
        $user = $this->crearUsuario();

        ArchivoSesion::create([
            'user_id'        => $user->id,
            'indice_pdf_id'  => 10,
            'tipo_archivo'   => 'sellos',
            'archivo_nombre' => 'ya_completado.pdf',
            'ruta_origen'    => '/tmp/ya_completado.pdf',
            'ruta_hash'      => hash('sha256', '/tmp/ya_completado.pdf'),
            'estado'         => 'completado',
            'tomado_en'      => now()->subMinutes(10),
            'completado_en'  => now(),
        ]);

        $pdfBytes = $this->pdfValidoBytes();

        Http::fake([
            'https://stellum.test/api/captura-bridge/indice-pdfs/11/stream' => Http::response($pdfBytes, 200),
            'https://stellum.test/api/captura-bridge/indice-pdfs/12/stream' => Http::response($pdfBytes, 200),
            'https://stellum.test/api/captura-bridge/indice-pdfs*' => Http::response([
                'ok'    => true,
                'items' => [
                    ['id' => 10, 'nombre' => 'ya_completado.pdf'],
                    ['id' => 11, 'nombre' => 'pendiente_1.pdf'],
                    ['id' => 12, 'nombre' => 'pendiente_2.pdf'],
                ],
                'next_after_id' => 12,
            ], 200),
        ]);

        $this->artisan('pdfs:prefetch', ['--buffer' => 5])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($this->tempCacheDir . DIRECTORY_SEPARATOR . '10.pdf');
        $this->assertFileExists($this->tempCacheDir . DIRECTORY_SEPARATOR . '11.pdf');
        $this->assertFileExists($this->tempCacheDir . DIRECTORY_SEPARATOR . '12.pdf');
    }
}
