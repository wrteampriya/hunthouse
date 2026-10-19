<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentSocialMediaValue;
use App\Models\AgentVerification;
use App\Models\AgentVerificationForm;
use App\Models\AgentVerificationFormSection;
use App\Models\AgentVerificationFormValue;
use App\Models\AgentVerificationValue;
use App\Models\Customer;
use App\Models\SocialMediaLink;
use App\Models\User;
use App\Models\VerifyCustomer;
use App\Models\VerifyCustomerForm;
use App\Models\VerifyCustomerFormValue;
use App\Models\VerifyCustomerValue;
use App\Services\ApiResponseService;
use App\Services\FileService;
use App\Services\HelperService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\HeaderParameter;

class VerificationApiController extends Controller
{
    public function getAgentVerificationForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'form_type' => 'required|in:become_agent,verify_agent',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        $data = AgentVerificationFormSection::where('status', 'active')
            ->where('form_type', $request->form_type)
            ->whereHas('agent_verification_forms', function ($query) {
                $query->where('status', 'active');
            })
            ->with(['agent_verification_forms' => function ($query) {
                $query->where('status', 'active')->orderBy('sequence', 'ASC')->with(['form_fields_values' => function ($subQuery) {
                    $subQuery->with('translations')->select('id', 'agent_verification_form_id', 'value');
                }, 'translations'])->select('id', 'agent_verification_form_section_id', 'name', 'field_type', 'is_required', 'sequence');
            }, 'translations'])
            ->orderBy('sequence', 'ASC')
            ->select('id', 'name', 'form_type', 'is_default', 'sequence')
            ->get()
            ->map(function ($section) {
                if ($section->agent_verification_forms) {
                    $section->agent_verification_forms->map(function ($field) {
                        if ($field->form_fields_values) {
                            $field->form_fields_values->map(function ($formValue) {
                                $formValue->translated_value = $formValue->translated_value;

                                return $formValue;
                            });
                        }
                        $field->translated_name = $field->translated_name;

                        return $field;
                    });
                }
                $section->translated_name = $section->translated_name;

                return $section;
            })
            ->filter(function ($section) {
                return $section->agent_verification_forms && $section->agent_verification_forms->isNotEmpty();
            })
            // The Default section must always render first, regardless of the section's own
            // (admin-draggable) sequence — pin it ahead of everything else here instead of
            // relying on sequence staying 0.
            ->sortBy(function ($section) {
                return $section->is_default ? -1 : $section->sequence;
            })
            ->values();

        if ($request->form_type === 'become_agent') {
            $this->mergeSocialMediaFieldsIntoDefaultSection($data);
        }

        if (collect($data)->isNotEmpty()) {
            ApiResponseService::successResponse('Data Fetched Successfully', $data, [], 200);
        } else {
            ApiResponseService::successResponse('No Data Found');
        }
    }

    public function getAgentVerificationFormFields(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'form_type' => 'required|in:become_agent,verify_agent',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        $data = AgentVerificationFormSection::where('status', 'active')
            ->where('form_type', $request->form_type)
            ->whereHas('agent_verification_forms', function ($query) {
                $query->where('status', 'active');
            })
            ->with(['agent_verification_forms' => function ($query) {
                $query->where('status', 'active')->with('form_fields_values:id,agent_verification_form_id,value')->select('id', 'agent_verification_form_section_id', 'name', 'field_type', 'is_required');
            }])->select('id', 'name', 'form_type', 'is_default', 'sequence')->get()
            ->filter(function ($section) {
                return $section->agent_verification_forms && $section->agent_verification_forms->isNotEmpty();
            })
            ->sortBy(function ($section) {
                return $section->is_default ? -1 : $section->sequence;
            })
            ->values();

        if ($request->form_type === 'become_agent') {
            $this->mergeSocialMediaFieldsIntoDefaultSection($data);
        }

        if (collect($data)->isNotEmpty()) {
            ApiResponseService::successResponse('Data Fetched Successfully', $data, [], 200);
        } else {
            ApiResponseService::successResponse('No Data Found');
        }
    }

    /**
     * Social media links live in the social_media_links/agent_social_media_values tables, not
     * AgentVerificationForm — this appends matching pseudo-fields onto the existing Default
     * section (not a new section) so the front-end renders them from the same fields loop it
     * already uses there. Definitions only (id/name/icon), never the customer's saved url —
     * that stays in get-agent-verification-form-values, same split as every other field type
     * here. field_type 'social_link' marks these so the front submits them via
     * social_media_links[] in apply-agent-verification, not form_fields[] — their id is a
     * social_media_links platform id, not an agent_verification_forms id.
     */
    private function mergeSocialMediaFieldsIntoDefaultSection($data)
    {
        $defaultSection = collect($data)->first(function ($section) {
            return $section->is_default;
        });

        if (! $defaultSection) {
            return;
        }

        $links = SocialMediaLink::where('status', 'active')->orderBy('sequence', 'ASC')->get();

        if ($links->isEmpty()) {
            return;
        }

        $nextSequence = ($defaultSection->agent_verification_forms->max('sequence') ?? 0) + 1;

        $socialFields = $links->values()->map(function ($link, $index) use ($defaultSection, $nextSequence) {
            return (object) [
                'id' => $link->id,
                'agent_verification_form_section_id' => $defaultSection->id,
                'name' => $link->name,
                'translated_name' => $link->translated_name,
                'field_type' => 'social_link',
                'is_required' => false,
                'sequence' => $nextSequence + $index,
                'icon' => $link->icon,
                'form_fields_values' => [],
            ];
        });

        // A plain property assignment here would write into Eloquent's $attributes array, not
        // $relations — toArray()/toJson() serialize from $relations, so the merge would silently
        // never show up in the response without going through setRelation().
        $defaultSection->setRelation(
            'agent_verification_forms',
            $defaultSection->agent_verification_forms->concat($socialFields)->values()
        );
    }

    public function getAgentVerificationFormValues(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'form_type' => 'required|in:become_agent,verify_agent',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        $data = AgentVerification::where('customer_id', Auth::user()->id)
            ->where('form_type', $request->form_type)
            ->with(['customer' => function ($query) {
                $query->select('id', 'name', 'profile', 'email', 'mobile', 'country_code')->withCount(['property', 'projects']);
            }])->with(['values' => function ($query) {
                $query->with('verify_form:id,name,field_type')->select('id', 'agent_verification_id', 'agent_verification_form_id', 'value');
            }])->first();

        if ($request->form_type === 'become_agent') {
            $customer = $data->customer ?? Customer::select('id', 'name', 'profile', 'email', 'mobile', 'country_code')
                ->withCount(['property', 'projects'])
                ->find(Auth::user()->id);

            $values = $this->withProfileDefaults($data->values ?? collect(), $customer);
            $workingHoursTotal = $this->calculateWorkingHoursTotal($values);

            if ($data) {
                $data->values = $values;
                $data->working_hours_total_per_day = $workingHoursTotal;
                $data->social_media_links = $customer->social_media_links;
                ApiResponseService::successResponse('Data Fetched Successfully', $data, [], 200);
            } else {
                // No submission yet, but the Default section's Name/Email/Mobile still have
                // profile-derived defaults to show — never a blank form for become_agent.
                ApiResponseService::successResponse('Data Fetched Successfully', [
                    'id' => null,
                    'customer_id' => Auth::user()->id,
                    'form_type' => $request->form_type,
                    'status' => null,
                    'working_hours_total_per_day' => $workingHoursTotal,
                    'customer' => $customer,
                    'values' => $values,
                    'social_media_links' => $customer->social_media_links,
                ], [], 200);
            }

            return;
        }

        if (collect($data)->isNotEmpty()) {
            ApiResponseService::successResponse('Data Fetched Successfully', $data, [], 200);
        } else {
            ApiResponseService::successResponse('No Data Found');
        }
    }

    /**
     * Name/Email/Mobile in the Default section are editable but start pre-filled from the
     * customer's own profile. If the agent hasn't submitted (or overwritten) one of them yet,
     * inject the profile value as its default so the field never renders blank.
     */
    private function withProfileDefaults($values, $customer)
    {
        $profileFieldMap = [
            'Name' => $customer?->name,
            'Email' => $customer?->email,
            'Mobile' => $customer?->mobile,
        ];

        $defaultFields = AgentVerificationForm::whereIn('name', array_keys($profileFieldMap))
            ->whereHas('agent_verification_form_section', function ($query) {
                $query->where('is_default', true);
            })
            ->get(['id', 'name', 'field_type']);

        foreach ($defaultFields as $field) {
            if ($values->contains('agent_verification_form_id', $field->id)) {
                continue;
            }

            $values->push((object) [
                'id' => null,
                'agent_verification_id' => null,
                'agent_verification_form_id' => $field->id,
                'value' => $profileFieldMap[$field->name],
                'verify_form' => (object) ['id' => $field->id, 'name' => $field->name, 'field_type' => $field->field_type],
            ]);
        }

        return $values;
    }

    /**
     * Working Hours - Start/End are two separate time fields in the Default section;
     * Total Hours/Day is derived here rather than stored, so every client shows the same number.
     */
    private function calculateWorkingHoursTotal($values): ?float
    {
        $start = $values->first(function ($value) {
            return $value->verify_form && $value->verify_form->name === 'Working Hours - Start';
        });
        $end = $values->first(function ($value) {
            return $value->verify_form && $value->verify_form->name === 'Working Hours - End';
        });

        if (! $start || ! $end || empty($start->value) || empty($end->value)) {
            return null;
        }

        $startTime = \Carbon\Carbon::createFromFormat('H:i', $start->value);
        $endTime = \Carbon\Carbon::createFromFormat('H:i', $end->value);

        if ($endTime->lessThanOrEqualTo($startTime)) {
            $endTime->addDay();
        }

        return round($startTime->diffInMinutes($endTime) / 60, 2);
    }

    /**
     * Front-end sends both 24-hour ("09:00") and 12-hour with AM/PM ("9:00 AM", "6:05 AM")
     * for 'time' fields — normalize either to 24-hour "H:i" for storage, since that's the
     * format calculateWorkingHoursTotal() (Carbon::createFromFormat('H:i', ...)) and existing
     * stored data both expect. Returns null when the value is neither.
     */
    private function normalizeTimeInput(?string $value): ?string
    {
        if (empty($value)) {
            return $value;
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return $value;
        }

        try {
            return \Carbon\Carbon::createFromFormat('g:i A', trim($value))->format('H:i');
        } catch (Exception) {
            return null;
        }
    }

    public function applyAgentVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'form_type'             => 'required|in:become_agent,verify_agent',
            'form_fields'           => 'required|array',
            'form_fields.*.id'      => 'required|exists:agent_verification_forms,id',
            'form_fields.*.value'   => 'required|max:3072',
            'social_media_links'          => 'nullable|array',
            'social_media_links.*.id'     => 'required_with:social_media_links|exists:social_media_links,id',
            'social_media_links.*.url'    => 'nullable|url|max:255',
        ], [
            'form_fields.*.id'       => ':positionth Form Field id is not valid',
            'form_fields.*.value'    => trans('File size exceeds the limit. Please upload a file smaller than 3MB.'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();

            // If Payload is empty then show Payload is empty
            if (empty($request->form_fields)) {
                ApiResponseService::validationError('Payload is empty');
            }

            // Every active is_required field for this form_type must be submitted with a value.
            $requiredFieldIds = AgentVerificationForm::whereHas('agent_verification_form_section', function ($query) use ($request) {
                $query->where('form_type', $request->form_type)->where('status', 'active');
            })->where('status', 'active')->where('is_required', true)->pluck('id');

            if ($requiredFieldIds->isNotEmpty()) {
                $submittedFieldIds = collect($request->form_fields)
                    ->filter(function ($field) {
                        return isset($field['value']) && $field['value'] !== '';
                    })
                    ->pluck('id')
                    ->map(function ($id) {
                        return (int) $id;
                    });

                if ($requiredFieldIds->diff($submittedFieldIds)->isNotEmpty()) {
                    ApiResponseService::validationError('Please fill all required fields');
                }
            }

            // check agent is already verified. if agent is already verified then he/she cannot apply for verification again. if agent try to apply for verification again then show message "You are already verified agent"
            $existingVerification = AgentVerification::where('customer_id', Auth::user()->id)
                ->where('form_type', $request->form_type)
                ->where('status', 'approved')
                ->first();

            if ($existingVerification) {
                ApiResponseService::validationError('You are already verified agent');
            }

            // Check if auto-approve is enabled for agent verification
            // $agentAutoApprove = HelperService::getSettingData('agent_auto_approve');
            // $initialStatus = ($agentAutoApprove == 1) ? 'approved' : 'pending';

            // Update the status of Customer (User) for the specific form type
            $verifyCustomer = AgentVerification::updateOrCreate(
                [
                    'customer_id' => Auth::user()->id,
                    'form_type' => $request->form_type,
                ],
                [
                    'status' => 'pending', // Set to pending by default, can be updated to approved based on settings
                ]
            );

            // If auto-approved, set the appropriate customer flag and send notifications
            // if ($initialStatus === 'approved') {
            //     $customer = Auth::user();
            //     if ($request->form_type === 'become_agent') {
            //         $title = 'Become Agent Request Approved';
            //         $translatedMessage = 'Congratulations! Your request to become an agent has been approved.';
            //         $customer->update(['is_agent' => 1]);
            //     } elseif ($request->form_type === 'verify_agent') {
            //         $title = 'Agent Verification Approved';
            //         $translatedMessage = 'Your agent verification request has been approved.';
            //         $customer->update(['is_agent_verified' => 1]);
            //     }

            //     // Send Notifications & Email for Auto-Approval
            //     if ($customer->notification == 1) {
            //         $user_token = \App\Models\Usertokens::where('customer_id', $customer->id)->pluck('fcm_id')->toArray();
            //         if (!empty($user_token)) {
            //             $fcmMsg = array(
            //                 'title' => $title,
            //                 'message' => $translatedMessage,
            //                 'type' => 'agent_verification',
            //                 'body' => $translatedMessage,
            //                 'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            //                 'sound' => 'default',
            //                 'id' => (string)$verifyCustomer->id,
            //                 'role_context' => 'agent',
            //             );
            //             send_push_notification($user_token, $fcmMsg);
            //         }

            //         \App\Models\Notifications::create([
            //             'title' => $title,
            //             'message' => $translatedMessage,
            //             'image' => '',
            //             'type' => '1',
            //             'send_type' => '0',
            //             'customers_id' => $customer->id
            //         ]);
            //     }

            //     if ($customer->email) {
            //         try {
            //             $emailTypeData = HelperService::getEmailTemplatesTypes("agent_verification_status");
            //             $agentVerificationTemplateData = system_setting($emailTypeData['type']);
            //             $appName = env("APP_NAME") ?? "eBroker";
            //             $variables = array(
            //                 'app_name' => $appName,
            //                 'user_name' => $customer->name,
            //                 'status' => 'Approved',
            //                 'email' => $customer->email
            //             );
            //             if (empty($agentVerificationTemplateData)) {
            //                 $agentVerificationTemplateData = "Your Agent Verification Status is Approved";
            //             }
            //             $agentVerificationTemplate = HelperService::replaceEmailVariables($agentVerificationTemplateData, $variables);

            //             $data = array(
            //                 'email_template' => $agentVerificationTemplate,
            //                 'email' => $customer->email,
            //                 'title' => $emailTypeData['title'],
            //             );
            //             HelperService::sendMail($data);
            //         } catch (Exception $e) {
            //             Log::error("Error sending auto-approved agent verification email: " . $e->getMessage());
            //         }
            //     }
            // }

            // Loop on request data of form_fields
            foreach ($request->form_fields as $key => $form_fields) {
                if (isset($form_fields['value']) && ! empty($form_fields['value'])) {
                    // Check the Value is File upload or not
                    if ($request->hasFile('form_fields.'.$key.'.value')) {
                        $file = $request->file('form_fields.'.$key.'.value'); // Get Request File
                        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp']; // Allowed Images Extensions
                        $allowedDocumentExtensions = ['doc', 'docx', 'pdf', 'txt']; // Allowed Documentation Extensions
                        $extension = $file->getClientOriginalExtension(); // Get Extension
                        // Check the extension and verify with allowed images or documents extensions
                        if (in_array($extension, $allowedImageExtensions) || in_array($extension, $allowedDocumentExtensions)) {
                            // Get Old form value
                            $oldFormValue = AgentVerificationValue::where(['agent_verification_id' => $verifyCustomer->id, 'agent_verification_form_id' => $form_fields['id']])->first();
                            $path = config('global.AGENT_VERIFICATION_DOC_PATH');
                            if (! empty($oldFormValue)) {
                                FileService::delete($path, $oldFormValue->getRawOriginal('value'));
                            }
                            $imageName = FileService::compressAndUpload($file, $path);
                            $value = $imageName;
                        } else {
                            // If File is not allowed then show Invalid File Type : Allowed types are: jpg, jpeg, png, doc, docx, pdf, txt
                            ApiResponseService::validationError('Invalid File Type');
                        }
                    } else {
                        // Check the value other than File Upload
                        $formFieldQueryData = AgentVerificationForm::where('id', $form_fields['id'])->first();
                        if ($formFieldQueryData->field_type == 'radio' || $formFieldQueryData->field_type == 'dropdown') {
                            $id = $form_fields['id'];
                            $value = $form_fields['value'];
                            // IF Field Type is Radio or Dropdown, then check its value with database stored options
                            $checkValueExists = AgentVerificationFormValue::where(['agent_verification_form_id' => $id, 'value' => $value])->first();
                            if (collect($checkValueExists)->isEmpty()) {
                                ApiResponseService::validationError('All fields are required');
                            }
                        } elseif ($formFieldQueryData->field_type == 'checkbox') {
                            // IF Field Type is Checkbox
                            $submittedValue = explode(',', $form_fields['value']); // Explode the Comma Separated Values
                            $encodedValueArray = [];
                            // Loop on the values and check its value with database stored options
                            foreach ($submittedValue as $key => $value) {
                                $encodedValue = $value;
                                $checkValueExists = AgentVerificationFormValue::where(['agent_verification_form_id' => $form_fields['id'], 'value' => $encodedValue])->first();
                                if (collect($checkValueExists)->isEmpty()) {
                                    ApiResponseService::validationError('All fields are required');
                                }
                                $encodedValueArray[] = $encodedValue;
                            }
                            // Convert the value into json encode
                            $value = implode(',', $encodedValueArray);
                        } elseif ($formFieldQueryData->field_type == 'time') {
                            $normalizedTime = $this->normalizeTimeInput($form_fields['value']);
                            if ($normalizedTime === null) {
                                ApiResponseService::validationError('Invalid time format, expected HH:MM or h:mm AM/PM');
                            }
                            $value = $normalizedTime;
                        } else {
                            // Get Value as it is for other field types (including freeform "chips")
                            $value = $form_fields['value'];
                        }
                    }

                    AgentVerificationValue::updateOrCreate(
                        [
                            'agent_verification_id' => $verifyCustomer->id,
                            'agent_verification_form_id' => (int) $form_fields['id'],
                        ],
                        [
                            'value' => $value,
                        ]
                    );
                }
            }

            // Social media links are optional and reuse the same agent_social_media_values
            // table as update-agent-profile, so the Become Agent form and the profile screen
            // always show the same data.
            if ($request->form_type === 'become_agent' && $request->has('social_media_links')) {
                foreach ($request->social_media_links as $link) {
                    AgentSocialMediaValue::updateOrCreate(
                        ['customer_id' => Auth::user()->id, 'social_media_link_id' => $link['id']],
                        ['url' => $link['url'] ?? null]
                    );
                }
            }

            // Send Notification to Admin
            $fcm_id = [];
            $user_data = User::select('fcm_id', 'name')->get();
            foreach ($user_data as $user) {
                array_push($fcm_id, $user->fcm_id);
            }

            if (! empty($fcm_id)) {
                $registrationIDs = $fcm_id;
                $fcmMsg = [
                    'title' => 'Agent Verification Form Submitted',
                    'message' => 'Agent Verification Form Submitted',
                    'type' => 'agent_verification',
                    'body' => 'Agent Verification Form Submitted',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                    'role_context' => 'agent',
                ];
                send_push_notification($registrationIDs, $fcmMsg);
            }

            // Commit the changes and return response
            DB::commit();
            if ($request->form_type == 'become_agent') {
                ApiResponseService::successResponse('Your become agent request has been submitted successfully. Please wait for admin approval');
            } else {
                ApiResponseService::successResponse('Your agent verification request has been submitted successfully. Please wait for admin approval');
            }
        } catch (Exception $e) {
            DB::rollback();
            ApiResponseService::logErrorResponse($e, $e->getMessage(), '', true);
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getUserVerificationForm(Request $request)
    {
        try {
            $data = VerifyCustomerForm::orderBy('rank', 'ASC')
                ->with(['form_fields_values' => function ($subQuery) {
                    $subQuery->with('translations')->select('id', 'verify_customer_form_id', 'value');
                }, 'translations'])
                ->select('id', 'name', 'field_type', 'rank')
                ->get()
                ->map(function ($field) {
                    if ($field->form_fields_values) {
                        $field->form_fields_values->map(function ($formValue) {
                            $formValue->translated_value = $formValue->translated_value;

                            return $formValue;
                        });
                    }
                    $field->translated_name = $field->translated_name;

                    return $field;
                });

            if ($data->isNotEmpty()) {
                ApiResponseService::successResponse('Data Fetched Successfully', $data);
            } else {
                ApiResponseService::successResponse('No Data Found');
            }
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, $e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getUserVerificationFormValues(Request $request)
    {
        try {
            $data = VerifyCustomer::where('user_id', Auth::user()->id)
                ->with(['user' => function ($query) {
                    $query->select('id', 'name', 'profile')->withCount(['property', 'projects' => function ($q) {
                        $q->where('status', 1);
                    }]);
                }])->with(['verify_customer_values' => function ($query) {
                    $query->with('verify_form:id,name,field_type')->select('id', 'verify_customer_id', 'verify_customer_form_id', 'value');
                }])->first();

            if ($data) {
                ApiResponseService::successResponse('Data Fetched Successfully', $data);
            } else {
                ApiResponseService::successResponse('No Data Found');
            }
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, $e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function applyUserVerification(Request $request)
    {

        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'form_fields' => 'required|array',
            'form_fields.*.id' => 'required|exists:verify_customer_forms,id',
            'form_fields.*.value' => 'required',
        ], [
            'form_fields.*.id' => ':positionth Form Field id is not valid',
            'form_fields.*.value' => ':positionth Form Field Value is not valid',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            if (empty($request->form_fields)) {
                ApiResponseService::validationError('Payload is empty');
            }

            // Check if auto-approve is enabled for user verification
            // $userAutoApprove = HelperService::getSettingData('auto_approve');
            // $initialStatus = ($userAutoApprove == 1) ? 'approved' : 'pending';

            // Update or create the VerifyCustomer record
            $verifyCustomer = VerifyCustomer::updateOrCreate(
                [
                    'user_id' => Auth::user()->id,
                ],
                [
                    'status' => 'pending', // Set to pending by default, can be updated to approved based on settings
                ]
            );

            // If auto-approved, send notifications
            // if ($initialStatus === 'approved') {
            //     $customer = Auth::user();
            //     $title = 'User Verification Approved';
            //     $translatedMessage = 'Your user verification request has been approved.';

            //     // Send Notifications & Email for Auto-Approval
            //     if ($customer->notification == 1) {
            //         $user_token = \App\Models\Usertokens::where('customer_id', $customer->id)->pluck('fcm_id')->toArray();
            //         if (!empty($user_token)) {
            //             $fcmMsg = array(
            //                 'title' => $title,
            //                 'message' => $translatedMessage,
            //                 'type' => 'user_verification',
            //                 'body' => $translatedMessage,
            //                 'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            //                 'sound' => 'default',
            //                 'id' => (string)$verifyCustomer->id,
            //                 'role_context' => 'user',
            //             );
            //             send_push_notification($user_token, $fcmMsg);
            //         }

            //         \App\Models\Notifications::create([
            //             'title' => $title,
            //             'message' => $translatedMessage,
            //             'image' => '',
            //             'type' => '1',
            //             'send_type' => '0',
            //             'customers_id' => $customer->id
            //         ]);
            //     }

            //     if ($customer->email) {
            //         try {
            //             $emailTypeData = HelperService::getEmailTemplatesTypes("user_verification_status");
            //             $userVerificationTemplateData = system_setting($emailTypeData['type']);
            //             $appName = env("APP_NAME") ?? "eBroker";
            //             $variables = array(
            //                 'app_name' => $appName,
            //                 'user_name' => $customer->name,
            //                 'status' => 'Approved',
            //                 'email' => $customer->email
            //             );

            //             if (empty($userVerificationTemplateData)) {
            //                 $userVerificationTemplateData = "Your User Verification Status is Approved";
            //             }
            //             $userVerificationTemplate = HelperService::replaceEmailVariables($userVerificationTemplateData, $variables);

            //             $data = array(
            //                 'email_template' => $userVerificationTemplate,
            //                 'email' => $customer->email,
            //                 'title' => $emailTypeData['title']
            //             );
            //             HelperService::sendMail($data);
            //         } catch (Exception $e) {
            //             Log::error("Error sending auto-approved user verification email: " . $e->getMessage());
            //         }
            //     }
            // }

            foreach ($request->form_fields as $key => $form_fields) {
                if (isset($form_fields['value']) && ! empty($form_fields['value'])) {
                    $value = '';
                    if ($request->hasFile('form_fields.'.$key.'.value')) {
                        $file = $request->file('form_fields.'.$key.'.value');
                        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                        $allowedDocumentExtensions = ['doc', 'docx', 'pdf', 'txt'];
                        $extension = $file->getClientOriginalExtension();

                        if (in_array($extension, $allowedImageExtensions) || in_array($extension, $allowedDocumentExtensions)) {
                            $oldFormValue = VerifyCustomerValue::where([
                                'verify_customer_id' => $verifyCustomer->id,
                                'verify_customer_form_id' => $form_fields['id'],
                            ])->first();

                            $path = config('global.AGENT_VERIFICATION_DOC_PATH');
                            if (! empty($oldFormValue)) {
                                FileService::delete($path, $oldFormValue->getRawOriginal('value'));
                            }
                            $imageName = FileService::compressAndUpload($file, $path);
                            $value = $imageName;
                        } else {
                            ApiResponseService::validationError('Invalid File Type');
                        }
                    } else {
                        $formFieldQueryData = VerifyCustomerForm::where('id', $form_fields['id'])->first();
                        if ($formFieldQueryData->field_type == 'radio' || $formFieldQueryData->field_type == 'dropdown') {
                            $checkValueExists = VerifyCustomerFormValue::where([
                                'verify_customer_form_id' => $form_fields['id'],
                                'value' => $form_fields['value'],
                            ])->first();
                            if (collect($checkValueExists)->isEmpty()) {
                                ApiResponseService::validationError('All fields are required');
                            }
                            $value = $form_fields['value'];
                        } elseif ($formFieldQueryData->field_type == 'checkbox') {
                            $submittedValue = explode(',', $form_fields['value']);
                            $encodedValueArray = [];
                            foreach ($submittedValue as $val) {
                                $checkValueExists = VerifyCustomerFormValue::where([
                                    'verify_customer_form_id' => $form_fields['id'],
                                    'value' => trim($val),
                                ])->first();
                                if (collect($checkValueExists)->isEmpty()) {
                                    ApiResponseService::validationError('All fields are required');
                                }
                                $encodedValueArray[] = trim($val);
                            }
                            $value = implode(',', $encodedValueArray);
                        } else {
                            $value = $form_fields['value'];
                        }
                    }

                    VerifyCustomerValue::updateOrCreate(
                        [
                            'verify_customer_id' => $verifyCustomer->id,
                            'verify_customer_form_id' => (int) $form_fields['id'],
                        ],
                        [
                            'value' => $value,
                        ]
                    );
                }
            }

            DB::commit();
            ApiResponseService::successResponse('Verification Applied Successfully');
        } catch (Exception $e) {
            DB::rollback();
            ApiResponseService::logErrorResponse($e, $e->getMessage());
        }
    }
}
