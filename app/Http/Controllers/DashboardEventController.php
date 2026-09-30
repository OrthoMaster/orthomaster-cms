<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DashboardEventController extends Controller
{
    /**
     * Liste des congrès et de leurs éditions.
     * GET /dashboard/events
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $events = DB::table('events')
            ->select('id', 'name', 'image', 'code', 'active_flg')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->orderBy('name')
            ->get()
            ->map(fn ($event) => (object) [
                'id'         => $event->id,
                'name'       => $event->name,
                'code'       => $event->code,
                'active_flg' => (bool) $event->active_flg,
                'image'      => $this->imageUrl($event->image),
            ]);

        return view('dashboard.events.index', [
            'events'   => $events,
            'editions' => $this->editionsByEvent($events->pluck('id')->all()),
            'search'   => $search,
        ]);
    }

    /**
     * Éditions regroupées par congrès, les plus récentes d'abord.
     */
    private function editionsByEvent(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        return DB::table('past_events')
            ->select('id', 'id_event', 'edition_year', 'complete_flg', 'active_flg')
            ->whereIn('id_event', $eventIds)
            ->orderByDesc('edition_year')
            ->get()
            ->groupBy('id_event')
            ->map(fn ($rows) => $rows->values())
            ->all();
    }

    /**
     * URL publique d'un visuel stocké en chemin relatif.
     */
    private function imageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk('s3')->url($path);
    }

        /* ===================== PRÉPARATION D'UNE ÉDITION ===================== */

    /** Nombre de participants affichés par page */
    private const PARTICIPANTS_PER_PAGE = 25;

    /** Longueur minimale d'une recherche d'utilisateur */
    private const SEARCH_MIN_LENGTH = 3;

    /**
     * Écran de préparation : sponsors, participants et paramètres.
     * GET /dashboard/events/{pastEvent}/prepare
     */
    public function prepare(Request $request, int $pastEvent)
    {
        $edition = $this->editionOrFail($pastEvent);

        return view('dashboard.events.prepare', [
            'edition'      => $edition,
            'sponsors'     => $this->sponsorsOf($edition->id),
            'participants' => $this->participantsOf($edition->id),
            'settings'     => $this->settingsOf($edition->id),
        ]);
    }

    /**
     * Rattache un sponsor à l'édition.
     * POST /dashboard/events/{pastEvent}/prepare/sponsors
     */
    public function addSponsor(Request $request, int $pastEvent)
    {
        $edition = $this->editionOrFail($pastEvent);

        $data = $request->validate([
            'ads_company_id' => ['required', 'integer', 'exists:ads_companies,id'],
        ]);

        // Un doublon est sans conséquence : la clé unique l'écarte
        DB::table('past_event_sponsors')->insertOrIgnore([
            'past_event_id'  => $edition->id,
            'ads_company_id' => $data['ads_company_id'],
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return redirect()
            ->route('dashboard.events.prepare', $edition->id)
            ->with('message', 'Sponsor ajouté.')
            ->with('alert-type', 'success');
    }

    /**
     * DELETE /dashboard/events/{pastEvent}/prepare/sponsors/{company}
     */
    public function removeSponsor(int $pastEvent, int $company)
    {
        $edition = $this->editionOrFail($pastEvent);

        DB::table('past_event_sponsors')
            ->where('past_event_id', $edition->id)
            ->where('ads_company_id', $company)
            ->delete();

        return redirect()
            ->route('dashboard.events.prepare', $edition->id)
            ->with('message', 'Sponsor retiré.')
            ->with('alert-type', 'success');
    }

    /**
     * POST /dashboard/events/{pastEvent}/prepare/participants
     */
    public function addParticipant(Request $request, int $pastEvent)
    {
        $edition = $this->editionOrFail($pastEvent);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        DB::table('past_event_users')->insertOrIgnore([
            'past_event_id' => $edition->id,
            'user_id'       => $data['user_id'],
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return redirect()
            ->route('dashboard.events.prepare', $edition->id)
            ->withFragment('participants')
            ->with('message', 'Participant ajouté.')
            ->with('alert-type', 'success');
    }

    /**
     * DELETE /dashboard/events/{pastEvent}/prepare/participants/{user}
     */
    public function removeParticipant(int $pastEvent, int $user)
    {
        $edition = $this->editionOrFail($pastEvent);

        DB::table('past_event_users')
            ->where('past_event_id', $edition->id)
            ->where('user_id', $user)
            ->delete();

        return redirect()
            ->route('dashboard.events.prepare', $edition->id)
            ->withFragment('participants')
            ->with('message', 'Participant retiré.')
            ->with('alert-type', 'success');
    }

    /**
     * POST /dashboard/events/{pastEvent}/prepare/settings
     */
    public function saveSettings(Request $request, int $pastEvent)
    {
        $edition = $this->editionOrFail($pastEvent);

        $data = $request->validate([
            'ads_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'ads_months'     => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        DB::table('past_event_settings')->updateOrInsert(
            ['past_event_id' => $edition->id],
            [
                'ads_multiplier' => $data['ads_multiplier'],
                'ads_months'     => $data['ads_months'],
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );

        return redirect()
            ->route('dashboard.events.prepare', $edition->id)
            ->withFragment('settings')
            ->with('message', 'Paramètres enregistrés.')
            ->with('alert-type', 'success');
    }

    /**
     * Autocomplete entreprises.
     * GET /dashboard/events/{pastEvent}/prepare/search-companies
     */
    public function searchCompanies(Request $request, int $pastEvent)
    {
        $edition = $this->editionOrFail($pastEvent);
        $term    = trim((string) $request->query('q', ''));

        $results = DB::table('ads_companies')
            ->select('id', 'name as text')
            ->when($term !== '', fn($q) => $q->where('name', 'like', '%' . $term . '%'))
            // Les sponsors déjà inscrits n'ont pas à être proposés
            ->whereNotIn('id', function ($sub) use ($edition) {
                $sub->from('past_event_sponsors')
                    ->select('ads_company_id')
                    ->where('past_event_id', $edition->id);
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($results);
    }

    /**
     * Autocomplete utilisateurs. La base est volumineuse : la recherche
     * ne démarre qu'à partir de trois caractères.
     * GET /dashboard/events/{pastEvent}/prepare/search-users
     */
    public function searchUsers(Request $request, int $pastEvent)
    {
        $edition = $this->editionOrFail($pastEvent);
        $term    = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < self::SEARCH_MIN_LENGTH) {
            return response()->json([]);
        }

        $results = DB::table('users')
            ->select('id', 'email', 'name', 'first_name')
            ->where(function ($q) use ($term) {
                $q->where('email', 'like', '%' . $term . '%')
                    ->orWhere('name', 'like', '%' . $term . '%')
                    ->orWhere('first_name', 'like', '%' . $term . '%');
            })
            ->whereNotIn('id', function ($sub) use ($edition) {
                $sub->from('past_event_users')
                    ->select('user_id')
                    ->where('past_event_id', $edition->id);
            })
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn($u) => [
                'id'    => $u->id,
                'text'  => $this->userLabel($u),
                'email' => $u->email,
            ]);

        return response()->json($results);
    }

    /**
     * Clôture l'édition : la préparation devient définitive.
     * POST /dashboard/events/{pastEvent}/prepare/complete
     */
    public function completeEdition(int $pastEvent)
    {
        $edition = $this->editionOrFail($pastEvent);

        DB::table('past_events')
            ->where('id', $edition->id)
            ->update(['complete_flg' => 1]);

        return redirect()
            ->route('dashboard.events.prepare', $edition->id)
            ->with('message', 'Édition clôturée.')
            ->with('alert-type', 'success');
    }

    /**
     * Édition, accompagnée du congrès dont elle relève.
     */
    private function editionOrFail(int $id)
    {
        $edition = DB::table('past_events as pe')
            ->join('events as e', 'e.id', '=', 'pe.id_event')
            ->select(
                'pe.id',
                'pe.id_event',
                'pe.edition_year',
                'pe.complete_flg',
                'e.name as event_name',
                'e.code as event_code'
            )
            ->where('pe.id', $id)
            ->first();

        abort_if(!$edition, 404);

        return $edition;
    }

    private function sponsorsOf(int $editionId)
    {
        return DB::table('past_event_sponsors as s')
            ->join('ads_companies as c', 'c.id', '=', 's.ads_company_id')
            ->select('c.id', 'c.name', 's.created_at')
            ->where('s.past_event_id', $editionId)
            ->orderBy('c.name')
            ->get();
    }

    private function participantsOf(int $editionId)
    {
        return DB::table('past_event_users as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->select('u.id', 'u.email', 'u.name', 'u.first_name')
            ->where('p.past_event_id', $editionId)
            ->orderBy('u.name')
            ->paginate(self::PARTICIPANTS_PER_PAGE)
            ->withQueryString();
    }

    /**
     * Paramètres de l'édition, valeurs par défaut si rien n'a été saisi.
     */
    private function settingsOf(int $editionId)
    {
        $row = DB::table('past_event_settings')
            ->where('past_event_id', $editionId)
            ->first();

        return (object) [
            'ads_multiplier' => (float) ($row->ads_multiplier ?? 2),
            'ads_months'     => $row->ads_months ?? 1,
            'saved'          => (bool) $row,
        ];
    }

    private function userLabel($user): string
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->name ?? ''));

        return $name !== ''
            ? $name . ' — ' . $user->email
            : $user->email;
    }
}
