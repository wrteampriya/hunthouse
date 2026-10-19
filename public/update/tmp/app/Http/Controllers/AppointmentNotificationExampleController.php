<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AppointmentNotificationExampleController extends Controller
{
    /**
     * Example: Mark appointment as completed
     */
    public function markAsCompleted(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'appointment_id' => 'required|exists:appointments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $appointment = Appointment::findOrFail($request->appointment_id);

        // Update appointment status
        $appointment->status = 'completed';
        $appointment->save();

        // Send notification using the service
        AppointmentNotificationService::sendStatusNotification(
            $appointment,
            'completed',
            null, // No reason needed for completion
            'agent' // Assuming agent marks it as completed
        );

        return response()->json(['message' => 'Appointment marked as completed']);
    }

    /**
     * Example: Mark appointment as no-show
     */
    public function markAsNoShow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'appointment_id' => 'required|exists:appointments,id',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $appointment = Appointment::findOrFail($request->appointment_id);

        // Update appointment status
        $appointment->status = 'no_show';
        $appointment->save();

        // Send notification using the service
        AppointmentNotificationService::sendStatusNotification(
            $appointment,
            'no_show',
            $request->reason, // Optional reason for no-show
            'agent' // Assuming agent marks it as no-show
        );

        return response()->json(['message' => 'Appointment marked as no-show']);
    }

    /**
     * Example: Reschedule appointment with custom reason
     */
    public function rescheduleAppointment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'appointment_id' => 'required|exists:appointments,id',
            'new_date' => 'required|date|after:today',
            'new_start_time' => 'required|date_format:H:i',
            'new_end_time' => 'required|date_format:H:i|after:new_start_time',
            'reason' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $appointment = Appointment::findOrFail($request->appointment_id);

        // Update appointment times and status
        $appointment->start_at = $request->new_date.' '.$request->new_start_time;
        $appointment->end_at = $request->new_date.' '.$request->new_end_time;
        $appointment->status = 'rescheduled';
        $appointment->save();

        // Send notification using the service
        AppointmentNotificationService::sendStatusNotification(
            $appointment,
            'rescheduled',
            $request->reason,
            'agent' // Assuming agent reschedules
        );

        return response()->json(['message' => 'Appointment rescheduled successfully']);
    }

    /**
     * Example: Send reminder notification (custom method)
     */
    public function sendReminder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'appointment_id' => 'required|exists:appointments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $appointment = Appointment::findOrFail($request->appointment_id);

        // You could extend the service to handle reminders
        // For now, we'll use the status notification with a custom message
        AppointmentNotificationService::sendStatusNotification(
            $appointment,
            'reminder', // Custom status for reminders
            'This is a friendly reminder about your upcoming appointment',
            'system' // System-generated reminder
        );

        return response()->json(['message' => 'Reminder sent successfully']);
    }

    /**
     * Example: Bulk status update with notifications
     */
    public function bulkStatusUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'appointment_ids' => 'required|array',
            'appointment_ids.*' => 'exists:appointments,id',
            'new_status' => 'required|in:confirmed,cancelled,completed,no_show',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $appointments = Appointment::whereIn('id', $request->appointment_ids)->get();
        $successCount = 0;
        $errorCount = 0;

        foreach ($appointments as $appointment) {
            try {
                // Update appointment status
                $appointment->status = $request->new_status;
                $appointment->save();

                // Send notification
                $notificationSent = AppointmentNotificationService::sendStatusNotification(
                    $appointment,
                    $request->new_status,
                    $request->reason,
                    'admin' // Assuming admin makes bulk updates
                );

                if ($notificationSent) {
                    $successCount++;
                } else {
                    $errorCount++;
                }

            } catch (\Exception $e) {
                $errorCount++;
                // Log error for debugging
                Log::error("Failed to update appointment {$appointment->id}: ".$e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Bulk update completed',
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'total' => count($appointments),
        ]);
    }
}
