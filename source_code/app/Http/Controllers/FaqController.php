<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FaqController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (! has_permissions('read', 'faqs')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $languages = HelperService::getActiveLanguages();

            return view('faqs.index', compact('languages'));
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! has_permissions('create', 'faqs')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'question' => 'required',
            'answer' => 'required',
            'user_type' => 'required|in:user,agent',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            // Add Faq data to database
            $faq = Faq::create(['question' => $request->question, 'answer' => $request->answer, 'user_type' => $request->user_type]);

            // START ::Add Translations
            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        $translationData[] = [
                            'translatable_id' => $faq->id,
                            'translatable_type' => 'App\Models\Faq',
                            'language_id' => $value['language_id'],
                            'key' => $key,
                            'value' => $value['value'],
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            // END ::Add Translations
            DB::commit();
            ResponseService::successResponse(trans('Data Created Successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $userType = request('user_type', null);

        $sql = Faq::with('translations')
            ->when($userType, function ($query) use ($userType) {
                $query->where('user_type', $userType);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orWhere('question', 'LIKE', "%$search%")
                        ->orWhere('answer', 'LIKE', "%$search%");
                });
            });

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $no = 1;
        foreach ($res as $row) {
            $row = (object) $row;
            $operate = '';
            if (has_permissions('update', 'faqs')) {
                $operate .= BootstrapTableService::editButton('', true, null, null, null, null);
            }
            if (has_permissions('delete', 'faqs')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('faqs.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            if (has_permissions('update', 'faqs')) {
                $tempRow['edit_status_url'] = route('faqs.status-update');
            } else {
                $tempRow['edit_status_url'] = null;
            }
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
        if (! has_permissions('update', 'faqs')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'edit_question' => 'required',
            'edit_answer' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            Faq::where('id', $id)->update(['question' => $request->edit_question, 'answer' => $request->edit_answer, 'user_type' => $request->edit_user_type ?? 'user']);

            // START ::Add Translations
            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        $translationData[] = [
                            'id' => $value['id'],
                            'translatable_id' => $id,
                            'translatable_type' => 'App\Models\Faq',
                            'language_id' => $value['language_id'],
                            'key' => $key,
                            'value' => $value['value'],
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (! has_permissions('delete', 'faqs')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Faq::where('id', $id)->delete();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function statusUpdate(Request $request)
    {
        if (! has_permissions('update', 'faqs')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'status' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            Faq::where('id', $request->id)->update(['status' => $request->status]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }
}
