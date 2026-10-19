<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Setting;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session as FacadesSession;
use Illuminate\Support\Facades\Validator;

class LanguageController extends Controller
{
    private function resolveLanguageFilePath(string $languageCode, string $type): string
    {
        if ($type === 'app') {
            return base_path('public/languages/'.$languageCode.'.json');
        }
        if ($type === 'web') {
            return base_path('public/web_languages/'.$languageCode.'.json');
        }

        // default to admin panel translations
        return base_path('resources/lang/'.$languageCode.'.json');
    }

    private function resolveBaselineEnFilePath(string $type): string
    {
        // Always compare against English baseline file for the same surface (admin/app/web)
        if ($type === 'app') {
            return base_path('public/languages/en.json');
        }
        if ($type === 'web') {
            return base_path('public/web_languages/en.json');
        }

        return base_path('resources/lang/en.json');
    }

    private function validateType(string $type): string
    {
        $allowed = ['admin', 'app', 'web'];

        return in_array($type, $allowed) ? $type : 'admin';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! has_permissions('read', 'language')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $language_count = Language::count();

        if ($language_count == 0) {
            $lang = new Language;
            $lang->name = 'English';
            $lang->code = 'en';
            $lang->file_name = 'en.json';
            $lang->status = 1;
            $lang->save();
        }

        return view('settings.language');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if (! has_permissions('create', 'language')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $request->validate([
            'name' => 'required',
            'code' => 'required|regex:/^[a-z0-9-]+$/|unique:languages',
            'file' => 'required',
            'file_for_web' => 'required',
            'file_for_panel' => 'required',
        ]);
        $language = new Language;
        $language->name = $request->name;
        $language->code = $request->code;
        $language->status = 0;
        $language->rtl = $request->rtl == 'true' ? true : false;

        if ($request->code == 'en') {
            $languageCode = 'en-new';
            $languageExists = Language::whereIn('code', ['en-new', 'en'])->first();
            if ($languageExists) {
                return redirect()->back()->with('error', trans('English language already exists'));
            }
        } else {
            $languageCode = $request->code;
        }
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if (strtolower($file->getClientOriginalExtension()) != 'json') {
                return redirect()->back()->with('error', 'Invalid File');
            }
            $filename = $languageCode.'.'.strtolower($file->getClientOriginalExtension());
            $file->move(base_path('public/languages/'), $filename);
            $language->file_name = $filename;
        }
        if ($request->hasFile('file_for_web')) {
            $file = $request->file('file_for_web');
            if (strtolower($file->getClientOriginalExtension()) != 'json') {
                return redirect()->back()->with('error', 'Invalid File');
            }
            $filename = $languageCode.'.'.strtolower($file->getClientOriginalExtension());
            $file->move(base_path('public/web_languages/'), $filename);
            $language->file_name = $filename;
        }
        if ($request->hasFile('file_for_panel')) {
            $file = $request->file('file_for_panel');
            if (strtolower($file->getClientOriginalExtension()) != 'json') {
                return redirect()->back()->with('error', 'Invalid File');
            }
            $filename = $languageCode.'.'.strtolower($file->getClientOriginalExtension());
            $file->move(base_path('resources/lang/'), $filename);
            $language->file_name = $filename;
        }
        $language->save();
        ResponseService::successRedirectResponse('Data Added Successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (! has_permissions('read', 'language')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');

        $sql = Language::query();

        if (isset($_GET['search']) && ! empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('code', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%");
        }

        $total = $sql->count();

        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $tempRow = [];
        $count = 1;
        $operate = '';
        foreach ($res as $row) {
            $defaultLanguageCode = system_setting('default_language');
            $tempRow = $row->toArray();
            $tempRow['rtl'] = $row->rtl ? 'Yes' : 'No';
            $tempRow['file_for_admin'] = file_exists(base_path('resources/lang/'.$row->file_name)) ? url('resources/lang/'.$row->file_name) : false;
            $tempRow['file_for_app'] = file_exists(base_path('public/languages/'.$row->file_name)) ? url('public/languages/'.$row->file_name) : false;
            $tempRow['file_for_web'] = file_exists(base_path('public/web_languages/'.$row->file_name)) ? url('public/web_languages/'.$row->file_name) : false;
            $tempRow['edit_status_url'] = route('update-language-status', $row->id);
            $tempRow['is_disabled'] = $defaultLanguageCode == $row->code ? true : false;
            $ids = isset($row->parameter_types) ? $row->parameter_types : '';
            $operate = '';
            if (has_permissions('update', 'language')) {
                $operate = BootstrapTableService::editButton('', true, null, null, $row->id, null);
                $dropdownItems = [
                    [
                        'icon' => '',
                        'url' => route('language.translations.edit', ['id' => $row->id, 'type' => 'admin']),
                        'text' => 'Edit Panel Json',
                    ],
                    [
                        'icon' => '',
                        'url' => route('language.translations.edit', ['id' => $row->id, 'type' => 'web']),
                        'text' => 'Edit Web Json',
                    ],
                    [
                        'icon' => '',
                        'url' => route('language.translations.edit', ['id' => $row->id, 'type' => 'app']),
                        'text' => 'Edit App Json',
                    ],
                ];

                $operate .= BootstrapTableService::dropdown('fas fa-ellipsis-v', $dropdownItems);
            }
            if (isset($defaultLanguageCode) && ! empty($defaultLanguageCode && has_permissions('delete', 'language'))) {
                if ($defaultLanguageCode != $row->code) {
                    $operate .= BootstrapTableService::deleteButton(route('language.destroy', $row->id), $row->id);
                }
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        if (! has_permissions('update', 'language')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $request->validate([
            'edit_language_name' => 'required',
            'edit_language_code' => 'required|regex:/^[a-z0-9-]+$/|unique:languages,code,'.$request->edit_id.',id',
        ]);
        $defaultLanguageCode = system_setting('default_language');

        $language = Language::find($request->edit_id);
        $language->name = $request->edit_language_name;
        $language->rtl = $request->edit_rtl == 'on' ? true : false;
        if ($request->edit_language_code == 'en') {
            $languageCode = 'en-new';
            $languageExists = Language::where('id', '!=', $request->edit_id)->whereIn('code', ['en-new', 'en'])->first();
            if ($languageExists) {
                return redirect()->back()->with('error', trans('English language already exists'));
            }
        } else {
            $languageCode = $request->edit_language_code;
        }
        if ($defaultLanguageCode == $language->code) {
            Setting::where('type', 'default_language')->update(['data' => $languageCode]);
        }
        $language->code = $languageCode;

        // Edit App JSON File
        if ($request->hasFile('edit_json_app')) {
            $file = $request->file('edit_json_app');
            $filename = $languageCode.'.'.strtolower($file->getClientOriginalExtension());
            if (strtolower($file->getClientOriginalExtension()) != 'json') {
                return back()->with('error', 'Invalid File Type');
            }
            if (file_exists(base_path('public/languages/'.$languageCode))) {
                File::delete(base_path('public/languages/'.$languageCode));
            }
            $file->move(base_path('public/languages/'), $filename);
            $language->file_name = $filename;
        }

        // Edit Admin JSON File
        if ($request->hasFile('edit_json_admin')) {
            $file = $request->file('edit_json_admin');
            $filename = $languageCode.'.'.strtolower($file->getClientOriginalExtension());
            if (strtolower($file->getClientOriginalExtension()) != 'json') {
                return redirect()->back()->with('success', 'Invalid File');
            }
            if (file_exists(base_path('resources/lang/'.$languageCode))) {
                File::delete(base_path('resources/lang/'.$languageCode));
            }
            $file->move(base_path('resources/lang/'), $filename);
            $language->file_name = $filename;
        }

        // Edit Web JSON File
        if ($request->hasFile('edit_json_web')) {
            $file = $request->file('edit_json_web');
            $filename = $languageCode.'.'.strtolower($file->getClientOriginalExtension());
            if (strtolower($file->getClientOriginalExtension()) != 'json') {
                return redirect()->back()->with('error', 'Invalid File Type');
            }
            if (file_exists(base_path('public/web_languages/'.$languageCode))) {
                File::delete(base_path('public/web_languages/'.$languageCode));
            }
            $file->move(base_path('public/web_languages/'), $filename);
            $language->file_name = $filename;
        }

        $language->save();
        ResponseService::successRedirectResponse('Data Updated Successfully');
    }

    public function editTranslations(Request $request, int $id)
    {
        if (! has_permissions('update', 'language')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $language = Language::findOrFail($id);
        $type = $this->validateType($request->query('type', 'admin'));
        $filePath = $this->resolveLanguageFilePath($language->getRawOriginal('code'), $type);

        $translations = [];
        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $translations = $decoded;
            }
        }

        // Merge with baseline en.json so missing keys appear for editing
        $baseline = [];
        $baselinePath = $this->resolveBaselineEnFilePath($type);
        if (file_exists($baselinePath)) {
            $json = file_get_contents($baselinePath);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $baseline = $decoded;
            }
        }
        if (! empty($baseline)) {
            foreach ($baseline as $key => $enValue) {
                if (! array_key_exists($key, $translations)) {
                    $translations[$key] = '';
                }
            }
        }

        return view('settings.language_translations', [
            'language' => $language,
            'type' => $type,
            'translations' => $translations,
        ]);
    }

    public function saveTranslations(Request $request, int $id)
    {
        // Fallback non-AJAX full-save endpoint (for small files or non-JS clients)
        if (! has_permissions('update', 'language')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $request->validate([
            'type' => 'required|in:admin,app,web',
            'translations' => 'required|array',
        ]);

        $this->mergeTranslationsIntoFile($request, $id, $request->input('translations', []));

        return redirect()->route('language.translations.edit', [
            'id' => $id,
            'type' => $this->validateType($request->input('type')),
        ])
            ->with('success', trans('Data Updated Successfully'));
    }

    /**
     * Chunked translations save endpoint (AJAX).
     * Accepts a subset of keys and merges them into the JSON file.
     */
    public function saveTranslationsChunk(Request $request, int $id)
    {
        if (! has_permissions('update', 'language')) {
            return response()->json([
                'error' => true,
                'message' => trans(PERMISSION_ERROR_MSG),
            ], 403);
        }

        $request->validate([
            'type' => 'required|in:admin,app,web',
            'translations' => 'required|array',
        ]);

        $chunk = $request->input('translations', []);

        $this->mergeTranslationsIntoFile($request, $id, $chunk);

        return response()->json([
            'error' => false,
            'message' => trans('Data Updated Successfully'),
        ]);
    }

    /**
     * Merge a (possibly partial) translations array into the language JSON file.
     */
    private function mergeTranslationsIntoFile(Request $request, int $id, array $chunk): void
    {
        $language = Language::findOrFail($id);
        $type = $this->validateType($request->input('type'));
        $filePath = $this->resolveLanguageFilePath($language->getRawOriginal('code'), $type);

        $existing = [];
        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        // Ensure all baseline keys exist so we keep parity with en.json
        $baselinePath = $this->resolveBaselineEnFilePath($type);
        if (file_exists($baselinePath)) {
            $baselineJson = file_get_contents($baselinePath);
            $baselineDecoded = json_decode($baselineJson, true);
            if (is_array($baselineDecoded)) {
                foreach ($baselineDecoded as $key => $_) {
                    if (! array_key_exists($key, $existing)) {
                        $existing[$key] = '';
                    }
                }
            }
        }

        // Merge this chunk only
        foreach ($chunk as $key => $value) {
            if (is_string($key)) {
                $existing[$key] = is_null($value) ? '' : (string) $value;
            }
        }

        if (! is_dir(dirname($filePath))) {
            File::makeDirectory(dirname($filePath), 0755, true, true);
        }

        file_put_contents($filePath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
            return redirect()->back()->with('error', trans('This is not allowed in the Demo Version'));
        }

        if (! has_permissions('delete', 'language')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $language = Language::find($id);
            $languageData = $language;
            if ($language->code == 'en') {
                return redirect()->back()->with('error', trans('Default English is the default language and cannot be deleted'));
            }
            if ($language->delete()) {
                if (file_exists(base_path('public/languages/'.$languageData->file_name))) {
                    unlink(base_path('public/languages/'.$languageData->file_name));
                }
                if (file_exists(base_path('public/web_languages/'.$languageData->file_name))) {
                    unlink(base_path('public/web_languages/'.$languageData->file_name));
                }
                if (file_exists(base_path('resources/lang/'.$languageData->file_name))) {
                    unlink(base_path('resources/lang/'.$languageData->file_name));
                }

                return redirect()->back()->with('success', trans('Data Deleted Successfully'));
            } else {
                return redirect()->back()->with('error', trans('Something Went Wrong'));
            }
        }
    }

    public function set_language(Request $request)
    {
        FacadesSession::put('locale', $request->lang);
        $language = Language::where('code', $request->lang)->first();
        FacadesSession::put('language', $language);
        FacadesSession::save();
        app()->setLocale($request->lang);
        Artisan::call('cache:clear');

        return redirect()->back();
    }

    public function downloadPanelFile()
    {

        $file = base_path('resources/lang/en.json');
        $filename = 'admin_panel_en.json';

        return Response::download($file, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function downloadAppFile()
    {
        $file = public_path('languages/en.json');
        $filename = 'app_en.json';

        return Response::download($file, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function downloadWebFile()
    {
        $file = public_path('web_languages/en.json');

        $filename = 'web_en.json';

        return Response::download($file, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function updateStatus(Request $request)
    {
        if (! has_permissions('update', 'language')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:languages,id',
                'status' => 'required|in:0,1',
            ]);
            if ($validator->fails()) {
                return ResponseService::errorResponse($validator->errors()->first());
            }
            $language = Language::find($request->id);
            if ($request->status == 0) {
                $checkStatusOFAllLanguages = Language::where('status', 1)->whereNotIn('id', [$request->id])->count();
                if ($checkStatusOFAllLanguages == 0) {
                    return response()->json([
                        'error' => true,
                        'message' => trans('At least one language must be active'),
                    ]);
                }

                $defaultLanguageCode = HelperService::getSettingData('default_language');
                if ($defaultLanguageCode == $language->getRawOriginal('code')) {
                    return response()->json([
                        'error' => true,
                        'message' => trans('Default language cannot be deactivated'),
                    ]);
                }

            }
            $language->status = $request->status;
            $language->save();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Issue in update language status');
        }
    }
}
