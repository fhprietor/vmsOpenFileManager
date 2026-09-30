# VmsOpenFileManager

Módulo de **gestión de archivos y liveries** para **phpVMS 7**.

Dos partes bien diferenciadas:

1. **File manager**: carpetas y ficheros con subida, miniaturas, contador de
   descargas y una tabla de permisos. Los pilotos los descargan desde
   `/vmsOpenDownloads`.
2. **Liveries**: catálogo de repintados organizado por **fabricante**,
   **simulador** y **subflota**, con orden manual, activación y descarga desde el
   panel del piloto.

- **Alias:** `vmsopenfilemanager`
- **Providers:** `AppServiceProvider`, `EventServiceProvider`, `RouteServiceProvider`
- **Config:** `config('vmsopenfilemanager.*')`

## Requisitos

- phpVMS 7 (Laravel 10, PHP 8.1+), MySQL/MariaDB.
- **Intervention Image** para las miniaturas: ya es dependencia de phpVMS 7
  (`intervention/image`), no hay que instalar nada extra.
- El disco de Laravel configurado (`public`) enlazado con
  `php artisan storage:link` para servir los ficheros.

## Funcionalidad

| Función | Detalle |
|---|---|
| **Carpetas** | `vmsopen_folders` (jerárquicas con `parent_id`, `is_public`, `is_livery_folder`, orden, icono y `settings` JSON). |
| **Ficheros** | `vmsopen_files` (nombre, extensión, tamaño, mime, ruta, miniatura, carpeta, autor, descargas, `metadata` JSON). |
| **Permisos** | `vmsopen_permissions` (tabla creada; hoy sin filas). |
| **Subidas** | Validación por extensión y tamaño; miniaturas automáticas. |
| **Descargas** | Panel del piloto en `/vmsOpenDownloads` (índice, navegación por carpeta y descarga) con contador. |
| **Fabricantes** | CRUD en admin (`vmsopen_manufacturers`: nombre, slug, logo, orden, activo). |
| **Simuladores** | `vmsopen_simulators` (nombre, slug, logo, color, orden, activo). |
| **Liveries** | `vmsopen_liveries` (nombre, slug, subflota, simulador, fabricante, aeronave, miniatura, tipo/ruta/tamaño de fichero, descripción, descargas, activo, orden). |
| **Catálogo de piloto** | `/liveries`: por simulador, por simulador+subflota, buscador, destacados, info y descarga. |

## Rutas

### Admin — prefijo `admin` (middleware `web` + `auth` + `role:admin`)

| Método | URI | Controlador |
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

### Piloto — middleware `web`, con `auth` en cada grupo

| Método | URI | Controlador |
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

## Configuración

`Config/config.php`:

| Clave | Valor | Descripción |
|---|---|---|
| `vmsopenfilemanager.max_file_size` | `104857600` | 100 MB |
| `vmsopenfilemanager.allowed_extensions` | `pdf, jpg, jpeg, png, gif, zip, rar, 7z` | Extensiones admitidas |
| `vmsopenfilemanager.thumbnail_size` | `200×200` | Miniaturas |
| `vmsopenfilemanager.disk` | `public` | Disco de Laravel |
| `vmsopenfilemanager.liveries_folder` | `liveries` | Carpeta base de liveries |

## Modelos y servicios

- `Models/FileFolder`, `FileItem`, `FilePermission`, `Livery`, `Manufacturer`,
  `Simulator` (tablas `vmsopen_*`).
- `Services/FileManagerService`: crear carpeta, subir fichero (con miniatura),
  borrar fichero y carpeta, asegurar directorios.
- `Services/StorageService`: generar miniatura, borrar y comprobar ficheros.

## Instalación

1. Copiar el módulo a `modules/VmsOpenFileManager`.
2. **Admin → Modules** → activar `VmsOpenFileManager`.
3. Visitar `/update` para lanzar migraciones.
4. `php artisan storage:link` si no existe el enlace público.
5. Crear fabricantes y simuladores, y subir liveries desde el admin.

Como submódulo del repositorio central:

```bash
git submodule update --init modules/VmsOpenFileManager
git -C modules/VmsOpenFileManager pull origin main && git add modules/VmsOpenFileManager && git commit
```

## Estructura

```
Config/              config.php
Database/Migrations/ vmsopen_folders, _files, _permissions, _simulators,
                     _manufacturers, _liveries
Http/Controllers/    Admin/  Pilot/  (Api/, Frontend/ = restos de andamiaje)
Routes/              admin.php  web.php        <-- las que se cargan
Models/              FileFolder, FileItem, FilePermission, Livery,
                     Manufacturer, Simulator
Services/            FileManagerService, StorageService
Resources/views/     admin/  pilot/  layouts/
Resources/lang/      traducciones
```

## Notas y deuda técnica

- **Solo se cargan `Routes/admin.php` y `Routes/web.php`** desde
  `RouteServiceProvider`. El resto de ficheros de rutas **no se cargan**:
  - `Http/Routes/{admin,api,web}.php` (andamiaje del generador de módulos;
    apuntan a controladores `AdminController`/`ApiController`/`IndexController`
    que no existen).
  - `Routes/api.php` (solo una ruta de prueba) y `Routes/test.php`
    (`/test-direct`).
- `RouteServiceProvider::mapAdminRoutes()` no se usa (y su `namespace` no
  resolvería las clases, que se importan con `::class`).
- `Admin\SettingsController`, `EventListener`/`TestEventListener` y
  `EventServiceProvider::$listen` (vacío) son restos del andamiaje.
- Directorios vacíos del generador: `Console/Commands`, `Http/Middleware`,
  `Http/Requests`, `Database/factories`, `Database/seeds`, `tests`.
- La tabla `vmsopen_permissions` existe pero no tiene filas: el control de
  permisos por fichero/carpeta está por completar.
- `vmsopenfilemanager.disk` **no se usa**: los servicios tienen `'public'`
  escrito a mano (`Storage::disk('public')`). Si se quiere otro disco, hay que
  cambiarlo en `FileManagerService` y `StorageService`.
- Los ficheros se guardan en el disco `public`; conviene revisar que el enlace
  simbólico y los permisos del servidor web sean correctos antes de subir
  contenido grande.
