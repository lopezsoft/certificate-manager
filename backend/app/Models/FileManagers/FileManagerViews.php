<?php

namespace App\Models\FileManagers;

use App\Traits\RedirectTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FileManagerViews
{
    public static function index(Request $request)
    {
        $user   = Auth::user();
        $tz     = getTimeZone();
        $tz_utc = getTimeZoneUTC();

        $filter = [
            'n' => $request->input('n') ? $request->input('n') : '',
            'uuid' => $request->input('uuid') ? $request->input('uuid') : '',
            'ds' => $request->input('ds') ? $request->input('ds') : '',
            'df' => $request->input('df') ? $request->input('df') : '',
        ];

        $fileManagerQuery         = FileManager::query()
                                        ->where('user_id', '=', $user->id);

        if (isset($filter['uuid']) && $filter['uuid']) {
            $fileManagerQuery->where('uuid', 'like', '%' . $filter['uuid'] . '%');
        }
        if (isset($filter['n']) && $filter['n']) {
            $fileManagerQuery->where('file_name', 'like', '%' . $filter['n'] . '%');
        }

        if (isset($filter['ds']) && $filter['ds']) {
            $ds = new \DateTime($filter['ds'], $tz);
            $ds->setTimezone($tz_utc);
            $fileManagerQuery->where('created_at', '>=', $ds);
        }

        if (isset($filter['df']) && $filter['df']) {
            $df = new \DateTime($filter['df'], $tz);
            $df->modify('+1 day');
            $df->setTimezone($tz_utc);
            $fileManagerQuery->where('created_at', '<=', $df);
        }

        $fileManagerQuery->orderBy('created_at', 'desc');
        $fileManager              = $fileManagerQuery->paginate(10);
        $data = [
            'files'             => $fileManager->appends(request()->except(['page', 'q'])),
            'filter'            => $filter,
            'csrf_token'        => csrf_token(),
            'sideBar'           => true,
        ];
        return view('dashboard.file-manager.index', $data);
    }

    public static function show($id)
    {
        $user   = Auth::user();

        $fileManagerQuery         = FileManager::query()
            ->where('user_id', '=', $user->id)
            ->where('uuid', '=', "{$id}");

        $fileManagerQuery->orderBy('created_at', 'desc');
        $fileManager              = $fileManagerQuery->paginate(10);
        $data = [
            'cases'             => $fileManager->appends(request()->except(['page', 'q'])),
            'filter'            => null,
            'csrf_token'        => csrf_token(),
            'sideBar'           => true,
        ];
        return view('dashboard.file-manager.index', $data);
    }
    public function destroy($id) {
        $fileManager = FileManager::where('id', '=', $id)->first();
        if (!$fileManager) {
            return RedirectTrait::redirectWithNotification('/dashboard/filemanager', 'Archivo no existe.');
        }
        if (count($fileManager->cases) > 0) {
            return redirect('/dashboard/filemanager')->with('notification', 'Archivo no puede ser eliminado.');
        }

        $fileManager->delete();

        return redirect('/dashboard/filemanager')->with('notification', 'Archivo eliminado.');
    }
}
