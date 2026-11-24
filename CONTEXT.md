# CONTEXT — Digitalización Chiapas 2026 (Captura-IREC)

Documento de referencia arquitectónica, visual y operativa para mantener consistencia en futuras actualizaciones del sistema **Captura-IREC** (`https://www.digitalizacionchiapas.online`).

---

## 1. Visión General y Entornos

- **Sistema principal**: **Captura-IREC** (Laravel 12, PHP 8.2+, MySQL `captura_irec`).
- **Dominio de producción**: `https://www.digitalizacionchiapas.online`
- **Repositorio Git**: `https://github.com/A1exauri0/Captura-IREC.git` (rama `main`).
- **Sistema hermano / Puente**: **Stellum 3.0** (`https://app.astronmx.cloud/`).
  - Stellum es la **Fuente Única de Verdad (Single Source of Truth)** para:
    1. La tabla `indice_archivos_pdfs` (millones de PDFs) y ejecución física de renombres aprobados.
    2. El inventario de equipos entregados (`laptops` y `computadoras` donde `entregada = 'entregada'`).
  - La comunicación segura se realiza a través de `App\Services\StellumBridgeService` usando `config('services.stellum_bridge.url')` y `config('services.stellum_bridge.token')` (respaldados en `.env` por `STELLUM_BRIDGE_URL` y `STELLUM_BRIDGE_TOKEN`).

---

## 2. Stack Tecnológico

- **Backend**: Laravel 12, Spatie Laravel Permission (`roles`, `permissions`), PhpSpreadsheet (exportación Excel), `setasign/fpdf` + `setasign/fpdi` (rotación física de páginas PDF).
- **Frontend**:
  - **Tailwind CSS v4** compilado localmente con **Vite** (`@vite(['resources/css/app.css', 'resources/js/app.js'])`). **Nunca usar `cdn.tailwindcss.com`**.
  - **Bootstrap 4.6 CSS/JS** + **jQuery 3.7** (compatibilidad con grillas, modales, dropdowns y reportes).
  - **Chart.js** + `chartjs-plugin-datalabels` (gráficas KPI).
  - **SweetAlert2** (toasts y alertas).
  - **FontAwesome 6** y **Material Design Icons**.
- **Tiempo Real (WebSockets)**: **Laravel Reverb** (`BROADCAST_CONNECTION=reverb`, puerto `8080`) + Laravel Echo + Pusher JS (`Pusher.logToConsole = false`).
  - Canales activos: `lotes-captura`, `autorizaciones-captura` (escucha `.peticion.actualizada` y `.inconsistencia.reportada`).

---

## 3. Sistema de Diseño, Estilos y Componentes Obligatorios

### Paleta Corporativa y Tipografía
Definida en `resources/css/app.css` (`@theme`) y `resources/views/layouts/app.blade.php`:
- **Color primario de marca**: `#034d6d` (`--brand-primary`, botones principales y badges activos).
- **Fondo del Sidebar**: Gradiente oscuro `#0f172a` → `#1e293b`.
- **Fondo del Workspace**: `#f1f5f9`, tarjetas blancas `#ffffff` con borde `#e2e8f0` y radio `12px`.
- **Tipografías**: `Plus Jakarta Sans` (títulos `h1–h6`) e `Inter` (cuerpo y tablas).
- **Logo / Favicon**: `public/img/favicon.png` (usado en `<link rel="icon">`, header del sidebar, login y `public/manifest.json`).

### Layouts Base (`resources/views/layouts/`)
Toda nueva vista **debe** extender uno de los 4 layouts base según su propósito:

| Layout | Uso | Secciones requeridas |
| :--- | :--- | :--- |
| `layouts.app` | Base general (shell con sidebar, navbar, Reverb/Echo, alertas flash). | `titulo`, `contenido`, `pre-script`, `pos-script` |
| `layouts.crud` | Vistas de gestión/CRUD y paneles operativos. | `titulo`, `crud_modulo`, `crud_titulo`, `crud_subtitulo`, `crud_acciones`, `contenido`, `pos-script` |
| `layouts.chart` | Vistas de indicadores y gráficas KPI. | `titulo`, `contenido`, `pos-script` |
| `layouts.report` | Vistas de productividad y reportes descargables. | `titulo`, `contenido`, `pos-script` |

> **Importante**: En `layouts.crud` los nombres de las secciones están en **español** (`crud_titulo`, `crud_subtitulo`, `crud_acciones`, `contenido`). No usar `crud_title` ni `crud_content`.

### Componentes de Navegación (`resources/views/components/navigation/`)
- **`sidebar.blade.php`**:
  - El menú está definido dinámicamente en un **bloque JSON embebido (`$sidebarMenuJson`)** directamente dentro de `sidebar.blade.php` (admite comentarios `/* ... */` que se limpian antes de `json_decode`).
  - Todas las opciones principales usan el **mismo tamaño (`0.875rem`) y color (`#e2e8f0`, blanco al estar activo)**; las subopciones desplegables usan `0.78rem` y `#cbd5e1`.
  - **Scroll personalizado (no nativo)**: Usa `.sidebar-scroll-wrapper`, `.custom-scroll` (oculta la barra nativa del navegador) y `.sidebar-scroll-thumb` (indicador overlay animado en `resources/css/app.css`).
  - **Estado temporal**: Solo está habilitado el tipo documental **Sellos** (`registros`, `notarias` y `catastro` están comentados con `/* ... */` para reactivarlos fácilmente cuando se requiera).
- **`navbar.blade.php`**:
  - Contiene el botón de colapsar sidebar, título actual, **campana de notificaciones desplegable** (muestra peticiones de autorización e inconsistencias nuevas en vivo por Reverb) y menú del usuario autenticado con su turno.

---

## 4. Organización de Carpetas de Vistas (`resources/views/`)

No repetir nombres de vistas del proyecto antiguo; mantener esta separación por dominio:
- `auth/` — `login.blade.php`
- `operacion/` — `estacion_captura.blade.php` (pantalla completa de trabajo para capturistas)
- `monitoreo/` — `sesiones_activas.blade.php`, `autorizaciones_tiempo_real.blade.php`, `revision_inconsistencias.blade.php`, `resolucion_colisiones.blade.php`, `aprobacion_renombres.blade.php`
- `buscador/` — `explorador_archivos.blade.php`
- `indicadores/` — `sellos_avance.blade.php`, `registros_avance.blade.php`, `notarias_avance.blade.php`, `catastro_avance.blade.php`
- `productividad/` — `sellos_usuarios.blade.php`, `registros_usuarios.blade.php`, `notarias_usuarios.blade.php`, `catastro_usuarios.blade.php`
- `bitacora/` — `historial_sellos.blade.php`, `historial_registros.blade.php`, `historial_notarias.blade.php`, `historial_catastro.blade.php`
- `configuracion/` — `usuarios_index.blade.php`, `roles_index.blade.php`, `permisos_index.blade.php`, `turnos_index.blade.php`, `asignaciones_captura.blade.php`, `inventario_equipos.blade.php`, `catalogos_rutas.blade.php`, `delegaciones_index.blade.php`, `municipios_localidades_index.blade.php`, `catalogo_inconsistencias.blade.php`

---

## 5. Estructura de Rutas (`routes/web.php`)

- **Sin prefijo `panel_control`**. Las rutas están divididas en dos bloques:
  1. **Capturistas** (`/captura/...`): estación de captura, búsqueda de lote, visor PDF, localidades, acciones Solo-BD (`renombrar`, `validar`, `duplicar`, `inconsistencia`), rotación física (`rotar`), liberar lote y solicitudes de autorización.
  2. **Administración** (`/admin/...`): `autorizaciones`, `sellos/*`, `registros/*`, `notarias/*`, `catastro/*`, `usuarios`, `roles`, `permisos`, `turnos`, `asignaciones`, `equipos`, `catalogos`, `delegaciones`, `municipios`, `localidades`, `catalogo-inconsistencias`, `inconsistencias`, `colisiones`, `aprobacion-renombres`.

---

## 6. Reglas de Negocio y Flujo Operativo

1. **Modo Solo-BD en Captura**:
   - Al capturar (`validado`, `renombrado`, `duplicar`, `inconsistencia`), **nunca se renombra ni mueve el archivo físico**.
   - Se guarda en la tabla de auditoría correspondiente (`auditoria_sellos`, etc.): `ruta_origen`, `ruta_propuesta` (calculada por `RutaPropuestaService` desde `config/reglas_destino_pdf.php`), `paginas`, `hash`, `archivo_danado` y `estado_aplicacion = 'pendiente'`.
   - **Excepción física controlada**: La acción **Rotar Página** (`/captura/rotar`) sí rota físicamente la página indicada por el usuario (previo modal de confirmación) y registra en auditoría `accion = 'rotar_pagina'` con el detalle de la página y grados rotados.
2. **Estación de Captura (Regla de Sellos)**:
   - Muestra únicamente los **9 inputs segmentados vacíos** (`Del`, `Secc`, `Libro`, `Tomo`, `Vol`, `Fracc`, `Año`, `Inscr`, `Bis`) con salto automático al siguiente campo.
   - Sin botón de *Mover*, sin botón de *Pantalla*, sin botón de *Configuración* y sin exponer rutas físicas de red al capturista.
   - La regla se selecciona automáticamente según `asignaciones_captura_usuario` (actualmente fija en Regla 2 Sellos).
3. **Detección de Colisiones (`revisar = 1`)**:
   - En cada acción se consulta `indice_archivos_pdfs` en Stellum (`verificar-existencia`). Si el nombre ya existe en entregables, se registra en `detalles` y se marca `revisar = 1` para resolverlo en `/admin/colisiones`.
4. **Catálogo de Inconsistencias (`R01–R09`)**:
   - Administrable desde `/admin/catalogo-inconsistencias` (`CatalogoInconsistencia`).
   - Al reportar una inconsistencia, se notifica en vivo a la campana del navbar (`InconsistenciaReportadaEvent`) y se gestiona en `/admin/inconsistencias`.
5. **Aprobación Masiva hacia Stellum (Millones de Archivos)**:
   - En `/admin/aprobacion-renombres`, el admin aprueba por delegación y tipo de sello.
   - Se crea un registro en `ordenes_aplicacion_stellum` y `EnviarOrdenStellumJob` despacha los cambios en bloques de 500 (`chunkById(500)`) hacia `POST /api/captura-bridge/aplicar-cambios/lote` en Stellum, donde `AplicarCambiosPdfJob` los procesa en cola de forma asíncrona.
6. **Manejo de Sesiones**:
   - **Autenticación**: Laravel Auth (`SESSION_DRIVER=file`, `120 min`).
   - **Sesiones de Lotes**: Tabla unificada `lotes_captura` (`LoteCaptura`). Bloquea un lote (`en_proceso`) al capturista, lo reanuda automáticamente si recarga, y lo muestra en tiempo real en `/admin/sellos/sesiones-activas`.
7. **Equipos Entregados y Asignación de PC**:
   - `/admin/equipos` consulta `GET /api/captura-bridge/equipos/entregados` en Stellum, lista solo PCs/Laptops marcadas como `entregada` y permite asignarlas a usuarios (guardando en `users.equipo_*` y sincronizando `prestado_a` en Stellum).
8. **Turnos Adaptativos y Sin Bono**:
   - Los turnos son dinámicos (`turnos`, `TurnoController`) con `hora_entrada` y `hora_salida` enlazados a `users.turno_id`. No existe cálculo de bonos en el sistema.
9. **Catálogos Base**:
   - `AnioSeeder`: años `1850–2030`.
   - `DelegacionesSeeder`, `MunicipiosSeeder`, `LocalidadesSeeder` (27,631 localidades).
   - `RolesSeeder` + `UsersSeeder` (admin principal + 1 usuario por rol).

---

## 7. Comandos Esenciales para Desarrollo y Despliegue

```bash
# Compilar Tailwind CSS v4 y JS con Vite (obligatorio tras editar vistas Blade o app.css)
npm run build

# Ejecutar migraciones y seeders base
php artisan migrate --force
php artisan db:seed --class=CatalogoInconsistenciasSeeder --force
php artisan db:seed --class=AnioSeeder --force

# Limpiar cachés tras desplegar cambios de configuración o rutas
php artisan optimize:clear

# Servicios en segundo plano recomendados en producción
php artisan reverb:start --host=127.0.0.1 --port=8080
php artisan queue:work --tries=3 --timeout=600
```
