<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido — Solo Computadora</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center p-5 text-slate-100">
    <div class="max-w-md w-full bg-slate-800 border border-slate-700 rounded-2xl p-6 text-center shadow-2xl space-y-4">
        <div class="w-16 h-16 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center mx-auto text-3xl">
            <i class="fa-solid fa-desktop"></i>
        </div>
        <h1 class="text-xl font-bold text-white">Estación de Captura no disponible en celulares</h1>
        <p class="text-sm text-slate-300">
            Por seguridad operativa y precisión en la captura de sellos, esta vista únicamente puede utilizarse desde una <strong>computadora de escritorio o laptop</strong>.
        </p>
        <div class="pt-2 flex flex-col gap-2">
            @if(auth()->user()?->hasAnyRole(['superadmin', 'administrador']))
                <a href="{{ route('admin.dashboard.avance') }}" class="w-full py-2.5 px-4 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm">
                    <i class="fa-solid fa-gauge-high mr-1"></i> Ir al Dashboard
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i> Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
</body>
</html>
