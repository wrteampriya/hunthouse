<?php

namespace App\Http\Controllers;

use App\Models\AgentProfile;
use App\Models\AgentVerification;
use App\Models\Notifications;
use App\Models\RejectReason;
use App\Models\Setting;
use App\Models\Usertokens;
use App\Models\VerifyCustomer;
use App\Models\VerifyCustomerForm;
use App\Models\VerifyCustomerFormValue;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VerifyCustomerFormController extends Controller
{
    public function verifyCustomerFormIndex()
    {
        if (! has_permissions('read', 'verify_customer_form')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $languages = HelperService::getActiveLanguages();

        return view('verify-customer-form.verify_customer_form', compact('languages'));
    }

    public function verifyCustomerFormStore(Request $request)
    {
        if (! has_permissions('create', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'field_type' => 'required|in:text,number,radio,checkbox,textarea,file,dropdown',
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
            // Get the data from Request
            $name = $request->name;
            $fieldType = $request->field_type;

            // Store name and field type in verify customer form
            $verifyCustomerForm = VerifyCustomerForm::create(['name' => strip_tags($name), 'field_type' => $fieldType]);

            // Check if option data is available or not
            if ($request->has('option_data') && ! empty($request->option_data)) {
                foreach ($request->option_data as $optionIndex => $option) {
                    // Remove the dd($option); line first!
                    if (! empty($option['option'])) {
                        $verifyCustomerFormValue = VerifyCustomerFormValue::create([
                            'verify_customer_form_id' => $verifyCustomerForm->id,
                            'value' => strip_tags($option['option']),
                        ]);

                        // Handle translations with the new flat structure
                        $optionTranslationData = [];

                        // Loop through the option array to find translation data
                        foreach ($option as $key => $value) {
                            // Check if this is a translation language ID
                            if (str_starts_with($key, 'translation_language_id_')) {
                                $languageId = str_replace('translation_language_id_', '', $key);
                                $translationKey = 'translation_value_'.$languageId;

                                // Check if corresponding translation value exists
                                if (isset($option[$translationKey]) && ! empty($option[$translationKey])) {
                                    $optionTranslationData[] = [
                                        'id' => null,
                                        'translatable_id' => $verifyCustomerFormValue->id,
                                        'translatable_type' => 'App\Models\VerifyCustomerFormValue',
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
                            'translatable_id' => $verifyCustomerForm->id,
                            'translatable_type' => 'App\Models\VerifyCustomerForm',
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

    /**
     * Display the specified resource.
     */
    public function verifyCustomerFormShow()
    {
        if (! has_permissions('read', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = VerifyCustomerForm::with('form_fields_values.translations', 'translations')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orWhere('name', 'LIKE', "%$search%")
                        ->orWhere('field_type', 'LIKE', "%$search%")
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
        $no = 1;
        foreach ($res as $row) {
            $row = (object) $row;

            $operate = '';
            if (has_permissions('update', 'verify_customer_form')) {
                $operate = BootstrapTableService::editButton('', true, null, null, $row->id, null);
            }
            if (has_permissions('delete', 'verify_customer_form')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('verify-customer-form.delete', $row->id));
            }

            $tempRow = $row->toArray();
            if (has_permissions('update', 'verify_customer_form')) {
                $tempRow['edit_status_url'] = route('verify-customer-form.status');
            } else {
                $tempRow['edit_status_url'] = null;
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function verifyCustomerFormStatus(Request $request)
    {
        if (! has_permissions('update', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            if ($request->status == '1') {
                $status = 'active';
            } else {
                $status = 'inactive';
            }
            VerifyCustomerForm::where('id', $request->id)->update(['status' => $status]);
            ResponseService::successResponse($request->status ? trans('Field Activated Successfully') : trans('Field Deactivated Successfully'));
        }
    }

    public function verifyCustomerFormUpdate(Request $request)
    {
        if (! has_permissions('update', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'id' => 'required|exists:verify_customer_forms,id',
            'option_data.*.edit_option' => 'nullable|not_regex:/,/',
        ], [
            'option_data.*.edit_option.not_regex' => 'Option value cannot contain comma',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            // Update form field name
            $verifyCustomerForm = VerifyCustomerForm::find($request->id);
            $verifyCustomerForm->name = strip_tags($request->name);
            $verifyCustomerForm->save();

            $optionTranslationData = [];
            // Handle options update if they exist
            if ($request->has('option_data') && ! empty($request->option_data)) {
                $updatedOptionIds = [];

                foreach ($request->option_data as $option) {
                    if (! empty($option['edit_option'])) {
                        if (! empty($option['edit_option_id'])) {
                            // Update existing option
                            $verifyCustomerFormValue = VerifyCustomerFormValue::find($option['edit_option_id']);
                            if ($verifyCustomerFormValue) {
                                $verifyCustomerFormValue->value = strip_tags($option['edit_option']);
                                $verifyCustomerFormValue->save();
                                $updatedOptionIds[] = $option['edit_option_id'];
                            }
                        } else {
                            // Create new option
                            $verifyCustomerFormValue = VerifyCustomerFormValue::create([
                                'verify_customer_form_id' => $request->id,
                                'value' => strip_tags($option['edit_option']),
                            ]);
                            $updatedOptionIds[] = $verifyCustomerFormValue->id;
                        }

                        // Handle option translations
                        if ($verifyCustomerFormValue) {
                            foreach ($option as $key => $value) {
                                if (str_starts_with($key, 'edit_translation_language_id_')) {
                                    $languageId = str_replace('edit_translation_language_id_', '', $key);
                                    $translationValueKey = 'edit_translation_value_'.$languageId;
                                    $translationIdKey = 'edit_translation_id_'.$languageId;

                                    if (isset($option[$translationValueKey]) && ! empty($option[$translationValueKey])) {
                                        $optionTranslationData[] = [
                                            'id' => $option[$translationIdKey] ?? null,
                                            'translatable_id' => $verifyCustomerFormValue->id,
                                            'translatable_type' => 'App\Models\VerifyCustomerFormValue',
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
                // Get IDs of records to delete
                $idsToDelete = VerifyCustomerFormValue::where('verify_customer_form_id', $request->id)
                    ->whereNotIn('id', $updatedOptionIds)
                    ->pluck('id');

                if ($idsToDelete->isNotEmpty()) {
                    VerifyCustomerFormValue::destroy($idsToDelete); // This triggers model events
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
                            'translatable_type' => 'App\Models\VerifyCustomerForm',
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

    public function verifyCustomerFormDestroy($id)
    {
        if (! has_permissions('delete', 'verify_customer_form')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:verify_customer_forms,id',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            $form = VerifyCustomerForm::find($id);
            $form->form_fields_values()->get()->each(function ($formValue) {
                $formValue->delete(); // This triggers the deleting event
            });
            $form->delete();
            DB::commit();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function agentVerificationListIndex(Request $request)
    {
        if (! has_permissions('read', 'approve_agent_verification')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $form_type = $request->form_type ?? 'become_agent';

        return view('agent-verification-form.agent_verification_list', compact('form_type'));
    }

    public function agentVerificationList(Request $request)
    {
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';
        $search = $request->search;
        $form_type = $request->form_type ?? 'become_agent';

        $sql = AgentVerification::with(['user' => function ($query) {
            $query->select('id', 'name', 'profile')->withCount(['property', 'projects']);
        }])->with('values')
            ->with('values.verify_form:id,name,field_type')
            ->with('values.verify_form.form_fields_values:id,agent_verification_form_id,value');

        if (! empty($form_type)) {
            $sql->where('form_type', $form_type);
        }

        if (! empty($search)) {
            $sql = $sql->where(function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")
                    ->orWhere('status', 'LIKE', "%$search%")
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('id', 'LIKE', "%$search%")->orWhere('name', 'LIKE', "%$search%");
                    });
            });
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
            $tempRow = $row->toArray();

            $operate = '';
            if (has_permissions('update', 'approve_agent_verification')) {
                $operate = BootstrapTableService::editButton('', true, null, null, $row->id, null);
            }
            $tempRow['operate'] = $operate;

            $viewFormClasses = ['btn', 'icon', 'btn-primary', 'btn-sm', 'rounded-pill', 'view-form-btn'];
            $viewFormAttributes = ['id' => $row->id, 'title' => trans('Submitted Form Values')];
            $viewFormButton = BootstrapTableService::button('bi bi-eye-fill ml-2', route('agent-verification.show-form', $row->id), $viewFormClasses, $viewFormAttributes);
            $tempRow['raw_view_form_btn'] = $viewFormButton;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function getAgentSubmittedForm($id)
    {
        // Validate the ID
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:agent_verifications,id',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }
        if (! has_permissions('read', 'verify_customer_form')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $customerVerification = AgentVerification::with('user:id,name,profile')
            ->with('values')
            ->with('values.verify_form')
            ->with('values.verify_form.translations')
            ->with('values.verify_form.form_fields_values')
            ->with('values.verify_form.form_fields_values.translations')
            ->with('values.verify_form.agent_verification_form_section')
            ->findOrFail($id);

        // Process file type based on value
        foreach ($customerVerification->values as $value) {
            if ($value->verify_form->field_type == 'file') {
                $value->file_type = $this->getFileType($value->value);
            } else {
                $value->file_type = 'other';
            }
        }

        // Group values by section
        $groupedValues = $customerVerification->values->groupBy(function ($value) {
            return $value->verify_form->agent_verification_form_section->id ?? 0;
        });

        // Map section names
        $sections = [];
        foreach ($groupedValues as $sectionId => $values) {
            $sectionName = $values->first()->verify_form->agent_verification_form_section->name ?? __('Other');
            $sections[] = [
                'name' => $sectionName,
                'values' => $values,
            ];
        }

        return view('verify-customer-form.agent-form-details', compact('customerVerification', 'sections'));
    }

    public function updateVerificationStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'edit_id' => 'required',
            'edit_status' => 'required|in:approved,rejected',
            'reject_reason' => 'required_if:edit_status,rejected|max:300',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            Log::info('Updating verification status for ID: '.$request->edit_id.' to status: '.$request->edit_status);

            $verifyCustomerData = AgentVerification::with('user')->findOrFail($request->edit_id);
            $verifyCustomerData->status = $request->edit_status;
            $verifyCustomerData->save();

            Log::info('Record updated. Form type: '.$verifyCustomerData->form_type);

            if ($request->edit_status == 'approved') {
                $statusText = 'Approved';

                // Update user flags based on form_type
                if ($verifyCustomerData->form_type == 'become_agent') {
                    $title = 'Become Agent Request Approved';
                    $translatedMessage = 'Congratulations! Your request to become an agent has been approved.';
                    Log::info('Setting is_agent = 1 for user: '.$verifyCustomerData->customer_id);
                    $verifyCustomerData->user->update(['is_agent' => 1]);

                    // Sync customer data to agent_profile
                    AgentProfile::updateOrCreate(
                        ['customer_id' => $verifyCustomerData->customer_id],
                        [
                            'agent_name' => $verifyCustomerData->user->name ?? '',
                            'agent_email' => $verifyCustomerData->user->email ?? '',
                            'agent_mobile' => $verifyCustomerData->user->mobile ?? '',
                            'agent_country_code' => $verifyCustomerData->user->country_code ?? '',
                            'agent_address' => $verifyCustomerData->user->address ?? '',
                            'agent_profile_photo' => $verifyCustomerData->user->getRawOriginal('profile') ?? null,
                        ]
                    );
                } else {
                    $title = 'Agent Verification Approved';
                    $translatedMessage = 'Your agent verification request has been approved.';
                    Log::info('Setting is_agent_verified = 1 for user: '.$verifyCustomerData->customer_id);
                    $verifyCustomerData->user->update(['is_agent_verified' => 1]);
                }
            } else {
                $statusText = 'Rejected';

                // Save Reject Reason
                RejectReason::updateOrCreate([
                    'agent_verification_id' => $verifyCustomerData->id,
                ], [
                    'reason' => $request->reject_reason,
                ]);

                // Revert user flags if rejected
                if ($verifyCustomerData->form_type == 'become_agent') {
                    $title = 'Become Agent Request Rejected';
                    $translatedMessage = 'Your request to become an agent has been rejected. Reason: '.$request->reject_reason;
                    Log::info('Setting is_agent = 0 for user: '.$verifyCustomerData->customer_id);
                    $verifyCustomerData->user->update(['is_agent' => 0]);
                } else {
                    $title = 'Agent Verification Rejected';
                    $translatedMessage = 'Your agent verification request has been rejected. Reason: '.$request->reject_reason;
                    Log::info('Setting is_agent_verified = 0 for user: '.$verifyCustomerData->customer_id);
                    $verifyCustomerData->user->update(['is_agent_verified' => 0]);
                }
            }

            if ($verifyCustomerData->user->notification == 1) {
                $user_token = Usertokens::where('customer_id', $verifyCustomerData->user->id)->pluck('fcm_id')->toArray();
                // dd($user_token);
                // START :: Send Notification To Customer
                if (! empty($user_token)) {
                    $registrationIDs = $user_token;
                    $message = $translatedMessage;
                    $fcmMsg = [
                        'title' => $title,
                        'message' => $message,
                        'type' => 'agent_verification',
                        'body' => $message,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'id' => (string) $verifyCustomerData->id,
                        'role_context' => 'agent',
                    ];
                    send_push_notification($registrationIDs, $fcmMsg);
                }
                // END ::  Send Notification To Customer

                Notifications::create([
                    'title' => $title,
                    'message' => $translatedMessage,
                    'image' => '',
                    'type' => '1',
                    'send_type' => '0',
                    'customers_id' => $verifyCustomerData->customer_id,
                ]);
            }

            // Send mail for agent verification status
            try {
                // $verifyCustomerData
                if ($verifyCustomerData->user->email) {
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes('agent_verification_status');

                    // Email Template
                    $agentVerificationTemplateData = system_setting($emailTypeData['type']);
                    $appName = env('APP_NAME') ?? 'eBroker';
                    $variables = [
                        'app_name' => $appName,
                        'user_name' => $verifyCustomerData->user->name,
                        'status' => $statusText,
                        'reject_reason' => $request->edit_status == 'rejected' ? $request->reject_reason : null,
                        'email' => $verifyCustomerData->user->email,
                    ];
                    if (empty($agentVerificationTemplateData)) {
                        $agentVerificationTemplateData = 'Your Agent Verification Status is '.$variables['status'];
                    }
                    $agentVerificationTemplate = HelperService::replaceEmailVariables($agentVerificationTemplateData, $variables);

                    $data = [
                        'email_template' => $agentVerificationTemplate,
                        'email' => $verifyCustomerData->user->email,
                        'title' => $emailTypeData['title'],
                    ];
                    HelperService::sendMail($data);
                }
            } catch (Exception $e) {
                Log::error('Something Went Wrong in Agent Verification Status Mail Sending');
            }
            DB::commit();
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error in updateVerificationStatus: '.$e->getMessage());
            Log::error($e->getTraceAsString());
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function agentAutoApproveSettings(Request $request)
    {
        if (! has_permissions('update', 'approve_agent_verification')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Setting::updateOrCreate(['type' => 'agent_auto_approve'], ['data' => $request->auto_approve]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function userAutoApproveSettings(Request $request)
    {
        if (! has_permissions('update', 'approve_agent_verification')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Setting::updateOrCreate(['type' => 'auto_approve'], ['data' => $request->auto_approve]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function verificationRequiredForAgentSettings(Request $request)
    {
        if (! has_permissions('update', 'approve_agent_verification')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Setting::updateOrCreate(['type' => 'verification_required_for_agent'], ['data' => $request->verification_required_for_agent]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function verificationRequiredForUserSettings(Request $request)
    {
        if (! has_permissions('update', 'approve_agent_verification')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Setting::updateOrCreate(['type' => 'verification_required_for_user'], ['data' => $request->verification_required_for_user]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    private function getFileType($filePath)
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $imageExtensions = ['jpg', 'jpeg', 'png'];
        $pdfExtensions = ['pdf'];
        $docExtensions = ['doc', 'docx'];
        $textExtensions = ['txt'];

        if (in_array($extension, $imageExtensions)) {
            return 'image';
        } elseif (in_array($extension, $pdfExtensions)) {
            return 'pdf';
        } elseif (in_array($extension, $docExtensions)) {
            return 'doc';
        } elseif (in_array($extension, $textExtensions)) {
            return 'txt';
        }

        return 'other';
    }

    public function userVerificationListIndex()
    {
        if (! has_permissions('read', 'approve_agent_verification')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('verify-customer-form.user_verification_list');
    }

    public function userVerificationList(Request $request)
    {
        if (! has_permissions('read', 'approve_agent_verification')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'updated_at');
        $order = $request->input('order', 'DESC');
        $search = $request->input('search');

        $sql = VerifyCustomer::with(['user' => function ($query) {
            $query->select('id', 'name', 'notification', 'email', 'profile')->withCount(['property', 'projects']);
        }, 'verify_customer_values' => function ($query) {
            $query->with(['verify_form' => function ($q) {
                $q->with('form_fields_values');
            }]);
        }])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                        ->orWhere('email', 'LIKE', "%$search%");
                });
            });

        $total = $sql->count();
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $rows = [];
        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['property_count'] = $row->user->property_count ?? 0;
            $tempRow['projects_count'] = $row->user->projects_count ?? 0;

            $viewFormClasses = ['btn', 'icon', 'btn-primary', 'btn-sm', 'rounded-pill', 'view-form-btn'];
            $viewFormAttributes = ['id' => $row->id, 'title' => trans('View Submitted Form')];
            $viewFormButton = BootstrapTableService::button('bi bi-eye ml-2', route('user-verification.show-form', $row->id), $viewFormClasses, $viewFormAttributes);
            $tempRow['raw_view_form_btn'] = $viewFormButton;

            $operate = '';
            if (has_permissions('update', 'approve_agent_verification')) {
                $operate = BootstrapTableService::editButton('', true, '#editModal', 'edit_btn', $row->id, null);
            }
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    public function getUserSubmittedForm($id)
    {
        if (! has_permissions('read', 'approve_agent_verification')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $verification = VerifyCustomer::with([
            'user',
            'verify_customer_values.verify_form.translations',
            'verify_customer_values.verify_form.form_fields_values.translations',
        ])->findOrFail($id);

        foreach ($verification->verify_customer_values as &$value) {
            if ($value->verify_form->field_type == 'file') {
                $value->file_type = $this->getFileType($value->value);
            } else {
                $value->file_type = 'other';
            }
        }

        return view('verify-customer-form.user-form-details', compact('verification'));
    }

    public function updateUserVerificationStatus(Request $request)
    {
        if (! has_permissions('update', 'approve_agent_verification')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'edit_id' => 'required',
            'edit_status' => 'required|in:approved,rejected',
            'reject_reason' => 'required_if:edit_status,rejected|max:300',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $verification = VerifyCustomer::with('user')->findOrFail($request->edit_id);
            $verification->status = $request->edit_status;
            $verification->save();

            $user = $verification->user;
            if ($user) {
                $statusText = ($request->edit_status == 'approved' ? 'Approved' : 'Rejected');
                $translatedMessage = 'Your user verification request is '.strtolower($statusText);

                if ($request->edit_status == 'rejected') {
                    // Save Reject Reason
                    RejectReason::updateOrCreate([
                        'verify_customer_id' => $verification->id,
                    ], [
                        'reason' => $request->reject_reason,
                    ]);
                    $translatedMessage .= '. Reason: '.$request->reject_reason;
                }

                if ($user->notification == 1) {
                    // Push Notification
                    $user_token = Usertokens::where('customer_id', $user->id)->pluck('fcm_id')->toArray();
                    if (! empty($user_token)) {
                        $fcmMsg = [
                            'title' => 'User Verification Request',
                            'message' => $translatedMessage,
                            'type' => 'user_verification',
                            'body' => $translatedMessage,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',
                            'id' => (string) $verification->id,
                            'role_context' => 'user',
                        ];
                        send_push_notification($user_token, $fcmMsg);
                    }

                    // Database Notification
                    Notifications::create([
                        'title' => 'User Verification Request Updated',
                        'message' => $translatedMessage,
                        'image' => '',
                        'type' => '1',
                        'send_type' => '0',
                        'customers_id' => $user->id,
                    ]);
                }

                // Email Notification
                if ($user->email) {
                    try {
                        $emailTypeData = HelperService::getEmailTemplatesTypes('user_verification_status');
                        $userVerificationTemplateData = system_setting($emailTypeData['type']);
                        $appName = env('APP_NAME') ?? 'eBroker';
                        $variables = [
                            'app_name' => $appName,
                            'user_name' => $user->name,
                            'status' => $statusText,
                            'reject_reason' => $request->edit_status == 'rejected' ? $request->reject_reason : null,
                            'email' => $user->email,
                        ];

                        if (empty($userVerificationTemplateData)) {
                            $userVerificationTemplateData = 'Your User Verification Status is '.$variables['status'];
                        }
                        $userVerificationTemplate = HelperService::replaceEmailVariables($userVerificationTemplateData, $variables);

                        $data = [
                            'email_template' => $userVerificationTemplate,
                            'email' => $user->email,
                            'title' => $emailTypeData['title'],
                        ];
                        HelperService::sendMail($data);
                    } catch (Exception $e) {
                        Log::error('Error sending user verification email: '.$e->getMessage());
                    }
                }
            }

            DB::commit();
            ResponseService::successResponse('Status updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'Error occurred');
        }
    }
}
