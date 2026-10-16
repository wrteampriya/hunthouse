<?php

use Carbon\Carbon;
use App\Models\Setting;
use App\Models\Projects;
use App\Models\Property;
use App\Models\ProjectView;
use App\Models\PropertyView;
use App\Services\HelperService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /********************************************************************* */
        /**
         * Update MAP_API_KEY from Place API Key
        */
        $placeApiKey = HelperService::getSettingData('place_api_key'); // Get Place API Key from settings
        if(!empty($placeApiKey)){
            $settings = [
                'map_api_key' => $placeApiKey, // Update MAP_API_KEY from Place API Key
                'gemini_ai_search' => 0
            ];

            foreach ($settings as $key => $value) { // Update MAP_API_KEY from Place API Key
                $setting = Setting::where('type', $key)->first(); // Get Setting by title
                if ($setting) {
                    $setting->data = $value; // Update MAP_API_KEY from Place API Key
                    $setting->save(); // Save Setting

                    $envUpdates = [
                        'MAP_API_KEY' => $value, // Update MAP_API_KEY from Place API Key
                    ];

                    foreach ($envUpdates as $key => $value) {
                        putenv($key . '=' . $value); // Update MAP_API_KEY from Place API Key
                    }
                }
            }
        }
        /********************************************************************* */

        /**
         * Appointment Schedule Migrations
        */

        // Agent Availability Table
        if(!Schema::hasTable('agent_availabilities')){
            Schema::create('agent_availabilities', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_admin_data')->default(0);
                $table->foreignId('agent_id')->nullable()->constrained('customers')->onDelete('cascade');
                $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->enum('day_of_week', ['monday','tuesday','wednesday','thursday','friday','saturday','sunday']);
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Agent Unavailability Table
        if(!Schema::hasTable('agent_unavailabilities')){
            Schema::create('agent_unavailabilities', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_admin_data')->default(0);
                $table->foreignId('agent_id')->nullable()->constrained('customers')->onDelete('cascade');
                $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->date('date');
                $table->enum('unavailability_type', ['full_day', 'specific_time']);
                $table->time('start_time')->nullable(); // null = full day
                $table->time('end_time')->nullable();
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }

        // Agent Extra Time Slots Table
        if(!Schema::hasTable('agent_extra_time_slots')){
            Schema::create('agent_extra_time_slots', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_admin_data')->default(0);
                $table->foreignId('agent_id')->nullable()->constrained('customers')->onDelete('cascade');
                $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->date('date');
                $table->time('start_time');
                $table->time('end_time');
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }

        // Agent Booking Preferences Table
        if(!Schema::hasTable('agent_booking_preferences')){
            Schema::create('agent_booking_preferences', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_admin_data')->default(0);
                $table->foreignId('agent_id')->unique()->nullable()->constrained('customers')->onDelete('cascade');
                $table->foreignId('admin_id')->unique()->nullable()->constrained('users')->onDelete('cascade');
                $table->integer('meeting_duration_minutes')->nullable();
                $table->integer('lead_time_minutes')->comment('Minimum Advance Booking Time')->nullable();
                $table->integer('buffer_time_minutes')->nullable();
                $table->boolean('auto_confirm')->default(false);
                $table->integer('cancel_reschedule_buffer_minutes')->nullable();
                $table->integer('auto_cancel_after_minutes')->nullable();
                $table->text('auto_cancel_message')->nullable();
                $table->integer('daily_booking_limit')->nullable();
                $table->text('availability_types')->nullable()->comment('allowed values: phone, virtual, in_person'); // ["phone","virtual","in_person"]
                $table->boolean('anti_spam_enabled')->default(true);
                $table->string('timezone', 100)->default('UTC');
                $table->unique(['is_admin_data', 'admin_id'], 'unique_data');
                $table->timestamps();
            });
        }

        // Appointments Table
        if(!Schema::hasTable('appointments')){
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_admin_appointment')->default(0);
                $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->foreignId('agent_id')->nullable()->constrained('customers')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('customers')->onDelete('cascade');
                $table->foreignId('property_id')->nullable()->constrained('propertys')->onDelete('set null');
                $table->enum('meeting_type', ['phone','virtual','in_person']);
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->enum('status', [
                    'pending','confirmed','cancelled','rescheduled','completed','auto_cancelled'
                ])->default('pending');
                $table->boolean('is_auto_confirmed')->default(false);
                $table->enum('last_status_updated_by', ['user','agent','system'])->nullable();
                $table->text('notes')->nullable();                
                $table->timestamps();
            });
        }


        // Appointment Reschedules Table
        if(!Schema::hasTable('appointment_reschedules')){
            Schema::create('appointment_reschedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
                $table->dateTime('old_start_at');
                $table->dateTime('old_end_at');
                $table->dateTime('new_start_at');
                $table->dateTime('new_end_at');
                $table->text('reason')->nullable();
                $table->enum('rescheduled_by', ['user','agent','system','admin']);
                $table->timestamps();
            });
        }

        // Appointment Cancellations Table
        if(!Schema::hasTable('appointment_cancellations')){
            Schema::create('appointment_cancellations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
                $table->enum('cancelled_by', ['user','agent','system','admin']);
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }

        // Report User By Agents
        if(!Schema::hasTable('report_user_by_agents')){
            Schema::create('report_user_by_agents', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_admin_data')->default(0);
                $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->foreignId('agent_id')->nullable()->constrained('customers')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('customers')->onDelete('cascade');
                $table->unique(['admin_id', 'agent_id', 'user_id'], 'unique_admin_agent_user');
                $table->enum('status', ['pending','approved','rejected'])->default('pending');
                $table->text('reason');
                $table->timestamps();
            });
        }

        // Block User for all appointments (Have authority only to admin)
        if(!Schema::hasTable('blocked_users_for_appointments')){
            Schema::create('blocked_users_for_appointments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('customers')->onDelete('cascade');
                $table->foreignId('blocked_by_admin_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('report_id')->nullable()->constrained('report_user_by_agents')->onDelete('set null');
                $table->enum('block_type', ['agent_specific', 'global'])->default('agent_specific');
                $table->foreignId('agent_id')->nullable()->constrained('customers')->onDelete('cascade');
                $table->text('reason')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamp('blocked_at')->useCurrent();
                $table->timestamp('unblocked_at')->nullable();
                $table->timestamps();
                
                // Indexes for better performance
                $table->index(['user_id', 'block_type', 'status']);
                $table->index(['agent_id', 'status']);
            });
        }
        
        /********************************************************************* */

        /** Property Views */
        if(!Schema::hasTable('property_views')){
            Schema::create('property_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->references('id')->on('propertys')->onDelete('cascade');
                $table->date('date');
                $table->integer('views')->default(0);
                $table->timestamps();
                $table->unique(['property_id', 'date'], 'unique_property_date');
            });

            $propertyData = Property::where('total_click','>',0)->get();
            foreach($propertyData as $property){
                PropertyView::updateOrCreate(
                    ['property_id' => $property->id, 'date' => Carbon::now()->format('Y-m-d')],
                    ['views' => $property->total_click]
                );
            }
        }

        /** Project Views */
        if(!Schema::hasTable('project_views')){
            Schema::create('project_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->date('date');
                $table->integer('views')->default(0);
                $table->timestamps();
                $table->unique(['project_id', 'date'], 'unique_project_date');
            });

            $projectData = Projects::where('total_click','>',0)->get();
            foreach($projectData as $project){
                ProjectView::updateOrCreate(
                    ['project_id' => $project->id, 'date' => Carbon::now()->format('Y-m-d')],
                    ['views' => $project->total_click]
                );
            }
        }
        /********************************************************************* */

        try {
            $appointmentStatusMailTemplate = "<p>Hello <strong>{user_name}</strong>,</p><p>The status of your appointment request on <strong>{app_name}</strong> has been updated.</p> <p><strong>Appointment Details:</strong>&nbsp;&nbsp;</p> <ul> <ul> <li><strong>Agent:</strong> {agent_name}&nbsp; &nbsp;</li> <li><strong>Customer:&nbsp;</strong>{customer_name}</li> <li><strong>Property:</strong> {property_name}&nbsp; &nbsp;</li> <li><strong>Status:</strong> {meeting_status}&nbsp; &nbsp;</li> <li><strong>Meeting Type:</strong> {meeting_type}&nbsp; &nbsp;</li> <li><strong>Date:</strong> {date}&nbsp; &nbsp;</li> <li><strong>Start Time:</strong> {start_time}&nbsp;&nbsp;</li> <li><strong>End Time:</strong> {end_time} <strong>{start_reason}</strong></li> <li><strong>Reason: </strong>{reason}<strong> (end_reason)</strong></li> </ul> </ul> <p>Thank you,<br />The <strong>{app_name}</strong> Team</p>";
            $newAppointmentMailTemplate = "<p>Hello <strong>{agent_name}</strong>,</p><p>You have received a new appointment request on <strong>{app_name}</strong>.</p> <p><strong>Appointment Details:</strong>&nbsp;&nbsp;</p> <ul> <li><strong>User:</strong> {user_name}&nbsp; &nbsp;</li> <li><strong>Property:</strong> {property_name}&nbsp;&nbsp;</li> <li><strong>Status:</strong> {meeting_status}&nbsp; &nbsp;</li> <li><strong>Meeting Type:</strong> {meeting_type}&nbsp; &nbsp;</li> <li><strong>Date:</strong> {date}&nbsp; &nbsp;</li> <li><strong>Start Time:</strong> {start_time}&nbsp; &nbsp;</li> <li><strong>End Time:</strong> {end_time}</li> </ul> <p><strong>{start_notes} Notes: </strong>{notes}<strong> (end_notes)</strong></p> <p>Thank you,<br />The <strong>{app_name}</strong> Team</p>";
            $appointmentMeetingTypeChangeMailTemplate = '<p data-start="141" data-end="162">Dear <strong>{user_name}</strong>,</p><p data-start="164" data-end="323">Your appointment related to <strong>{property_name}</strong>&nbsp;has been updated.<br data-start="231" data-end="234" />The meeting type has changed from <strong>{old_meeting_type}</strong> to <strong>{new_meeting_type}</strong>.</p> <p data-start="325" data-end="351"><strong data-start="325" data-end="349">Appointment Details:</strong></p> <ul data-start="352" data-end="486"> <li data-start="352" data-end="377"> <p data-start="354" data-end="377">Agent: <strong>{agent_name}</strong></p> </li> <li data-start="378" data-end="409"> <p data-start="380" data-end="409">Customer: <strong>{customer_name}</strong></p> </li> <li data-start="410" data-end="428"> <p data-start="412" data-end="428">Date: <strong>{date}</strong></p> </li> <li data-start="429" data-end="459"> <p data-start="431" data-end="459">Start Time: <strong>{start_time}</strong></p> </li> <li data-start="460" data-end="486"> <p data-start="462" data-end="486">End Time: <strong>{end_time}</strong></p> </li> </ul> <p data-start="488" data-end="582">Please make note of this change. If you have any questions, feel free to contact your agent.</p> <p data-start="584" data-end="623">Best regards,<br data-start="597" data-end="600" />The <strong>{app_name}</strong> Team</p>';

            $settingsData = array(
                'appointment_status_mail_template' => $appointmentStatusMailTemplate,
                'new_appointment_request_mail_template' => $newAppointmentMailTemplate,
                'appointment_meeting_type_change_mail_template' => $appointmentMeetingTypeChangeMailTemplate,
                'gemini_ai_search' => 0
            );
            foreach ($settingsData as $key => $settingData) {
                Setting::updateOrCreate(['type' => $key],['data' => $settingData]);
            }
        }catch(Exception $e){
            Log::error("Something Went Wrong in Appointment Mail Templates Migration");
        }

        /********************************************************************* */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        /** Appointments */
        Schema::dropIfExists('agent_availabilities');
        Schema::dropIfExists('agent_unavailabilities');
        Schema::dropIfExists('agent_booking_preferences');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_reschedules');
        Schema::dropIfExists('appointment_cancellations');
        Schema::dropIfExists('report_user_by_agents');
        Schema::dropIfExists('blocked_users_for_appointments'); 
        Schema::dropIfExists('agent_extra_time_slots');
        /** Property Views */
        Schema::dropIfExists('property_views');
        /** Project Views */
        Schema::dropIfExists('project_views');
        Schema::enableForeignKeyConstraints();
    }
};
