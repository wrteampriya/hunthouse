<?php

namespace App\Http\Controllers;

use App\Models\SocialMediaLink;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SocialMediaLinkController extends Controller
{
    public function index()
    {
        if (! has_permissions('read', 'social_media_link')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $languages = HelperService::getActiveLanguages();

        return view('social-media-link.index', compact('languages'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'social_media_link')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'icon' => 'required|image|mimes:svg,png,jpg,jpeg,webp|max:2048',
            'sequence' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $link = new SocialMediaLink;
            $link->name = strip_tags($request->name);
            $link->sequence = $request->sequence ?? 0;
            if ($request->hasFile('icon')) {
                $link->icon = FileService::compressAndUpload($request->file('icon'), config('global.SOCIAL_MEDIA_ICON_PATH'));
            }
            $link->save();

            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    if (! empty($translation['value'])) {
                        $translationData[] = [
                            'translatable_id' => $link->id,
                            'translatable_type' => SocialMediaLink::class,
                            'key' => 'name',
                            'value' => strip_tags($translation['value']),
                            'language_id' => $translation['language_id'],
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            DB::commit();
            ResponseService::successResponse(trans('Data Created Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function show()
    {
        if (! has_permissions('read', 'social_media_link')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'sequence');
        $order = request('order', 'ASC');
        $search = request('search');

        $sql = SocialMediaLink::with('translations')->withCount('values')
            ->when($search, function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")->orWhere('name', 'LIKE', "%$search%");
            });

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $rows = [];
        foreach ($res as $row) {
            $operate = '';
            if (has_permissions('update', 'social_media_link')) {
                $operate = BootstrapTableService::editButton('', true, null, null, $row->id, null);
            }
            if (has_permissions('delete', 'social_media_link') && $row->values_count === 0) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('social-media-links.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['edit_status_url'] = has_permissions('update', 'social_media_link')
                ? route('social-media-links.status')
                : null;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function update(Request $request)
    {
        if (! has_permissions('update', 'social_media_link')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:social_media_links,id',
            'name' => 'required',
            'icon' => 'nullable|image|mimes:svg,png,jpg,jpeg,webp|max:2048',
            'sequence' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $link = SocialMediaLink::find($request->id);
            $link->name = strip_tags($request->name);
            $link->sequence = $request->sequence ?? 0;
            if ($request->hasFile('icon')) {
                $link->icon = FileService::compressAndReplace(
                    $request->file('icon'),
                    config('global.SOCIAL_MEDIA_ICON_PATH'),
                    $link->getRawOriginal('icon')
                );
            }
            $link->save();

            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    if (! empty($translation['value'])) {
                        $translationData[] = [
                            'id' => $translation['id'] ?? null,
                            'translatable_id' => $link->id,
                            'translatable_type' => SocialMediaLink::class,
                            'key' => 'name',
                            'value' => strip_tags($translation['value']),
                            'language_id' => $translation['language_id'],
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            DB::commit();
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function status(Request $request)
    {
        if (! has_permissions('update', 'social_media_link')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $status = $request->status == '1' ? 'active' : 'inactive';
        SocialMediaLink::where('id', $request->id)->update(['status' => $status]);
        ResponseService::successResponse($request->status ? trans('Field Activated Successfully') : trans('Field Deactivated Successfully'));
    }

    public function destroy($id)
    {
        if (! has_permissions('delete', 'social_media_link')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:social_media_links,id',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $link = SocialMediaLink::find($id);

            if ($link->values()->exists()) {
                DB::rollback();
                ResponseService::errorResponse(trans('This field cannot be deleted as one or more agents have already added a value for it.'));
            }

            $rawIcon = $link->getRawOriginal('icon');
            $link->values()->delete();
            $link->delete();

            if (! empty($rawIcon)) {
                FileService::delete(config('global.SOCIAL_MEDIA_ICON_PATH'), $rawIcon);
            }

            DB::commit();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }
}
