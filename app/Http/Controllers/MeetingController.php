<?php

namespace App\Http\Controllers;

use Spatie\GoogleCalendar\Event;
use Carbon\Carbon;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Illuminate\Support\Facades\Log;

class MeetingController extends Controller
{
    public function createMeeting(string $staffEmail, string $client, string $startDateTime)
    {
        try {
            // Test
            $staffEmail = 'tien.nguyen@orthomaster.fr';

            config(['google-calendar.calendar_id' => $staffEmail]);
            $event = new Event;
            $event->name = 'Visio: ' . $client;
            $event->description = "Cet événement a été créé automatiquement. Veuillez ajouter le client à cet événement afin qu'ils puissent recevoir les e-mails.";

            $start = \Carbon\Carbon::parse($startDateTime);
            $event->startDateTime = $start;
            $event->endDateTime = $start->copy()->addMinutes(45);

            $conferenceData = new ConferenceData();
            $conferenceRequest = new CreateConferenceRequest();
            $conferenceRequest->setRequestId('meet-assign-' . time());
            $conferenceData->setCreateRequest($conferenceRequest);

            $event->googleEvent->setConferenceData($conferenceData);

            // nó trả về MỘT instance Event MỚI có id của Google. Phải gán lại để đọc được id.
            $event = $event->save(null, [
                'conferenceDataVersion' => 1,
            ]);

            return $event->id;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['staff-meeting', $staffEmail]);
            return false;
        }
    }

    public function updateMeetingTime(string $staffEmail, string $googleEventId, string $newDate)
    {
        try {
            // Test
            $staffEmail = 'tien.nguyen@orthomaster.fr';

            config(['google-calendar.calendar_id' => $staffEmail]);
 
            $event = Event::find($googleEventId);
            if (!$event) {
                return false;
            }
 
            $start = \Carbon\Carbon::parse($newDate);
            $event->startDateTime = $start;
            $event->endDateTime = $start->copy()->addMinutes(45);
 
            $event->save();
 
            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['staff-meeting', 'change-date', $staffEmail]);
            return false;
        }
    }
}
