<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Digitalización Chiapas 2026</title>
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#034d6d">
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/pwa-192.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-white flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-200">
        <div class="bg-linear-to-r from-slate-900 to-sky-900 p-6 text-center text-white">
            <div class="w-14 h-14 rounded-2xl bg-white p-2 flex items-center justify-center mx-auto mb-3 shadow">
                <img src="{{ asset('img/favicon.png') }}" alt="Captura IREC" class="w-full h-full object-contain">
            </div>
            <h1 class="text-2xl font-bold tracking-tight">Digitalización Chiapas 2026</h1>
        </div>

        <form method="POST" action="{{ route('login.submit') }}" class="p-6 space-y-4">
            @csrf
            @if($errors->any())
                <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Usuario o Correo Electrónico</label>
                <div class="relative">
                    <i class="fa-solid fa-user absolute left-3.5 top-3 text-slate-400"></i>
                    <input type="text" name="login" value="{{ old('login') }}" required autofocus
                           class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:border-sky-600 focus:ring-2 focus:ring-sky-600/20 text-sm"
                           placeholder="usuario@gmail.com">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Contraseña</label>
                <div class="relative">
                    <i class="fa-solid fa-lock absolute left-3.5 top-3 text-slate-400"></i>
                    <input type="password" name="password" required
                           class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:border-sky-600 focus:ring-2 focus:ring-sky-600/20 text-sm"
                           placeholder="••••••••">
                </div>
            </div>

            <button type="submit"
                    class="w-full py-2.5 px-4 bg-sky-700 hover:bg-sky-800 text-white font-bold rounded-lg shadow transition-colors flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i> Ingresar al Sistema
            </button>
        </form>
    </div>
</body>
</html>
