<?php

namespace Modules\VmsOpenFileManager\Http\Controllers\Pilot;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\VmsOpenFileManager\Models\FileFolder;
use Modules\VmsOpenFileManager\Models\FileItem;

class FileDownloadController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $folders = FileFolder::whereNull('parent_id')
            ->where(function ($query) use ($user) {
                $query->where('is_public', true);
            })
            ->orderBy('order')
            ->get();

        return view('vmsopenfilemanager::pilot.downloads.index', compact('folders'));
    }

    public function folder($folderId)
    {
        // La carpeta debe ser publica (misma regla que el indice); si no, 404
        $folder = FileFolder::with(['parent', 'children', 'files'])
            ->where('is_public', true)
            ->findOrFail($folderId);

        $subfolders = $folder->children()->where('is_public', true)->get();
        $files = $folder->files()->where('is_active', true)->orderBy('created_at', 'desc')->get();

        return view('vmsopenfilemanager::pilot.downloads.folder', compact('folder', 'subfolders', 'files'));
    }

    public function download($fileId)
    {
        // Solo ficheros activos de carpetas publicas. Sin esto, cualquier piloto
        // autenticado podia descargar cualquier fichero por su ID.
        $file = FileItem::with('folder')
            ->where('is_active', true)
            ->whereHas('folder', function ($query) {
                $query->where('is_public', true);
            })
            ->findOrFail($fileId);

        if (!Storage::disk('public')->exists($file->path)) {
            abort(404);
        }

        $file->incrementDownloads();

        // Usar el nombre original con extensión
        $originalName = $file->name . '.' . $file->extension;

        return Storage::disk('public')->download($file->path, $originalName);
    }
}