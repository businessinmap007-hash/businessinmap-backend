<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ReminderPreference;
use Illuminate\Http\Request;

/**
 * A user's reminder lead times: how long before an appointment (two reminders)
 * and before an agenda item (medication dose / task) they want to be notified.
 */
class ReminderPreferenceController extends Controller
{
    /** GET /api/v2/me/reminder-preferences — mine (defaults if unset). */
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => ['reminder_preferences' => $this->serialize(ReminderPreference::forUser((int) $request->user()->id))],
        ]);
    }

    /** PUT /api/v2/me/reminder-preferences — set mine. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'appointment_first_lead_minutes' => ['required', 'integer', 'min:5', 'max:' . ReminderPreference::MAX_FIRST_LEAD],
            // null disables the closer reminder.
            'appointment_second_lead_minutes' => ['nullable', 'integer', 'min:5', 'max:' . ReminderPreference::MAX_SECOND_LEAD],
            'agenda_lead_minutes' => ['required', 'integer', 'min:0', 'max:' . ReminderPreference::MAX_AGENDA_LEAD],
        ]);

        // The closer reminder must actually be closer than the first.
        if (($data['appointment_second_lead_minutes'] ?? null) !== null
            && $data['appointment_second_lead_minutes'] >= $data['appointment_first_lead_minutes']) {
            return response()->json([
                'success' => false,
                'message' => __('يجب أن يكون التذكير الثاني أقرب من الأول.'),
                'errors' => ['appointment_second_lead_minutes' => [__('يجب أن يكون التذكير الثاني أقرب من الأول.')]],
            ], 422);
        }

        $row = ReminderPreference::query()->updateOrCreate(
            ['user_id' => (int) $request->user()->id],
            [
                'appointment_first_lead_minutes' => (int) $data['appointment_first_lead_minutes'],
                'appointment_second_lead_minutes' => $data['appointment_second_lead_minutes'] ?? null,
                'agenda_lead_minutes' => (int) $data['agenda_lead_minutes'],
            ],
        );

        return response()->json([
            'success' => true,
            'message' => __('تم حفظ تفضيلات التذكير.'),
            'data' => ['reminder_preferences' => $this->serialize($row)],
        ]);
    }

    private function serialize(ReminderPreference $row): array
    {
        return [
            'appointment_first_lead_minutes' => $row->firstLead(),
            'appointment_second_lead_minutes' => $row->secondLead(),
            'agenda_lead_minutes' => $row->agendaLead(),
        ];
    }
}
