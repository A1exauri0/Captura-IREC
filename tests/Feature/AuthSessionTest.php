<?php

namespace Tests\Feature;

use App\Events\LoteActualizadoEvent;
use App\Models\ArchivoSesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(array $overrides = []): User
    {
        $suffix = uniqid();

        return User::create(array_merge([
            'name'     => 'Usuario Prueba',
            'user'     => 'usuario_' . $suffix,
            'email'    => 'usuario_' . $suffix . '@example.com',
            'password' => Hash::make('secret123'),
            'estatus'  => true,
        ], $overrides));
    }

    public function test_session_config_expires_on_close_and_by_inactivity(): void
    {
        $this->assertTrue((bool) config('session.expire_on_close'));
        $this->assertSame(120, (int) config('session.lifetime'));
    }

    public function test_login_does_not_set_remember_cookie_and_clears_existing_remember_token(): void
    {
        $user = $this->crearUsuario();
        $user->forceFill(['remember_token' => 'token-heredado-123'])->save();

        $response = $this->post(route('login.submit'), [
            'login'    => $user->user,
            'password' => 'secret123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNull($user->remember_token);

        $recallerName = Auth::guard('web')->getRecallerName();
        $recallerCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $recallerName);

        // Si existe la cookie en respuesta, debe ser únicamente para borrarla (valor vacío o expirada)
        if ($recallerCookie !== null) {
            $this->assertTrue(
                empty($recallerCookie->getValue()) || $recallerCookie->getExpiresTime() < time(),
                'No debe emitirse una cookie remember activa.'
            );
        }
    }

    public function test_login_blocks_inactive_users(): void
    {
        $user = $this->crearUsuario(['estatus' => false]);

        $response = $this->from(route('login'))->post(route('login.submit'), [
            'login'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_logout_releases_only_current_user_active_capture_sessions_and_broadcasts(): void
    {
        Event::fake([LoteActualizadoEvent::class]);

        $user = $this->crearUsuario();
        $user->forceFill(['remember_token' => 'token-viejo'])->save();

        $otroUsuario = $this->crearUsuario();

        $activaUsuario = ArchivoSesion::create([
            'user_id'        => $user->id,
            'tipo_archivo'   => 'sellos',
            'pc'             => 'PC-01',
            'archivo_nombre' => 'activo_usuario.pdf',
            'ruta_origen'    => '/tmp/activo_usuario.pdf',
            'ruta_hash'      => hash('sha256', '/tmp/activo_usuario.pdf'),
            'estado'         => 'en_captura',
            'tomado_en'      => now(),
            'expira_en'      => now()->addMinutes(15),
        ]);

        $completadaUsuario = ArchivoSesion::create([
            'user_id'        => $user->id,
            'tipo_archivo'   => 'sellos',
            'pc'             => 'PC-01',
            'archivo_nombre' => 'completado_usuario.pdf',
            'ruta_origen'    => '/tmp/completado_usuario.pdf',
            'ruta_hash'      => hash('sha256', '/tmp/completado_usuario.pdf'),
            'estado'         => 'completado',
            'tomado_en'      => now()->subMinutes(5),
            'completado_en'  => now(),
        ]);

        $activaOtro = ArchivoSesion::create([
            'user_id'        => $otroUsuario->id,
            'tipo_archivo'   => 'sellos',
            'pc'             => 'PC-02',
            'archivo_nombre' => 'activo_otro.pdf',
            'ruta_origen'    => '/tmp/activo_otro.pdf',
            'ruta_hash'      => hash('sha256', '/tmp/activo_otro.pdf'),
            'estado'         => 'en_captura',
            'tomado_en'      => now(),
            'expira_en'      => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['pc_seleccionada' => 'PC-01'])
            ->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('pc_seleccionada'));

        $user->refresh();
        $this->assertNull($user->remember_token);

        $this->assertDatabaseMissing('archivos_sesiones', ['id' => $activaUsuario->id]);
        $this->assertDatabaseHas('archivos_sesiones', ['id' => $completadaUsuario->id, 'estado' => 'completado']);
        $this->assertDatabaseHas('archivos_sesiones', ['id' => $activaOtro->id, 'estado' => 'en_captura']);

        Event::assertDispatched(LoteActualizadoEvent::class, function (LoteActualizadoEvent $event) use ($activaUsuario) {
            return $event->accion === 'lote_eliminado'
                && ($event->lote['id'] ?? null) === $activaUsuario->id
                && ($event->lote['lote'] ?? null) === 'activo_usuario.pdf';
        });
    }

    public function test_logout_when_guest_redirects_cleanly_to_login(): void
    {
        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
