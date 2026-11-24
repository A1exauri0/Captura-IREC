@extends('layouts.app')
@section('titulo', 'Catálogo de Números de PC — Captura-IREC')

@section('contenido')
<div class="space-y-5 pb-8">
    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ $errors->first() }}
        </div>
    @endif

    <!-- Formulario Nuevo Número de PC -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <h2 class="text-base font-bold text-slate-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-computer text-sky-700"></i> Registrar Nuevo Número de PC
        </h2>
        <form method="POST" action="{{ route('admin.pcs.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Número de PC *</label>
                <input type="text" name="numero_pc" required placeholder="Ej. PC-21"
                       class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:border-sky-600 focus:outline-none uppercase">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Número de Serie (opcional)</label>
                <input type="text" name="no_serie" placeholder="Ej. 5CD1234ABC"
                       class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:border-sky-600 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Ubicación</label>
                <input type="text" name="ubicacion" placeholder="Ej. Mesa 3 / Sala A"
                       class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:border-sky-600 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Descripción</label>
                <input type="text" name="descripcion" placeholder="Notas opcionales"
                       class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:border-sky-600 focus:outline-none">
            </div>
            <div>
                <button type="submit" class="w-full py-2 px-4 rounded-lg bg-sky-700 hover:bg-sky-800 text-white text-sm font-bold">
                    <i class="fa-solid fa-plus mr-1"></i> Agregar PC
                </button>
            </div>
        </form>
    </div>

    <!-- Tabla de PCs -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-800">PCs Disponibles para Selección en Inicio de Sesión ({{ $pcs->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-2.5">Número de PC</th>
                        <th class="px-4 py-2.5">No. Serie</th>
                        <th class="px-4 py-2.5">Ubicación</th>
                        <th class="px-4 py-2.5">Descripción</th>
                        <th class="px-4 py-2.5 text-center">Estado</th>
                        <th class="px-4 py-2.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($pcs as $pc)
                        <tr class="hover:bg-slate-50">
                            <form method="POST" action="{{ route('admin.pcs.update', $pc->id) }}">
                                @csrf
                                @method('PUT')
                                <td class="px-4 py-2">
                                    <input type="text" name="numero_pc" value="{{ $pc->numero_pc }}" required class="w-28 px-2 py-1 text-xs font-mono font-bold rounded border border-slate-300">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" name="no_serie" value="{{ $pc->no_serie }}" class="w-36 px-2 py-1 text-xs font-mono rounded border border-slate-300">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" name="ubicacion" value="{{ $pc->ubicacion }}" class="w-36 px-2 py-1 text-xs rounded border border-slate-300">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" name="descripcion" value="{{ $pc->descripcion }}" class="w-44 px-2 py-1 text-xs rounded border border-slate-300">
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <select name="activo" class="px-2 py-1 text-xs rounded border border-slate-300">
                                        <option value="1" {{ $pc->activo ? 'selected' : '' }}>Activa</option>
                                        <option value="0" {{ !$pc->activo ? 'selected' : '' }}>Inactiva</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap space-x-1">
                                    <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Guardar</button>
                            </form>
                            <form method="POST" action="{{ route('admin.pcs.destroy', $pc->id) }}" class="inline" onsubmit="return confirm('¿Eliminar {{ $pc->numero_pc }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2.5 py-1 rounded bg-rose-600 hover:bg-rose-700 text-white font-semibold">Eliminar</button>
                            </form>
                                </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
