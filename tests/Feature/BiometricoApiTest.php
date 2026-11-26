<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Huella;
use App\Models\Turno;
use App\Models\User;
use App\Services\AsistenciaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BiometricoApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'token-biometrico-pruebas-seguro-2026';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.biometrico.token', $this->token);
        config()->set('services.biometrico.antirebote_minutos', 5);
        config()->set('services.biometrico.tolerancia_default', 10);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_rechaza_peticiones_sin_token_valido(): void
    {
        $this->getJson('/api/biometrico/ping')->assertStatus(401);

        $this->withHeaders(['Authorization' => 'Bearer token-incorrecto'])
            ->getJson('/api/biometrico/ping')
            ->assertStatus(401);

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/biometrico/ping')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_auth_admin_solo_permite_administradores(): void
    {
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Captura', 'guard_name' => 'web']);

        $admin = User::create([
            'name'     => 'Admin Prueba',
            'user'     => 'admin_bio',
            'email'    => 'admin_bio@example.test',
            'password' => Hash::make('secret123'),
            'estatus'  => true,
        ]);
        $admin->assignRole('administrador');

        $capturista = User::create([
            'name'     => 'Capturista Prueba',
            'user'     => 'cap_bio',
            'email'    => 'cap_bio@example.test',
            'password' => Hash::make('secret123'),
            'estatus'  => true,
        ]);
        $capturista->assignRole('Captura');

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/biometrico/auth-admin', ['user' => 'admin_bio', 'password' => 'secret123'])
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('admin.id', $admin->id);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/biometrico/auth-admin', ['user' => 'cap_bio', 'password' => 'secret123'])
            ->assertStatus(403);
    }

    public function test_usuario_puede_enrolar_multiples_huellas_y_desactivar_una(): void
    {
        $turno = Turno::create([
            'nombre'             => 'Matutino',
            'hora_entrada'       => '08:00:00',
            'hora_salida'        => '16:00:00',
            'tolerancia_minutos' => 10,
            'estatus'            => true,
        ]);

        $usuario = User::create([
            'name'     => 'María González',
            'user'     => 'mgonzalez',
            'email'    => 'maria@example.test',
            'password' => Hash::make('123456'),
            'turno_id' => $turno->id,
            'turno'    => $turno->nombre,
            'estatus'  => true,
        ]);

        // Dedo 1: omitir `dedo` -> debe usar default `pulgar_derecho` y guardar EXACTAMENTE 3 huellas (muestras 1, 2, 3)
        $res1 = $this->withHeaders($this->authHeaders())->postJson('/api/biometrico/huellas', [
            'user_id'            => $usuario->id,
            'muestras'           => [
                ['template' => base64_encode('tpl-maria-pulgar-m1'), 'calidad' => 91],
                ['template' => base64_encode('tpl-maria-pulgar-m2'), 'calidad' => 93],
                ['template' => base64_encode('tpl-maria-pulgar-m3'), 'calidad' => 90],
            ],
            'formato'            => 'mock_v1',
            'dispositivo_modelo' => 'Mock v1',
        ]);
        $res1->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total_creadas', 3)
            ->assertJsonPath('huella.dedo', 'pulgar_derecho')
            ->assertJsonPath('huella.numero_muestra', 1);
        $huella1Id = $res1->json('huella.id');
        $this->assertEquals(3, Huella::where('user_id', $usuario->id)->where('dedo', 'pulgar_derecho')->where('activo', true)->count());

        // Dedo 2: índice izquierdo del mismo usuario -> crea otras 3 huellas (total 6 activas en 2 dedos)
        $res2 = $this->withHeaders($this->authHeaders())->postJson('/api/biometrico/huellas', [
            'user_id'            => $usuario->id,
            'dedo'               => 'indice_izquierdo',
            'muestra_1'          => base64_encode('tpl-maria-ind-izq-m1'),
            'muestra_2'          => base64_encode('tpl-maria-ind-izq-m2'),
            'muestra_3'          => base64_encode('tpl-maria-ind-izq-m3'),
            'formato'            => 'mock_v1',
            'calidad'            => 88,
            'dispositivo_modelo' => 'Mock v1',
        ]);
        $res2->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total_creadas', 3);

        // Re-enrolar el mismo dedo (pulgar_derecho) desactiva las 3 anteriores y deja exactamente 3 nuevas activas
        $resReenroll = $this->withHeaders($this->authHeaders())->postJson('/api/biometrico/huellas', [
            'user_id'   => $usuario->id,
            'dedo'      => 'pulgar_derecho',
            'muestra_1' => base64_encode('tpl-maria-pulgar-v2-m1'),
            'muestra_2' => base64_encode('tpl-maria-pulgar-v2-m2'),
            'muestra_3' => base64_encode('tpl-maria-pulgar-v2-m3'),
            'formato'   => 'mock_v1',
        ]);
        $resReenroll->assertStatus(201)->assertJsonPath('total_creadas', 3);
        $huella1Id = $resReenroll->json('huella.id');
        $this->assertEquals(3, Huella::where('user_id', $usuario->id)->where('dedo', 'pulgar_derecho')->where('activo', true)->count());

        // Template duplicado en OTRO dedo debe rechazarse con 422
        $this->withHeaders($this->authHeaders())->postJson('/api/biometrico/huellas', [
            'user_id'   => $usuario->id,
            'dedo'      => 'medio_derecho',
            'muestra_1' => base64_encode('tpl-maria-pulgar-v2-m1'),
            'muestra_2' => base64_encode('tpl-medio-m2'),
            'muestra_3' => base64_encode('tpl-medio-m3'),
            'formato'   => 'mock_v1',
        ])->assertStatus(422);

        // Listar huellas activas devuelve 6 (3 de pulgar_derecho + 3 de indice_izquierdo)
        $list = $this->withHeaders($this->authHeaders())->getJson('/api/biometrico/huellas');
        $list->assertStatus(200)->assertJsonPath('total', 6);
        $versionHash = $list->json('version_hash');

        // Con mismo version_hash responde sin_cambios=true
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/biometrico/huellas?version_hash=' . $versionHash)
            ->assertStatus(200)
            ->assertJsonPath('sin_cambios', true);

        // Desactivar dedo pulgar_derecho (todo_el_dedo=1 por default) revoca sus 3 huellas y deja solo las 3 de indice_izquierdo
        $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/biometrico/huellas/' . $huella1Id)
            ->assertStatus(200)
            ->assertJsonPath('desactivadas', 3);

        $this->assertEquals(3, Huella::where('user_id', $usuario->id)->where('activo', true)->count());
    }

    public function test_flujo_completo_entrada_retardo_antirebote_salida_y_turno_nocturno(): void
    {
        $tz = config('app.timezone', 'America/Mexico_City');

        $matutino = Turno::create([
            'nombre'             => 'Matutino',
            'hora_entrada'       => '08:00:00',
            'hora_salida'        => '16:00:00',
            'tolerancia_minutos' => 10,
            'cruza_medianoche'   => false,
            'estatus'            => true,
        ]);

        $usuario = User::create([
            'name'     => 'Juan Pérez',
            'user'     => 'jperez',
            'email'    => 'juan@example.test',
            'password' => Hash::make('123456'),
            'turno_id' => $matutino->id,
            'turno'    => $matutino->nombre,
            'estatus'  => true,
        ]);

        $huella = Huella::create([
            'user_id'       => $usuario->id,
            'dedo'          => 'indice_derecho',
            'template'      => base64_encode('tpl-juan'),
            'muestra_1'     => base64_encode('m1'),
            'muestra_2'     => base64_encode('m2'),
            'muestra_3'     => base64_encode('m3'),
            'template_hash' => hash('sha256', base64_encode('tpl-juan')),
            'formato'       => 'mock_v1',
            'activo'        => true,
        ]);

        $service = app(AsistenciaService::class);

        // 1) Entrada con retardo (08:15 con tolerancia de 10 min => retardo de 15 min)
        $rEntrada = $service->registrarChequeo(
            $usuario,
            $huella,
            ['score' => 95, 'dispositivo' => 'Estacion-01'],
            Carbon::parse('2026-11-26 08:15:00', $tz)
        );
        $this->assertTrue($rEntrada['ok']);
        $this->assertEquals('entrada', $rEntrada['accion']);
        $this->assertEquals('retardo', $rEntrada['estatus']);
        $this->assertEquals(15, $rEntrada['minutos_retardo']);

        // 2) Anti-rebote a los 2 minutos (no debe cerrar salida)
        $rRebote = $service->registrarChequeo(
            $usuario,
            $huella,
            ['score' => 94, 'dispositivo' => 'Estacion-01'],
            Carbon::parse('2026-11-26 08:17:00', $tz)
        );
        $this->assertEquals('antirebote', $rRebote['accion']);
        $this->assertNull(Asistencia::first()->hora_salida);

        // 3) Salida a las 16:05 (470 minutos laborados)
        $rSalida = $service->registrarChequeo(
            $usuario,
            $huella,
            ['score' => 96, 'dispositivo' => 'Estacion-01'],
            Carbon::parse('2026-11-26 16:05:00', $tz)
        );
        $this->assertEquals('salida', $rSalida['accion']);
        $this->assertEquals(470, $rSalida['minutos_trabajados']);

        // 4) Tercer chequeo -> jornada_completa
        $rTercero = $service->registrarChequeo(
            $usuario,
            $huella,
            ['score' => 96, 'dispositivo' => 'Estacion-01'],
            Carbon::parse('2026-11-26 16:30:00', $tz)
        );
        $this->assertEquals('jornada_completa', $rTercero['accion']);
        $this->assertEquals(1, Asistencia::count());

        // 5) Turno nocturno que cruza medianoche (22:00 - 06:00)
        $nocturno = Turno::create([
            'nombre'             => 'Nocturno',
            'hora_entrada'       => '22:00:00',
            'hora_salida'        => '06:00:00',
            'tolerancia_minutos' => 10,
            'cruza_medianoche'   => true,
            'estatus'            => true,
        ]);

        $usrNoche = User::create([
            'name'     => 'Sofía Luna',
            'user'     => 'sluna',
            'email'    => 'sofia@example.test',
            'password' => Hash::make('123456'),
            'turno_id' => $nocturno->id,
            'turno'    => $nocturno->nombre,
            'estatus'  => true,
        ]);

        // Entra a las 22:05 del 26 de nov (dentro de tolerancia => a_tiempo)
        $nEntrada = $service->registrarChequeo(
            $usrNoche,
            null,
            ['dispositivo' => 'Estacion-Noche'],
            Carbon::parse('2026-11-26 22:05:00', $tz)
        );
        $this->assertEquals('entrada', $nEntrada['accion']);
        $this->assertEquals('a_tiempo', $nEntrada['estatus']);
        $this->assertEquals('2026-11-26', $nEntrada['asistencia']->fecha_laboral->format('Y-m-d'));

        // Sale a las 06:05 del 27 de nov -> debe emparejarse con la fecha_laboral 2026-11-26
        $nSalida = $service->registrarChequeo(
            $usrNoche,
            null,
            ['dispositivo' => 'Estacion-Noche'],
            Carbon::parse('2026-11-27 06:05:00', $tz)
        );
        $this->assertEquals('salida', $nSalida['accion']);
        $this->assertEquals('2026-11-26', $nSalida['asistencia']->fecha_laboral->format('Y-m-d'));
        $this->assertEquals(480, $nSalida['minutos_trabajados']);
    }

    public function test_endpoint_checar_y_hoy_por_http(): void
    {
        $turno = Turno::create([
            'nombre'             => 'Vespertino',
            'hora_entrada'       => '14:00:00',
            'hora_salida'        => '22:00:00',
            'tolerancia_minutos' => 10,
            'estatus'            => true,
        ]);

        $usuario = User::create([
            'name'     => 'Luis Ramírez',
            'user'     => 'lramirez',
            'email'    => 'luis@example.test',
            'password' => Hash::make('123456'),
            'turno_id' => $turno->id,
            'turno'    => $turno->nombre,
            'estatus'  => true,
        ]);

        $huella = Huella::create([
            'user_id'       => $usuario->id,
            'dedo'          => 'pulgar_derecho',
            'template'      => base64_encode('tpl-luis'),
            'muestra_1'     => base64_encode('m1'),
            'muestra_2'     => base64_encode('m2'),
            'muestra_3'     => base64_encode('m3'),
            'template_hash' => hash('sha256', base64_encode('tpl-luis')),
            'formato'       => 'mock_v1',
            'activo'        => true,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/biometrico/asistencia/checar', [
                'user_id'     => $usuario->id,
                'huella_id'   => $huella->id,
                'score'       => 97,
                'dispositivo' => 'Estacion-Lobby',
            ])
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('accion', 'entrada')
            ->assertJsonPath('asistencia.dedo', 'Pulgar Derecho');

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/biometrico/asistencia/hoy')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'registros');

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/biometrico/asistencia')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('kpis.total', 1)
            ->assertJsonCount(1, 'registros');
    }
}
