<?php

namespace App\Http\Controllers;

use App\Models\AgentVerificationForm;
use App\Models\AgentVerificationFormSection;
use App\Models\AgentVerificationFormValue;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AgentVerificationFormController extends Controller
{
    public function index(Request $request)
    {
        if (! has_permissions('read', 'verify_customer_form')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $languages = HelperService::getActiveLanguages();
        $form_type = $request->form_type;
        $sections = AgentVerificationFormSection::where('status', 'active')
            ->when($form_type, function ($query) use ($form_type) {
                return $query->where('form_type', $form_type);
            })
            ->get();

        return view('agent-verification-form.fields', compact('languages', 'sections', 'form_type'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'agent_verification_form_section_id' => 'required|exists:agent_verification_form_sections,id',
            'name' => 'required',
            'field_type' => 'required|in:text,number,radio,checkbox,textarea,file,dropdown',
            'sequence' => 'nullable|integer',
            'option_data.*' => 'required_if:field_type,radio|required_if:field_type,checkbox|required_if:field_type,dropdown',
            'option_data.*.option' => 'nullable|not_regex:/,/',
        ], [
            'option_data.*.option.not_regex' => 'Option value cannot contain comma',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            // Store form
            $form = AgentVerificationForm::create([
                'agent_verification_form_section_id' => $request->agent_verification_form_section_id,
                'name' => strip_tags($request->name),
                'field_type' => $request->field_type,
                'sequence' => $request->sequence ?? 0,
            ]);

            // Check if option data is available or not
            if ($request->has('option_data') && ! empty($request->option_data)) {
                foreach ($request->option_data as $optionIndex => $option) {
                    if (! empty($option['option'])) {
                        $formValue = AgentVerificationFormValue::create([
                            'agent_verification_form_id' => $form->id,
                            'value' => strip_tags($option['option']),
                        ]);

                        // Handle translations
                        $optionTranslationData = [];
                        foreach ($option as $key => $value) {
                            if (str_starts_with($key, 'translation_language_id_')) {
                                $languageId = str_replace('translation_language_id_', '', $key);
                                $translationKey = 'translation_value_'.$languageId;

                                if (isset($option[$translationKey]) && ! empty($option[$translationKey])) {
                                    $optionTranslationData[] = [
                                        'id' => null,
                                        'translatable_id' => $formValue->id,
                                        'translatable_type' => 'App\Models\AgentVerificationFormValue',
                                        'key' => 'value',
                                        'value' => strip_tags($option[$translationKey]),
                                        'language_id' => $languageId,
                                    ];
                                }
                            }
                        }

                        if (! empty($optionTranslationData)) {
                            HelperService::storeTranslations($optionTranslationData);
                        }
                    }
                }
            }

            // Add Translations
            if (isset($request->field_translations) && ! empty($request->field_translations)) {
                $translationData = [];
                foreach ($request->field_translations as $translation) {
                    if (! empty($translation['value'])) {
                        $translationData[] = [
                            'translatable_id' => $form->id,
                            'translatable_type' => 'App\Models\AgentVerificationForm',
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
        $filter_section_id = request('filter_section_id');
        $filter_form_type = request('form_type');

        $sql = AgentVerificationForm::with('form_fields_values.translations', 'translations', 'agent_verification_form_section')
            ->when($filter_section_id, function ($query) use ($filter_section_id) {
                $query->where('agent_verification_form_section_id', $filter_section_id);
            })
            ->when($filter_form_type, function ($query) use ($filter_form_type) {
                $query->whereHas('agent_verification_form_section', function ($q) use ($filter_form_type) {
                    $q->where('form_type', $filter_form_type);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orWhere('name', 'LIKE', "%$search%")
                        ->orWhere('field_type', 'LIKE', "%$search%")
                        ->orWhereHas('agent_verification_form_section', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%")->orWhere('form_type', 'LIKE', "%$search%");
                        })
                        ->orWhereHas('form_fields_values', function ($query) use ($search) {
                            $query->where('value', 'LIKE', "%$search%");
                        });
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
            }
            if (has_permissions('delete', 'verify_customer_form')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('agent-verification-form-fields.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            if (has_permissions('update', 'verify_customer_form')) {
                $tempRow['edit_status_url'] = route('agent-verification-form-fields.status');
            } else {
                $tempRow['edit_status_url'] = null;
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function status(Request $request)
    {
        if (! has_permissions('update', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            if ($request->status == '1') {
                $status = 'active';
            } else {
                $status = 'inactive';
            }
            AgentVerificationForm::where('id', $request->id)->update(['status' => $status]);
            ResponseService::successResponse($request->status ? trans('Field Activated Successfully') : trans('Field Deactivated Successfully'));
        }
    }

    public function update(Request $request)
    {
        if (! has_permissions('update', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:agent_verification_forms,id',
            'agent_verification_form_section_id' => 'required|exists:agent_verification_form_sections,id',
            'name' => 'required',
            'sequence' => 'nullable|integer',
            'option_data.*.edit_option' => 'nullable|not_regex:/,/',
        ], [
            'option_data.*.edit_option.not_regex' => 'Option value cannot contain comma',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $form = AgentVerificationForm::find($request->id);
            $form->name = strip_tags($request->name);
            $form->agent_verification_form_section_id = $request->agent_verification_form_section_id;
            $form->sequence = $request->sequence ?? 0;
            $form->save();

            $optionTranslationData = [];
            // Handle options update if they exist
            if ($request->has('option_data') && ! empty($request->option_data)) {
                $updatedOptionIds = [];

                foreach ($request->option_data as $option) {
                    if (! empty($option['edit_option'])) {
                        if (! empty($option['edit_option_id'])) {
                            // Update existing option
                            $formValue = AgentVerificationFormValue::find($option['edit_option_id']);
                            if ($formValue) {
                                $formValue->value = strip_tags($option['edit_option']);
                                $formValue->save();
                                $updatedOptionIds[] = $option['edit_option_id'];
                            }
                        } else {
                            // Create new option
                            $formValue = AgentVerificationFormValue::create([
                                'agent_verification_form_id' => $request->id,
                                'value' => strip_tags($option['edit_option']),
                            ]);
                            $updatedOptionIds[] = $formValue->id;
                        }

                        // Handle option translations
                        if ($formValue) {
                            foreach ($option as $key => $value) {
                                if (str_starts_with($key, 'edit_translation_language_id_')) {
                                    $languageId = str_replace('edit_translation_language_id_', '', $key);
                                    $translationValueKey = 'edit_translation_value_'.$languageId;
                                    $translationIdKey = 'edit_translation_id_'.$languageId;

                                    if (isset($option[$translationValueKey]) && ! empty($option[$translationValueKey])) {
                                        $optionTranslationData[] = [
                                            'id' => $option[$translationIdKey] ?? null,
                                            'translatable_id' => $formValue->id,
                                            'translatable_type' => 'App\Models\AgentVerificationFormValue',
                                            'key' => 'value',
                                            'value' => strip_tags($option[$translationValueKey]),
                                            'language_id' => $languageId,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }

                if (! empty($optionTranslationData)) {
                    HelperService::storeTranslations($optionTranslationData);
                }

                // Delete options that were removed
                $idsToDelete = AgentVerificationFormValue::where('agent_verification_form_id', $request->id)
                    ->whereNotIn('id', $updatedOptionIds)
                    ->pluck('id');

                if ($idsToDelete->isNotEmpty()) {
                    AgentVerificationFormValue::destroy($idsToDelete);
                }
            }

            // Handle field translations
            if (isset($request->field_translations) && ! empty($request->field_translations)) {
                $translationData = [];
                foreach ($request->field_translations as $translation) {
                    if (! empty($translation['value'])) {
                        $translationData[] = [
                            'id' => $translation['id'] ?? null,
                            'translatable_id' => $request->id,
                            'translatable_type' => 'App\Models\AgentVerificationForm',
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

    public function destroy($id)
    {
        if (! has_permissions('delete', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:agent_verification_forms,id',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            $form = AgentVerificationForm::find($id);
            $form->form_fields_values()->get()->each(function ($formValue) {
                $formValue->delete();
            });
            $form->delete();
            DB::commit();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
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
                AgentVerificationForm::where('id', $field['id'])
                    ->update(['sequence' => $field['sort_order']]);
            }

            DB::commit();
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }
}
