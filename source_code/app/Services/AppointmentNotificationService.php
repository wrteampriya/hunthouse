<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Notifications;
use App\Models\Property;
use App\Models\User;
use App\Models\Usertokens;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentNotificationService
{
    /**
     * Send notification for appointment status change
     */
    public static function sendStatusNotification(Appointment $appointment, string $newStatus, ?string $reason = null, ?string $changedBy = null)
    {
        try {
            $agent = Customer::select('id', 'name', 'email')
                ->where('id', $appointment->agent_id)
                ->first();

            $user = Customer::select('id', 'name', 'email')
                ->where('id', $appointment->user_id)
                ->first();

            $property = Property::select('id', 'title')
                ->where('id', $appointment->property_id)
                ->first();

            // Determine who to notify based on who made the change
            $notifyTarget = null;
            $notifier = null;

            if ($changedBy === 'agent') {
                $notifyTarget = $user;
                $notifier = $agent;
            } elseif ($changedBy === 'user') {
                $notifyTarget = $agent;
                $notifier = $user;
            } else {
                // Default: notify the other party
                $notifyTarget = $user;
                $notifier = $agent;
            }

            if (! $notifyTarget) {
                Log::warning('No target found for appointment notification', [
                    'appointment_id' => $appointment->id,
                    'status' => $newStatus,
                ]);

                return false;
            }

            // Send email notification
            self::sendEmailNotification($appointment, $notifyTarget, $notifier, $property, $newStatus, $reason);

            // Send push notification
            self::sendPushNotification($appointment, $notifyTarget, $newStatus, $reason);

            // Store notification in database
            self::storeNotification($appointment, $notifyTarget, $newStatus, $reason, $property);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send appointment status notification', [
                'appointment_id' => $appointment->id,
                'status' => $newStatus,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email notification for appointment status change
     */
    private static function sendEmailNotification(Appointment $appointment, Customer $notifyTarget, Customer $notifier, Property $property, string $status, ?string $reason = null)
    {
        try {
            if (empty($notifyTarget->email)) {
                return false;
            }

            $emailTypeData = HelperService::getEmailTemplatesTypes('appointment_status');
            $templateRaw = HelperService::getSettingData($emailTypeData['type']);
            $appName = env('APP_NAME') ?? 'eBroker';

            // Get timezone for the target user
            $targetTimezone = $notifyTarget->getTimezone();
            $startAt = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($targetTimezone);
            $endAt = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($targetTimezone);

            $variables = [
                'app_name' => $appName,
                'user_name' => $notifyTarget->name,
                'property_name' => $property?->title ?? 'N/A',
                'agent_name' => $notifier?->name ?? 'N/A',
                'customer_name' => $user?->name ?? 'N/A',
                'meeting_status' => $status,
                'meeting_type' => $appointment->meeting_type,
                'start_time' => $startAt->format('H:i'),
                'end_time' => $endAt->format('H:i'),
                'date' => $startAt->format('d M Y'),
                'reason' => $reason,
                'email' => $notifyTarget->email,
            ];

            if (empty($templateRaw)) {
                $templateRaw = self::getDefaultEmailTemplate($status);
            }

            $emailTemplate = HelperService::replaceEmailVariables($templateRaw, $variables);
            $data = [
                'email_template' => $emailTemplate,
                'email' => $notifyTarget->email,
                'title' => self::getEmailTitle($status),
            ];

            HelperService::sendMail($data);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send appointment email notification', [
                'appointment_id' => $appointment->id,
                'target_email' => $notifyTarget->email ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send push notification for appointment status change
     */
    private static function sendPushNotification(Appointment $appointment, Customer $notifyTarget, string $status, ?string $reason = null)
    {
        try {
            $fcmTokens = Usertokens::where('customer_id', $notifyTarget->id)
                ->pluck('fcm_id')
                ->toArray();

            if (empty($fcmTokens)) {
                return false;
            }

            $title = self::getNotificationTitle($status);
            $body = self::getNotificationBody($status, $reason);

            $fcmMsg = [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'appointment_status',
                    'appointment_id' => $appointment->id,
                    'status' => $status,
                    'reason' => $reason,
                ],
            ];

            send_push_notification($fcmTokens, $fcmMsg);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send appointment push notification', [
                'appointment_id' => $appointment->id,
                'target_id' => $notifyTarget->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Store notification in database
     */
    private static function storeNotification(Appointment $appointment, Customer $notifyTarget, string $status, ?string $reason = null, ?Property $property = null)
    {
        try {
            $title = self::getNotificationTitle($status);
            $body = self::getNotificationBody($status, $reason);

            Notifications::create([
                'title' => $title,
                'message' => $body,
                'image' => '',
                'type' => '2', // Appointment notification type
                'send_type' => '0', // Push notification
                'customers_id' => $notifyTarget->id,
                'propertys_id' => $property?->id ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to store appointment notification', [
                'appointment_id' => $appointment->id,
                'target_id' => $notifyTarget->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get default email template based on status
     */
    private static function getDefaultEmailTemplate(string $status): string
    {
        $templates = [
            'confirmed' => 'Your appointment has been confirmed for {property_name} on {date} between {start_time}-{end_time}.',
            'cancelled' => 'Your appointment for {property_name} on {date} has been cancelled. {start_reason}Reason: {reason}{end_reason}',
            'rescheduled' => 'Your appointment for {property_name} has been rescheduled to {date} between {start_time}-{end_time}.',
            'pending' => 'Your appointment request for {property_name} on {date} is pending confirmation.',
            'completed' => 'Your appointment for {property_name} on {date} has been marked as completed.',
            'no_show' => 'Your appointment for {property_name} on {date} has been marked as no-show.',
        ];

        return $templates[$status] ?? 'Your appointment status has been updated to {meeting_status}.';
    }

    /**
     * Get email title based on status
     */
    private static function getEmailTitle(string $status): string
    {
        $titles = [
            'confirmed' => 'Appointment Confirmed',
            'cancelled' => 'Appointment Cancelled',
            'rescheduled' => 'Appointment Rescheduled',
            'pending' => 'Appointment Pending',
            'completed' => 'Appointment Completed',
            'no_show' => 'Appointment No-Show',
        ];

        return $titles[$status] ?? 'Appointment Status Updated';
    }

    /**
     * Get notification title based on status
     */
    private static function getNotificationTitle(string $status): string
    {
        $titles = [
            'confirmed' => 'Appointment Confirmed',
            'cancelled' => 'Appointment Cancelled',
            'rescheduled' => 'Appointment Rescheduled',
            'pending' => 'Appointment Pending',
            'completed' => 'Appointment Completed',
            'no_show' => 'Appointment No-Show',
        ];

        return $titles[$status] ?? 'Appointment Status Updated';
    }

    /**
     * Get notification body based on status
     */
    private static function getNotificationBody(string $status, ?string $reason = null): string
    {
        $baseMessages = [
            'confirmed' => 'Your appointment has been confirmed',
            'cancelled' => 'Your appointment has been cancelled',
            'rescheduled' => 'Your appointment has been rescheduled',
            'pending' => 'Your appointment request is pending confirmation',
            'completed' => 'Your appointment has been marked as completed',
            'no_show' => 'Your appointment has been marked as no-show',
        ];

        $message = $baseMessages[$status] ?? 'Your appointment status has been updated';

        if ($reason && in_array($status, ['cancelled', 'rescheduled'])) {
            $message .= '. Reason: '.$reason;
        }

        return $message;
    }

    /**
     * Send notification for appointment cancellation due to user report
     */
    public static function sendCancellationByReportNotification(Appointment $appointment, string $reason)
    {
        try {
            $user = Customer::select('id', 'name', 'email')
                ->where('id', $appointment->user_id)
                ->first();

            if (! $user) {
                return false;
            }

            // Send push notification
            $userFcmToken = Usertokens::where('customer_id', $user->id)->get();
            $translatedTitle = 'Appointment Cancelled';
            $translatedBody = 'Your appointment(s) have been cancelled because you were reported by the agent.';

            $fcmMsg = [
                'title' => $translatedTitle,
                'body' => $translatedBody,
                'data' => [
                    'type' => 'appointment_cancelled_by_report',
                    'reason' => $reason,
                ],
            ];
            send_push_notification($userFcmToken, $fcmMsg);

            $title = 'Appointment Cancelled';
            $body = 'Your appointment(s) have been cancelled because you were reported by the agent.';
            // Store notification in DB
            Notifications::insert([
                [
                    'title' => $title,
                    'message' => $body,
                    'image' => '',
                    'type' => '2',
                    'send_type' => '0',
                    'customers_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // Send email notification
            $emailTypeData = HelperService::getEmailTemplatesTypes('appointment_status');
            $templateRaw = HelperService::getSettingData($emailTypeData['type']);
            $appName = env('APP_NAME') ?? 'eBroker';

            $variables = [
                'app_name' => $appName,
                'user_name' => $user->name,
                'property_name' => $appointment->property->title ?? 'N/A',
                'agent_name' => $appointment->agent->name ?? 'N/A',
                'customer_name' => $user->name,
                'meeting_status' => 'cancelled',
                'meeting_type' => $appointment->meeting_type,
                'start_time' => $appointment->start_at,
                'end_time' => $appointment->end_at,
                'date' => $appointment->date,
                'reason' => $reason,
            ];

            if (empty($templateRaw)) {
                $templateRaw = 'Your appointment(s) have been cancelled because you were reported by the agent. Reason: {reason}';
            }

            $emailTemplate = HelperService::replaceEmailVariables($templateRaw, $variables);
            $data = [
                'email_template' => $emailTemplate,
                'email' => $user->email,
                'title' => 'Appointment Cancelled',
            ];

            HelperService::sendMail($data);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send cancellation by report notification', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send notification for new appointment request
     */
    public static function sendNewAppointmentRequestNotification(Appointment $appointment, $agent, Customer $user, Property $property, bool $autoConfirm = false, $admin = null)
    {
        try {
            // Send notification to agent
            self::sendNewAppointmentRequestToAgent($appointment, $agent, $user, $property, $admin);

            // If auto-confirmed, send confirmation to user
            if ($autoConfirm) {
                self::sendAppointmentConfirmedToUser($appointment, $agent, $user, $property, $admin);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send new appointment request notification', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send new appointment request notification to agent
     */
    private static function sendNewAppointmentRequestToAgent(Appointment $appointment, $agent, Customer $user, Property $property, ?User $admin = null)
    {
        try {
            // Send email to agent
            $emailTypeData = HelperService::getEmailTemplatesTypes('new_appointment_request');
            $templateRaw = HelperService::getSettingData($emailTypeData['type']);
            $appName = env('APP_NAME') ?? 'eBroker';

            if ($admin) {
                $agentTimezone = $admin->getTimezone();
                $agentName = trans('Admin');
                $agentEmail = $admin->email;
            } else {
                $agentTimezone = $agent->getTimezone(true);
                $agentName = $agent->name;
                $agentEmail = $agent->email;
            }
            $agentStartAt = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone);
            $agentEndAt = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($agentTimezone);

            $variables = [
                'app_name' => $appName,
                'user_name' => $user->name,
                'property_name' => $property->title ?? 'N/A',
                'agent_name' => $agentName,
                'meeting_status' => $appointment->status,
                'meeting_type' => $appointment->meeting_type,
                'start_time' => $agentStartAt->format('H:i'),
                'end_time' => $agentEndAt->format('H:i'),
                'date' => $agentStartAt->format('d M Y'),
                'email' => $agentEmail,
                'notes' => $appointment->notes ?? null,
            ];

            if (empty($templateRaw)) {
                $templateRaw = 'New appointment request from {user_name} for {property_name} on {date} between {start_time}-{end_time}';
            }

            $emailTemplate = HelperService::replaceEmailVariables($templateRaw, $variables);
            $data = [
                'email_template' => $emailTemplate,
                'email' => $agentEmail,
                'title' => 'New Appointment Request',
            ];

            HelperService::sendMail($data);

            // Send push notification to agent
            if ($admin) {
                $agentFcmToken = [$admin->fcm_id];
            } else {
                $agentFcmToken = Usertokens::where('customer_id', $agent->id)->pluck('fcm_id')->toArray() ?? [];
            }
            $translatedTitle = 'New Appointment Request';
            $translatedBody = 'You have a new appointment request';

            $fcmMsg = [
                'title' => $translatedTitle,
                'body' => $translatedBody,
                'data' => [
                    'type' => 'new_appointment_request',
                    'appointment_id' => $appointment->id,
                ],
            ];

            send_push_notification($agentFcmToken, $fcmMsg);

            if ($agent) {
                $title = 'New Appointment Request';
                $body = 'You have a new appointment request';
                // Store notification in database
                Notifications::create([
                    'title' => $title,
                    'message' => $body,
                    'image' => '',
                    'type' => '2',
                    'send_type' => '0',
                    'customers_id' => $agent->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send new appointment request notification to agent', [
                'appointment_id' => $appointment->id,
                'agent_id' => $admin ? $admin->id : $agent->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send notification for meeting type change
     */
    public static function sendMeetingTypeChangeNotification(Appointment $appointment, string $oldMeetingType, string $newMeetingType, ?string $updatedBy = null)
    {
        try {
            $agent = Customer::select('id', 'name', 'email')
                ->where('id', $appointment->agent_id)
                ->first();

            $user = Customer::select('id', 'name', 'email')
                ->where('id', $appointment->user_id)
                ->first();

            $property = Property::select('id', 'title')
                ->where('id', $appointment->property_id)
                ->first();

            // Determine who to notify based on who made the change
            $notifyTarget = null;
            $notifier = null;

            if ($updatedBy === 'agent') {
                $notifyTarget = $user;
                $notifier = $agent;
            } elseif ($updatedBy === 'user') {
                $notifyTarget = $agent;
                $notifier = $user;
            } else {
                // Default: notify both parties
                self::sendMeetingTypeChangeToUser($appointment, $oldMeetingType, $newMeetingType, $user, $agent, $property);
                self::sendMeetingTypeChangeToAgent($appointment, $oldMeetingType, $newMeetingType, $agent, $user, $property);

                return true;
            }

            if (! $notifyTarget) {
                Log::warning('No target found for meeting type change notification', [
                    'appointment_id' => $appointment->id,
                    'old_meeting_type' => $oldMeetingType,
                    'new_meeting_type' => $newMeetingType,
                ]);

                return false;
            }

            // Send email notification
            self::sendMeetingTypeChangeEmail($appointment, $notifyTarget, $notifier, $property, $oldMeetingType, $newMeetingType);

            // Send push notification
            self::sendMeetingTypeChangePush($appointment, $notifyTarget, $oldMeetingType, $newMeetingType);

            // Store notification in database
            self::storeMeetingTypeChangeNotification($appointment, $notifyTarget, $oldMeetingType, $newMeetingType, $property);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting type change notification', [
                'appointment_id' => $appointment->id,
                'old_meeting_type' => $oldMeetingType,
                'new_meeting_type' => $newMeetingType,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send meeting type change notification to user
     */
    private static function sendMeetingTypeChangeToUser(Appointment $appointment, string $oldMeetingType, string $newMeetingType, Customer $user, $agent, Property $property)
    {
        try {
            // Send email notification
            self::sendMeetingTypeChangeEmail($appointment, $user, $agent, $property, $oldMeetingType, $newMeetingType);

            // Send push notification
            self::sendMeetingTypeChangePush($appointment, $user, $oldMeetingType, $newMeetingType);

            // Store notification in database
            self::storeMeetingTypeChangeNotification($appointment, $user, $oldMeetingType, $newMeetingType, $property);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting type change notification to user', [
                'appointment_id' => $appointment->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send meeting type change notification to agent
     */
    private static function sendMeetingTypeChangeToAgent(Appointment $appointment, string $oldMeetingType, string $newMeetingType, $agent, Customer $user, Property $property)
    {
        try {
            // Send email notification
            self::sendMeetingTypeChangeEmail($appointment, $agent, $user, $property, $oldMeetingType, $newMeetingType);

            // Send push notification
            self::sendMeetingTypeChangePush($appointment, $agent, $oldMeetingType, $newMeetingType);

            // Store notification in database
            self::storeMeetingTypeChangeNotification($appointment, $agent, $oldMeetingType, $newMeetingType, $property);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting type change notification to agent', [
                'appointment_id' => $appointment->id,
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email notification for meeting type change
     */
    private static function sendMeetingTypeChangeEmail(Appointment $appointment, Customer $notifyTarget, Customer $notifier, Property $property, string $oldMeetingType, string $newMeetingType)
    {
        try {
            if (empty($notifyTarget->email)) {
                return false;
            }

            $emailTypeData = HelperService::getEmailTemplatesTypes('appointment_meeting_type_change');
            $templateRaw = HelperService::getSettingData($emailTypeData['type']);
            $appName = env('APP_NAME') ?? 'eBroker';

            // Get timezone for the target user
            $targetTimezone = $notifyTarget->getTimezone();
            $startAt = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($targetTimezone);
            $endAt = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($targetTimezone);

            $variables = [
                'app_name' => $appName,
                'user_name' => $notifyTarget->name,
                'property_name' => $property?->title ?? 'N/A',
                'agent_name' => $notifier?->name ?? 'N/A',
                'customer_name' => $notifyTarget->name,
                'old_meeting_type' => ucfirst(str_replace('_', ' ', $oldMeetingType)),
                'new_meeting_type' => ucfirst(str_replace('_', ' ', $newMeetingType)),
                'meeting_type' => ucfirst(str_replace('_', ' ', $newMeetingType)),
                'start_time' => $startAt->format('H:i'),
                'end_time' => $endAt->format('H:i'),
                'date' => $startAt->format('d M Y'),
                'email' => $notifyTarget->email,
            ];

            if (empty($templateRaw)) {
                $templateRaw = self::getDefaultMeetingTypeChangeEmailTemplate($oldMeetingType, $newMeetingType);
            }

            $emailTemplate = HelperService::replaceEmailVariables($templateRaw, $variables);
            $data = [
                'email_template' => $emailTemplate,
                'email' => $notifyTarget->email,
                'title' => 'Meeting Type Updated',
            ];

            HelperService::sendMail($data);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting type change email notification', [
                'appointment_id' => $appointment->id,
                'target_email' => $notifyTarget->email ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send push notification for meeting type change
     */
    private static function sendMeetingTypeChangePush(Appointment $appointment, Customer $notifyTarget, string $oldMeetingType, string $newMeetingType)
    {
        try {
            $fcmTokens = Usertokens::where('customer_id', $notifyTarget->id)
                ->pluck('fcm_id')
                ->toArray();

            if (empty($fcmTokens)) {
                return false;
            }

            $title = 'Meeting Type Updated';
            $body = 'Meeting type changed from :old_type to :new_type';

            $fcmMsg = [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'meeting_type_change',
                    'appointment_id' => $appointment->id,
                    'old_meeting_type' => $oldMeetingType,
                    'new_meeting_type' => $newMeetingType,
                ],
                'replace' => [
                    'old_type' => ucfirst(str_replace('_', ' ', $oldMeetingType)),
                    'new_type' => ucfirst(str_replace('_', ' ', $newMeetingType)),
                ],
            ];

            send_push_notification($fcmTokens, $fcmMsg);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send meeting type change push notification', [
                'appointment_id' => $appointment->id,
                'target_id' => $notifyTarget->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Store meeting type change notification in database
     */
    private static function storeMeetingTypeChangeNotification(Appointment $appointment, Customer $notifyTarget, string $oldMeetingType, string $newMeetingType, ?Property $property = null)
    {
        try {
            $title = 'Meeting Type Updated';
            $body = 'Meeting type changed from '.ucfirst(str_replace('_', ' ', $oldMeetingType)).' to '.ucfirst(str_replace('_', ' ', $newMeetingType));

            Notifications::create([
                'title' => $title,
                'message' => $body,
                'image' => '',
                'type' => '2', // Appointment notification type
                'send_type' => '0', // Push notification
                'customers_id' => $notifyTarget->id,
                'propertys_id' => $property?->id ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to store meeting type change notification', [
                'appointment_id' => $appointment->id,
                'target_id' => $notifyTarget->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get default email template for meeting type change
     */
    private static function getDefaultMeetingTypeChangeEmailTemplate(string $oldMeetingType, string $newMeetingType): string
    {
        return 'Your appointment meeting type has been changed from {old_meeting_type} to {new_meeting_type} for {property_name} on {date} between {start_time}-{end_time}.';
    }

    /**
     * Send appointment confirmed notification to user
     */
    private static function sendAppointmentConfirmedToUser(Appointment $appointment, $agent, Customer $user, Property $property, ?User $admin = null)
    {
        try {
            // Send push notification to user
            $userFcmToken = Usertokens::where('customer_id', $user->id)->get();
            $translatedTitle = 'Your Appointment is Confirmed';
            $translatedBody = 'Your appointment is confirmed';

            $fcmMsg = [
                'title' => $translatedTitle,
                'body' => $translatedBody,
                'data' => [
                    'type' => 'appointment_confirmed',
                    'appointment_id' => $appointment->id,
                ],
            ];

            send_push_notification($userFcmToken, $fcmMsg);

            // Store notification in database
            $title = 'Your Appointment is Confirmed';
            $body = 'Your appointment is confirmed';
            Notifications::create([
                'title' => $title,
                'message' => $body,
                'image' => '',
                'type' => '2',
                'send_type' => '0',
                'customers_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Send confirmation email to user
            $emailTypeData = HelperService::getEmailTemplatesTypes('appointment_status');
            $templateRaw = HelperService::getSettingData($emailTypeData['type']);
            $appName = env('APP_NAME') ?? 'eBroker';

            $timezone = $user->getTimezone();
            if ($admin) {
                $agentName = trans('Admin');
            } else {
                $agentName = $agent->name;
            }
            $userStartAt = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($timezone);
            $userEndAt = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($timezone);

            $variables = [
                'app_name' => $appName,
                'user_name' => $user->name,
                'property_name' => $property->title ?? 'N/A',
                'agent_name' => $agentName,
                'customer_name' => $user->name,
                'meeting_status' => 'confirmed',
                'meeting_type' => $appointment->meeting_type,
                'start_time' => $userStartAt->format('H:i'),
                'end_time' => $userEndAt->format('H:i'),
                'date' => $userStartAt->format('d M Y'),
                'email' => $user->email,
                'reason' => null,
            ];

            if (empty($templateRaw)) {
                $templateRaw = 'Appointment status updated to {meeting_status}';
            }

            $emailTemplate = HelperService::replaceEmailVariables($templateRaw, $variables);
            $data = [
                'email_template' => $emailTemplate,
                'email' => $user->email,
                'title' => 'Your Appointment is Confirmed',
            ];

            HelperService::sendMail($data);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send appointment confirmed notification to user', [
                'appointment_id' => $appointment->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
