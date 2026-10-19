<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Translation;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackageFeatureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! has_permissions('read', 'package-feature')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $type = $request->input('user_type');

        return view('features.index', compact('type'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! has_permissions('create', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            $type = $request->input('user_type', 'user');
            Feature::create(array_merge($request->only('name'), ['user_type' => $type]));
            ResponseService::successResponse(trans('Data Created Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Error in Package Feature Store Controller', trans('Something Went Wrong'));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (! has_permissions('read', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');
        $search = request('search');

        $sql = Feature::when($search, function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")
                    ->orWhere('name', 'LIKE', "%$search%");
            });
        });

        if (request()->has('user_type') && ! empty(request('user_type'))) {
            $userType = request('user_type');
            if ($userType == 'user') {
                $sql->whereIn('user_type', ['user', 'all']);
            } elseif ($userType == 'agent') {
                $sql->whereIn('user_type', ['agent', 'all']);
            } else {
                $sql->where('user_type', $userType);
            }
        }

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $no = 1;
        foreach ($res as $row) {
            $row = (object) $row;
            $translationUrl = route('package-features.translated-names', $row->id);
            $operate = BootstrapTableService::button('fa fa-language', $translationUrl, ['btn-primary'], ['title' => __('Manage Translations')]);

            $tempRow = $row->toArray();
            $tempRow['edit_status_url'] = route('package-features.status-update');
            $tempRow['name'] = trans($tempRow['name']);
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (! has_permissions('update', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Feature::where('id', $id)->update($request->only('name'));
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Error in Package Feature Update Controller', trans('Something Went Wrong'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (! has_permissions('delete', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Feature::where('id', $id)->delete();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Error in Package Feature Delete Controller', trans('Something Went Wrong'));
        }
    }

    /**
     * Update status of specified resource.
     */
    public function updateStatus(Request $request)
    {
        if (! has_permissions('update', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Feature::where('id', $request->id)->update(['status' => $request->status == 1 ? true : false]);
            ResponseService::successResponse(trans('Status Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Error in Package Feature Update Controller', trans('Something Went Wrong'));
        }
    }

    public function translatedNames(string $id)
    {
        if (! has_permissions('read', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $feature = Feature::find($id);
        $languages = HelperService::getActiveLanguages();
        $featureTranslations = Translation::where('translatable_id', $id)->where('translatable_type', 'App\Models\Feature')->get();
        $type = request('user_type');

        return view('features.translated-names', compact('feature', 'languages', 'featureTranslations', 'type'));
    }

    public function updateTranslatedNames(Request $request)
    {
        if (! has_permissions('update', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            $validator = Validator::make($request->all(), [
                'feature_id' => 'required|exists:features,id',
                'translations' => 'required|array',
                'translations.*.language_id' => 'required|exists:languages,id',
                'translations.*.value' => 'required|string',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            // Add Translations
            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    $translationData[] = [
                        'id' => $translation['id'] ?? null,
                        'translatable_id' => $request->feature_id,
                        'translatable_type' => 'App\Models\Feature',
                        'key' => 'name',
                        'value' => $translation['value'],
                        'language_id' => $translation['language_id'],
                    ];
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Error in Package Feature Update Translated Names Controller', trans('Something Went Wrong'));
        }
    }
}
