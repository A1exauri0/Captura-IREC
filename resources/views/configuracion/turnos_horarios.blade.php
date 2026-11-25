@extends('layouts.crud')

@section('titulo', 'Gestión de Turnos Adaptativos y Asignación de Capturistas')
@section('crud_modulo', 'Recursos Humanos y Horarios Operativos')
@section('crud_titulo', 'Turnos Adaptativos y Relación con Capturistas')
@section('crud_subtitulo', 'Cree nuevos turnos, configure su hora de entrada y salida, y asigne capturistas a cada jornada.')

@section('crud_acciones')
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalCrearTurno" style="background-color:#034d6d;border-color:#034d6d;">
        <i class="fa-solid fa-plus mr-1"></i> Nuevo Turno Adaptativo
    </button>
    <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#modalAsignarMasivo">
        <i class="fa-solid fa-user-check mr-1"></i> Asignar Capturistas a Turno
    </button>
@endsection

@section('contenido')
<div class="row">
    <!-- Listado de Turnos Adaptativos -->
    <div class="col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-clock mr-2 text-sky-700"></i>Catálogo de Turnos Configurados</span>
                <span class="badge badge-info">{{ $turnos->count() }} turnos</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-xs uppercase">
                            <tr>
                                <th>Turno</th>
                                <th class="text-center">Entrada</th>
                                <th class="text-center">Salida</th>
                                <th class="text-center">Capturistas</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($turnos as $t)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center" style="gap:8px;">
                                            <span class="inline-block w-3 h-3 rounded-full" style="background-color: {{ $t->color_hex }}"></span>
                                            <div>
                                                <div class="font-weight-bold text-dark">{{ $t->nombre }}</div>
                                                @if($t->cruza_medianoche)
                                                    <span class="badge badge-warning" style="font-size:0.65rem;">Cruza medianoche</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center font-mono small font-weight-bold">{{ substr($t->hora_entrada, 0, 5) }}</td>
                                    <td class="text-center font-mono small font-weight-bold">{{ substr($t->hora_salida, 0, 5) }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-pill badge-primary" style="background-color:#034d6d;">{{ $t->usuarios_count }}</span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-light border btn-editar-turno"
                                                data-id="{{ $t->id }}"
                                                data-nombre="{{ $t->nombre }}"
                                                data-entrada="{{ substr($t->hora_entrada, 0, 5) }}"
                                                data-salida="{{ substr($t->hora_salida, 0, 5) }}"
                                                data-color="{{ $t->color_hex }}"
                                                data-descripcion="{{ $t->descripcion }}">
                                            <i class="fa-solid fa-pen text-sky-700"></i>
                                        </button>
                                        <form action="{{ route('admin.turnos.destroy', $t->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este turno?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light border text-danger">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Relación de Usuarios Capturistas con su Turno -->
    <div class="col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:10px;">
                <span><i class="fa-solid fa-users-gear mr-2 text-sky-700"></i>Capturistas y Turno Asignado</span>
                <input type="text" id="txtFiltroUsuarioTurno" class="form-control form-control-sm" style="max-width: 240px;" placeholder="Buscar capturista...">
            </div>
            <div class="card-body p-0" style="max-height: 540px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0" id="tablaUsuariosTurnos">
                    <thead class="bg-light text-xs uppercase sticky-top">
                        <tr>
                            <th>#</th>
                            <th>Nombre del Capturista</th>
                            <th>Usuario</th>
                            <th>Turno Actual / Horario</th>
                            <th class="text-center">Cambiar Turno</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $idx => $u)
                            <tr>
                                <td class="text-muted small">{{ $idx + 1 }}</td>
                                <td class="font-weight-bold text-dark small">{{ $u->name }}</td>
                                <td class="font-mono text-xs text-muted">{{ $u->user }}</td>
                                <td>
                                    @if($u->turnoRelacion)
                                        <span class="badge text-white" style="background-color: {{ $u->turnoRelacion->color_hex }}">
                                            {{ $u->turnoRelacion->nombre }} ({{ substr($u->turnoRelacion->hora_entrada, 0, 5) }} - {{ substr($u->turnoRelacion->hora_salida, 0, 5) }})
                                        </span>
                                    @else
                                        <span class="badge badge-secondary">{{ $u->turno ?: 'Sin Turno' }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <form action="{{ route('admin.turnos.asignar') }}" method="POST" class="d-inline-flex align-items-center" style="gap:4px;">
                                        @csrf
                                        <input type="hidden" name="user_ids[]" value="{{ $u->id }}">
                                        <select name="turno_id" class="form-control form-control-sm py-0" style="height:28px;font-size:0.75rem;" onchange="this.form.submit()">
                                            @foreach($turnos as $t)
                                                <option value="{{ $t->id }}" {{ ($u->turno_id == $t->id || strtolower($u->turno) === strtolower($t->nombre)) ? 'selected' : '' }}>
                                                    {{ $t->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Turno -->
<div class="modal fade" id="modalCrearTurno" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('admin.turnos.store') }}" method="POST" class="modal-content border-0 shadow-lg rounded-xl">
            @csrf
            <div class="modal-header text-white" style="background:#034d6d;">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-clock mr-2"></i>Crear Nuevo Turno Adaptativo</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="font-weight-bold small">Nombre del Turno:</label>
                    <input type="text" name="nombre" required class="form-control" placeholder="Ej. Fin de Semana, Especial Nocturno, Vespertino B...">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold small">Hora de Entrada:</label>
                        <input type="time" name="hora_entrada" required class="form-control" value="08:00">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold small">Hora de Salida:</label>
                        <input type="time" name="hora_salida" required class="form-control" value="16:00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label class="font-weight-bold small">Color Identificador:</label>
                        <input type="color" name="color_hex" class="form-control" value="#034d6d" style="height:38px;">
                    </div>
                    <div class="form-group col-md-8">
                        <label class="font-weight-bold small">Descripción:</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Descripción opcional del horario">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#034d6d;">Guardar Turno</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Turno -->
<div class="modal fade" id="modalEditarTurno" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formEditarTurno" method="POST" class="modal-content border-0 shadow-lg rounded-xl">
            @csrf
            @method('PUT')
            <div class="modal-header text-white" style="background:#034d6d;">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-pen mr-2"></i>Editar Turno Adaptativo</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="font-weight-bold small">Nombre del Turno:</label>
                    <input type="text" name="nombre" id="editTurnoNombre" required class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold small">Hora de Entrada:</label>
                        <input type="time" name="hora_entrada" id="editTurnoEntrada" required class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold small">Hora de Salida:</label>
                        <input type="time" name="hora_salida" id="editTurnoSalida" required class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label class="font-weight-bold small">Color:</label>
                        <input type="color" name="color_hex" id="editTurnoColor" class="form-control" style="height:38px;">
                    </div>
                    <div class="form-group col-md-8">
                        <label class="font-weight-bold small">Descripción:</label>
                        <input type="text" name="descripcion" id="editTurnoDescripcion" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#034d6d;">Actualizar Turno</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Asignación Masiva -->
<div class="modal fade" id="modalAsignarMasivo" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="{{ route('admin.turnos.asignar') }}" method="POST" class="modal-content border-0 shadow-lg rounded-xl">
            @csrf
            <div class="modal-header text-white" style="background:#034d6d;">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-user-check mr-2"></i>Asignar Capturistas a un Turno</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="font-weight-bold small">Seleccione el Turno Destino:</label>
                    <select name="turno_id" required class="form-control">
                        @foreach($turnos as $t)
                            <option value="{{ $t->id }}">{{ $t->nombre }} ({{ substr($t->hora_entrada, 0, 5) }} a {{ substr($t->hora_salida, 0, 5) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold small">Seleccione Capturistas (Ctrl / Click para varios):</label>
                    <select name="user_ids[]" multiple required class="form-control" style="height: 240px;">
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->user }}) — Actual: {{ $u->turnoRelacion->nombre ?? $u->turno }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#034d6d;">Asignar Seleccionados</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-editar-turno').click(function() {
        const id = $(this).data('id');
        $('#formEditarTurno').attr('action', "{{ url('/admin/turnos') }}/" + id);
        $('#editTurnoNombre').val($(this).data('nombre'));
        $('#editTurnoEntrada').val($(this).data('entrada'));
        $('#editTurnoSalida').val($(this).data('salida'));
        $('#editTurnoColor').val($(this).data('color'));
        $('#editTurnoDescripcion').val($(this).data('descripcion'));
        $('#modalEditarTurno').modal('show');
    });

    $('#txtFiltroUsuarioTurno').on('input', function() {
        const val = $(this).val().toLowerCase();
        $('#tablaUsuariosTurnos tbody tr').each(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
        });
    });
});
</script>
@endsection
