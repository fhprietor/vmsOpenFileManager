# VmsOpenFileManager

Módulo de **gestión de archivos y liveries** para **phpVMS 7**.

Dos partes bien diferenciadas:

1. **File manager**: carpetas y ficheros con subida, miniaturas, contador de
   descargas y una tabla de permisos (sin uso hoy). Los pilotos los descargan
   desde `/vmsOpenDownloads`.
2. **Liveries**: catálogo de repintados organizado por **fabricante**,
   **simulador** y **subflota**, con orden manual, activación y descarga desde el
   panel del piloto. Los ficheros pueden ser locales (ZIP subido) o externos
   (URL, p. ej. mega.nz).

- **Alias:** `vmsopenfilemanager`
- **Providers:** `AppServiceProvider`, `EventServiceProvider`, `RouteServiceProvider`
- **Config:** `config('vmsopenfilemanager.*')` (ver aviso: casi ninguna clave se usa)

## Requisitos

- phpVMS 7 (Laravel 10, PHP 8.1+), MySQL/MariaDB.
- **Intervention Image** para las miniaturas: ya es dependencia del core
  (`intervention/image`). Hace falta GD o Imagick.
- Disco `public` de Laravel enlazado (`php artisan storage:link`).

## Funcionalidad

| Función | Detalle |
|---|---|
| **Carpetas** | `vmsopen_folders`, jerárquicas (`parent_id`), con `is_public`, `is_livery_folder`, icono, orden y `settings` JSON. |
| **Ficheros** | `vmsopen_files`: nombre, fichero, extensión, tamaño (**texto legible**, p. ej. "617.47 KB"), mime, ruta, miniatura, carpeta, autor, descargas, activo, `metadata` JSON. |
| **Permisos** | `vmsopen_permissions` + modelo `FilePermission`: **creados pero sin usar** (0 filas, sin controladores que los consulten). |
| **Subidas** | File manager: cualquier extensión, límite duro de 100 MB. Livery local: solo `zip`, hasta 200 MB, con miniatura opcional. |
| **Descargas** | `/vmsOpenDownloads` (índice, carpeta, fichero) incrementando el contador. |
| **Fabricantes** | CRUD en admin: `vmsopen_manufacturers` (nombre, slug, logo, orden, activo). |
| **Simuladores** | `vmsopen_simulators` (nombre, slug, logo, color, orden, activo); la migración siembra 6 (MSFS, MSFS2024, XP11, XP12, P3D v5, P3D v4). |
| **Liveries** | `vmsopen_liveries`: subflota, simulador, fabricante, aeronave, miniatura, tipo (`local`/`external`), ruta o URL, tamaño, descripción, descargas, activo, orden. |
| **Catálogo de piloto** | `/liveries`: por simulador, por simulador+subflota, buscador, destacados, info (JSON) y descarga. |

**Estado actual en producción** (referencia): 6 carpetas, 6 ficheros, 6
simuladores, 14 fabricantes y 61 liveries — todas `external` (enlaces mega.nz),
57 con miniatura, 59 con aeronave asignada. `vmsopen_permissions`: 0 filas.

## Rutas

### Admin — prefijo `admin` (`web` + `auth` + **`role:admin`** de Laratrust)

| Método | URI | Controlador@método |
|---|---|---|
| GET | `/admin/vmsopenfilemanager` | `Admin\FileManagerController@index` |
| POST | `/admin/vmsopenfilemanager/folder` | `@createFolder` |
| POST | `/admin/vmsopenfilemanager/upload` | `@uploadFile` |
| DELETE | `/admin/vmsopenfilemanager/file/{id}` | `@deleteFile` |
| DELETE | `/admin/vmsopenfilemanager/folder/{id}` | `@deleteFolder` |
| GET | `/admin/manufacturers` | `Admin\ManufacturerController@index` |
| POST | `/admin/manufacturers` | `@store` |
| PUT | `/admin/manufacturers/{manufacturer}` | `@update` |
| DELETE | `/admin/manufacturers/{manufacturer}` | `@destroy` |
| GET | `/admin/liveries` | `Admin\LiveryController@index` |
| POST | `/admin/liveries/upload` | `@upload` |
| POST | `/admin/liveries/update-order` | `@updateOrder` |
| GET | `/admin/liveries/{id}/edit` | `@edit` |
| PUT | `/admin/liveries/{id}` | `@update` |
| POST | `/admin/liveries/{id}/toggle` | `@toggleActive` |
| DELETE | `/admin/liveries/{id}` | `@delete` |

Enlaces de admin registrados: **File Manager** (`/admin/vmsopenfilemanager`),
**Liveries** (`/admin/liveries`) y **Manufacturers** (`/admin/manufacturers`).

> Se usa `role:admin` (Laratrust), **no** la convención del core
> `ability:admin,admin-access`.

### Piloto — `web` + `auth`

| Método | URI | Controlador@método |
|---|---|---|
| GET | `/vmsOpenDownloads` | `Pilot\FileDownloadController@index` |
| GET | `/vmsOpenDownloads/folder/{folderId}` | `@folder` |
| GET | `/vmsOpenDownloads/file/{fileId}` | `@download` |
| GET | `/liveries` | `Pilot\LiveryController@index` |
| GET | `/liveries/simulator/{simulator}` | `@bySimulator` |
| GET | `/liveries/simulator/{simulator}/subfleet/{subfleet}` | `@bySubfleet` |
| GET | `/liveries/search` | `@search` |
| GET | `/liveries/featured/{limit?}` | `@featured` |
| GET | `/liveries/info/{id}` | `@info` |
| GET | `/liveries/download/{id}` | `@download` |

### API

**No hay superficie de API.** `Routes/api.php`, `Routes/test.php` y todo
`Http/Routes/*` **no los carga ningún provider** (ver Deuda técnica).

## Modelos y tablas

| Modelo | Tabla | Notas |
|---|---|---|
| `FileFolder` | `vmsopen_folders` | `parent`/`children`/`files`; `slug` único; `parent_id` **sin FK** |
| `FileItem` | `vmsopen_files` | `folder`, `user`; accessor `is_image`; `incrementDownloads()`; **sin FK** |
| `FilePermission` | `vmsopen_permissions` | `folder`, `role`, `user`; **sin uso** |
| `Livery` | `vmsopen_liveries` | `subfleet`, `simulator`, `manufacturer`, `aircraft`; scopes `active/local/external/bySimulator/bySubfleet/byManufacturer`; `incrementDownloads()` |
| `Manufacturer` | `vmsopen_manufacturers` | `liveries`, `activeLiveries`; siembra 6 |
| `Simulator` | `vmsopen_simulators` | `liveries`, `activeLiveries`; siembra 6 |

Claves primarias **autoincrementales** (no UUID). FKs de `vmsopen_liveries` a
`subfleets`/`simuladores`/`manufacturers`/`aircraft`; el resto de tablas no
declara FKs.

Servicios: `FileManagerService` (crear carpeta, subir fichero con miniatura,
borrar) y `StorageService` (miniaturas con Intervention Image, borrar,
comprobar).

## Almacenamiento

Todo en el disco `public` (`storage/app/public`, servido en `/storage`):

- ficheros: `filemanager/{slug-carpeta}/{nombre-slug-timestamp}.{ext}`
- miniaturas de fichero: `{nombre}_thumb.{ext}` en la misma carpeta
- liveries locales: `liveries/files/{slug}.{ext}`
- miniaturas de livery: `liveries/thumbnails/{hash}.{ext}` (redimensionadas a 300 px de ancho)

Las vistas usan `Storage::url()` con el disco por defecto (`local`), que coincide
por casualidad con la URL pública.

## Configuración (`Config/config.php`)

| Clave | Valor | ¿Se usa? |
|---|---|---|
| `thumbnail_size` | 200×200 | **sí** (`StorageService`) |
| `max_file_size` | 104857600 (100 MB) | **no** — la validación fija `max:102400` KB |
| `allowed_extensions` | pdf, jpg, … | **no** — no hay filtro de extensiones |
| `disk` | `public` | **no** — `'public'` está escrito a mano |
| `liveries_folder` | `liveries` | **no** — rutas fijas `liveries/files` y `liveries/thumbnails` |
| `name` | VmsOpenFileManager | no |

No hay **ningún** `setting()` ni fila en la tabla `settings`.

## Instalación

1. Copiar el módulo a `modules/VmsOpenFileManager`.
2. **Admin → Modules** → activar.
3. Visitar `/update` para lanzar migraciones (⚠️ ver aviso de migración abajo).
4. `php artisan storage:link`.
5. Crear fabricantes/simuladores y subir liveries.

Como submódulo del repositorio central:

```bash
git submodule update --init modules/VmsOpenFileManager
git -C modules/VmsOpenFileManager pull origin main && git add modules/VmsOpenFileManager && git commit
```

## Estructura

```
Config/              config.php (referencia; casi nada se usa)
Database/Migrations/ vmsopen_folders, _files, _permissions, _simulators,
                     _manufacturers, _liveries (siembran simuladores y fabricantes)
Http/Controllers/    Admin/  Pilot/  (Api/ y Frontend/ = andamiaje)
Routes/              admin.php  web.php        <-- las unicas que se cargan
Models/              FileFolder, FileItem, FilePermission, Livery,
                     Manufacturer, Simulator
Services/            FileManagerService, StorageService
Resources/views/     admin/  pilot/  layouts/
```

## Integración

- `resources/views/layouts/vholar/nav.blade.php` enlaza
  `route('vmsopenfilemanager.liveries.index')` y `.downloads.index`: **si el
  módulo se desactiva, la navegación del tema lanza error**.
- No hay overrides del tema para este alias.
- Sin acoplamiento con otros módulos.

## Deuda técnica y seguridad

1. **⚠️ Numeración de migración inconsistente (rompe instalaciones limpias):** la
   tabla `migrations` de esta instalación registra
   `2024_01_01_000004_create_vmsopen_liveries_table`, pero el fichero en disco es
   `2024_01_01_000007_create_vmsopen_liveries_table.php`. En un despliegue nuevo
   se ejecutaría `000007` y fallaría con "table already exists". Hay que
   renombrar el fichero a `..._000004_...` (o recrear el registro) antes de
   publicar el módulo.
2. **Sin API**: `Routes/api.php`, `Routes/test.php` y `Http/Routes/*` no se
   cargan. `Http/Routes/*` apunta a `AdminController`/`ApiController`/
   `IndexController` de andamiaje (el de admin ni existe).
3. **Vistas que faltan** (provocan 500 si se alcanzan):
   `admin/settings/index.blade.php` (`Admin\SettingsController@index`, que además
   no tiene ruta) y `pilot/liveries/search.blade.php` (rama HTML de `search`; la
   rama JSON funciona).
4. **Sin control de acceso por fichero/carpeta**: `FilePermission` y
   `vmsopen_permissions` están muertos (0 filas). `folder($id)` y
   `download($fileId)` no vuelven a comprobar `is_public`, y
   `Pilot\LiveryController@download($id)` no comprueba `is_active`: **cualquier
   piloto autenticado puede descargar cualquier fichero o livery por ID**.
5. **Sin validación de extensiones** en el file manager (la tabla llegó a
   contener un `.config`), y los límites están fijados en código: 100 MB
   (ficheros) y 200 MB + `mimes:zip` (livery local).
6. `vmsopenfilemanager.max_file_size`, `allowed_extensions`, `disk` y
   `liveries_folder` **no se usan**: documentan una intención que el código no
   aplica.
7. **Tamaños como texto**: `FileItem.size` y `Livery.file_size` guardan cadenas
   legibles ("617.47 KB"), no bytes → no se pueden sumar ni ordenar por tamaño.
8. `$breadcrumbs` no se pasa a la vista del file manager: el breadcrumb solo
   muestra "Root".
9. `@push('css')` en dos vistas de piloto no tiene efecto: el layout `app` de
   Vholar no define `@stack('css')`.
10. Andamiaje sin usar: `Traits/` vacío, `Console/Commands/` vacío,
    `Database/factories` y `Database/seeds` vacíos, `tests/` sin ficheros,
    `Listeners/TestEventListener` no registrado, `EventServiceProvider::$listen`
    vacío, `RouteServiceProvider::mapAdminRoutes()` sin llamar, `ApiController`
    usa `Auth::user()` sin importar la fachada, `Frontend\IndexController` sin
    ruta.
11. `module.json` no declara `version`; `composer.json` mantiene marcadores
    (`VENDOR/...`, autor vacío) y requiere `composer/installers ~1.0` frente al
    `~1.12.0` del core.
12. El JS de admin lleva las URLs `/admin/liveries/...` escritas a mano.
13. `Admin\LiveryController` y `Admin\ManufacturerController` repiten
    `auth`+`role:admin` en el constructor (el grupo ya los aplica).
14. El accessor `Livery::download_url` no se usa: el controlador de piloto siempre
    pasa por `/liveries/download/{id}` para poder contar las descargas.
