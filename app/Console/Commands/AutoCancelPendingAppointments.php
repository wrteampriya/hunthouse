<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Appointment;
use App\Models\AppointmentCancellation;
use App\Models\AgentBookingPreference;
use App\Services\AppointmentNotificationService;

class AutoCancelPendingAppointments extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'appointments:auto-cancel';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Automatically cancel pending appointments based on agent or admin preferences';

	/**
	 * Execute the console command.
	 */
	public function handle(): int
	{
		$this->info('Starting auto-cancel of pending appointments...');

		$nowUtc = Carbon::now('UTC');

		// Process in chunks to avoid memory issues
		Appointment::query()
			->where('status', 'pending')
			->orderBy('id')
			->chunkById(200, function ($appointments) use ($nowUtc) {
				foreach ($appointments as $appointment) {
					try {
						$preference = $this->resolvePreferenceForAppointment($appointment);
						$autoCancelAfterMinutes = (int) ($preference->auto_cancel_after_minutes ?? 0);

						// Treat non-positive value as toggle off
						if ($autoCancelAfterMinutes <= 0) {
							continue;
						}

						$createdAt = Carbon::parse($appointment->created_at, 'UTC');
						$minutesSinceCreated = $createdAt->diffInMinutes($nowUtc, false);

						if ($minutesSinceCreated < $autoCancelAfterMinutes) {
							continue;
						}

						DB::beginTransaction();

						// Double-check current status inside transaction for concurrency safety
						$appt = Appointment::lockForUpdate()->find($appointment->id);
						if (!$appt || $appt->status !== 'pending') {
							DB::rollBack();
							continue;
						}

						$reason = $preference->auto_cancel_message ?? 'Automatically cancelled due to no confirmation within the allowed time window.';

						$appt->status = 'auto_cancelled';
						$appt->last_status_updated_by = 'system';
						$appt->save();

						AppointmentCancellation::create([
							'appointment_id' => $appt->id,
							'reason' => $reason,
							'cancelled_by' => 'system',
						]);

						DB::commit();

						// Notify parties
						try {
							AppointmentNotificationService::sendStatusNotification($appt, 'cancelled', $reason, 'system');
						} catch (\Throwable $notifyEx) {
							Log::warning('Auto-cancel notification failed', [
								'appointment_id' => $appt->id,
								'error' => $notifyEx->getMessage(),
							]);
						}

						$this->line("Auto-cancelled appointment ID: {$appt->id}");
					} catch (\Throwable $e) {
						DB::rollBack();
						Log::error('Failed to auto-cancel appointment', [
							'appointment_id' => $appointment->id,
							'error' => $e->getMessage(),
						]);
					}
				}
			});

		$this->info('Auto-cancel task completed.');
		return Command::SUCCESS;
	}

	/**
	 * Resolve the preference row based on whether the appointment is for an admin or agent.
	 */
	private function resolvePreferenceForAppointment(Appointment $appointment): ?AgentBookingPreference
	{
		if ((bool) $appointment->is_admin_appointment === true || ($appointment->admin_id && !$appointment->agent_id)) {
			return AgentBookingPreference::where([
				'is_admin_data' => 1,
				'admin_id' => $appointment->admin_id,
			])->first();
		}

		if ($appointment->agent_id) {
			return AgentBookingPreference::where('agent_id', $appointment->agent_id)->first();
		}

		return null;
	}
}


