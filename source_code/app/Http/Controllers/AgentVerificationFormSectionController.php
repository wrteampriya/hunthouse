<?php

namespace App\Http\Controllers;

use App\Models\AgentVerificationForm;
use App\Models\AgentVerificationFormSection;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AgentVerificationFormSectionController extends Controller
{
    public function index(Request $request)
    {
        if (! has_permissions('read', 'verify_customer_form')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $languages = HelperService::getActiveLanguages();
        $form_type = $request->form_type;

        return view('agent-verification-form.sections', compact('languages', 'form_type'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'form_type' => 'required|in:become_agent,verify_agent',
            'sequence' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $section = AgentVerificationFormSection::create([
                'name' => strip_tags($request->name),
                'form_type' => $request->form_type,
                'sequence' => $request->sequence ?? 0,
            ]);

            // Add Translations
            if (isset($request->section_translations) && ! empty($request->section_translations)) {
                $translationData = [];
                foreach ($request->section_translations as $translation) {
                    if (! empty($translation['value'])) {
                        $translationData[] = [
                            'translatable_id' => $section->id,
                            'translatable_type' => 'App\Models\AgentVerificationFormSection',
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
        if (! has_permissions('read', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'sequence');
        $order = request('order', 'ASC');
        $search = request('search');
        $filter_form_type = request('filter_form_type');

        $sql = AgentVerificationFormSection::with('translations')
            ->with(['agent_verification_forms' => function ($q) {
                $q->orderBy('sequence', 'ASC')->select('id', 'agent_verification_form_section_id', 'name', 'field_type', 'sequence');
            }])
            ->when($filter_form_type, function ($query) use ($filter_form_type) {
                $query->where('form_type', $filter_form_type);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orWhere('name', 'LIKE', "%$search%")
                        ->orWhere('form_type', 'LIKE', "%$search%");
                });
            });

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];

        foreach ($res as $row) {
            $row = (object) $row;

            $operate = '';
            if (has_permissions('update', 'verify_customer_form')) {
                $operate = BootstrapTableService::editButton('', true, null, null, $row->id, null);
                $operate .= BootstrapTableService::button('bi bi-arrow-down-up ', route('agent-verification-form-sections.reorder-fields', $row->id), ['btn', 'icon', 'btn-sm', 'rounded-pill', 'text-info', 'bg-light-info', 'reorder-button', 'border', 'border-info'], ['title' => trans('Reorder Fields')]);
            }
            if (has_permissions('delete', 'verify_customer_form')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('agent-verification-form-sections.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            if (has_permissions('update', 'verify_customer_form')) {
                $tempRow['edit_status_url'] = route('agent-verification-form-sections.status');
            } else {
                $tempRow['edit_status_url'] = null;
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function update(Request $request)
    {
        if (! has_permissions('update', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:agent_verification_form_sections,id',
            'name' => 'required',
            'form_type' => 'required|in:become_agent,verify_agent',
            'sequence' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $section = AgentVerificationFormSection::find($request->id);
            $section->name = strip_tags($request->name);
            $section->form_type = $request->form_type;
            $section->sequence = $request->sequence ?? 0;
            $section->save();

            // Handle section translations
            if (isset($request->section_translations) && ! empty($request->section_translations)) {
                $translationData = [];
                foreach ($request->section_translations as $translation) {
                    if (! empty($translation['value'])) {
                        $translationData[] = [
                            'id' => $translation['id'] ?? null,
                            'translatable_id' => $section->id,
                            'translatable_type' => 'App\Models\AgentVerificationFormSection',
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
        if (! has_permissions('update', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $status = $request->status == '1' ? 'active' : 'inactive';
            AgentVerificationFormSection::where('id', $request->id)->update(['status' => $status]);
            ResponseService::successResponse($request->status ? trans('Field Activated Successfully') : trans('Field Deactivated Successfully'));
        }
    }

    public function destroy($id)
    {
        if (! has_permissions('delete', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:agent_verification_form_sections,id',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $section = AgentVerificationFormSection::find($id);
            $section->delete();

            DB::commit();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function reorderFields($id)
    {
        if (! has_permissions('update', 'verify_customer_form')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $section = AgentVerificationFormSection::with(['agent_verification_forms' => function ($q) {
            $q->orderBy('sequence', 'ASC');
        }])->findOrFail($id);

        return view('agent-verification-form.reorder-fields', compact('section'));
    }

    public function saveFieldOrder(Request $request)
    {
        if (! has_permissions('update', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'fields' => 'required|array',
            'fields.*.id' => 'required|exists:agent_verification_forms,id',
            'fields.*.sort_order' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            foreach ($request->fields as $field) {
                AgentVerificationForm::where('id', $field['id'])->update(['sequence' => $field['sort_order']]);
            }
            DB::commit();
            ResponseService::successResponse(trans('Field Order Updated Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function updateSequence(Request $request)
    {
        if (! has_permissions('update', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:agent_verification_form_sections,id',
            'sections.*.sort_order' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            foreach ($request->sections as $section) {
                AgentVerificationFormSection::where('id', $section['id'])
                    ->update(['sequence' => $section['sort_order']]);
            }

            DB::commit();
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }
}
