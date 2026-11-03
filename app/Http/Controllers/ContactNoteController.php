<?php

namespace App\Http\Controllers;

use App\Models\ContactNote;
use App\Models\ContactProfile;
use App\Models\Person;
use App\Models\User;
use App\Services\MembershipSales\ContactNoteOwnershipService;
use App\Services\MembershipSales\ContactNotePhone;
use App\Services\MembershipSales\ContactProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ContactNoteController extends Controller
{
    public function __construct(
        private readonly ContactNoteOwnershipService $ownership,
        private readonly ContactProfileService $profiles,
    ) {}

    public function index(Request $request)
    {
        $viewer = $request->user();
        abort_unless($viewer->gym_id !== null, 403);
        $filters = $request->validate([
            'sales_manager_id' => ['nullable', 'integer'],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $managers = User::query()->role('sales_manager')->where('gym_id', $viewer->gym_id)
            ->orderBy('name')->orderBy('surname')->get(['id', 'name', 'surname']);

        $query = ContactProfile::query()->with([
            'members' => fn ($members) => $members->where('gym_id', $viewer->gym_id)
                ->whereIn('user_id', $managers->modelKeys()),
            'user:id,name,surname', 'members.creator:id,name,surname',
            'members.editor:id,name,surname', 'members.notes.writer:id,name,surname',
            'members.notes.user:id,name,surname',
        ])
            ->where('gym_id', $viewer->gym_id)
            ->whereIn('user_id', $managers->modelKeys())
            ->where('is_primary', true);
        if (! empty($filters['sales_manager_id'])) {
            $query->where('user_id', $filters['sales_manager_id']);
        }
        if (! empty($filters['phone_number'])) {
            $query->whereHas('members', fn ($members) => $members
                ->where('gym_id', $viewer->gym_id)
                ->whereIn('user_id', $managers->modelKeys())
                ->where('phone_number', 'like', '%'.$filters['phone_number'].'%'));
        }
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $query->whereHas('members', fn ($members) => $members
                ->where('gym_id', $viewer->gym_id)
                ->whereIn('user_id', $managers->modelKeys())
                ->whereHas('notes', fn ($notes) => $notes
                    ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
                    ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))));
        }

        $contacts = $query->orderByDesc(ContactNote::query()->selectRaw('MAX(contact_notes.created_at)')
                ->join('contact_profiles as member_profiles', 'member_profiles.id', '=', 'contact_notes.contact_profile_id')
                ->whereColumn('member_profiles.group_uuid', 'contact_profiles.group_uuid')
                ->whereColumn('member_profiles.gym_id', 'contact_profiles.gym_id')
                ->whereColumn('member_profiles.user_id', 'contact_profiles.user_id'))
                ->orderByDesc('contact_profiles.created_at')->orderByDesc('contact_profiles.id')
                ->paginate(15)->withQueryString();
        $members = $contacts->getCollection()->flatMap(fn (ContactProfile $group) => $group->members);
        $phoneVariants = $members->flatMap(fn (ContactProfile $member) => ContactNotePhone::variants($member->phone_number))
            ->unique()->values()->all();
        $personsByPhone = $phoneVariants === [] ? collect() : Person::query()
            ->whereHas('gyms', fn ($gyms) => $gyms->where('gyms.id', $viewer->gym_id))
            ->whereIn(DB::raw(ContactNotePhone::normalizedSql('phone')), $phoneVariants)
            ->whereIn('type', ['visitor', 'guest', 'newcomer'])
            ->get(['id', 'phone', 'is_blocked'])
            ->keyBy(fn (Person $person) => ContactNotePhone::canonical((string) $person->phone));
        $members->each(function (ContactProfile $member) use ($personsByPhone): void {
            $person = $personsByPhone->get(ContactNotePhone::canonical($member->phone_number));
            $member->setAttribute('existing_person_id', $person?->id);
            $member->setAttribute('existing_person_blocked', (bool) $person?->is_blocked);
        });
        $contacts->getCollection()->each(function (ContactProfile $group): void {
            $primary = $group->members->firstWhere('id', $group->id);
            $group->setAttribute('existing_person_id', $primary?->existing_person_id);
            $group->setAttribute('existing_person_blocked', $primary?->existing_person_blocked ?? false);
        });

        return Inertia::render('ContactNotes/Index', [
            'contacts' => $contacts,
            'salesManagers' => $managers,
            'filters' => $filters,
            'canCreate' => $viewer->hasRole('sales_manager'),
            'canAddNote' => true,
            'canShowPersonActions' => $viewer->hasAnyRole(['sales_manager', 'manager', 'admin', 'super_admin']),
            'canSellMembership' => $viewer->hasAnyRole(['sales_manager', 'manager']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasRole('sales_manager'), 403);
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'note' => ['required', 'string', 'max:10000'],
            ...$this->detailRules(),
        ]);
        $this->ownership->create($request->user(), $data['phone_number'], trim($data['note']), $data);
        return redirect()->route('contact-notes.index', ['locale' => app()->getLocale()]);
    }

    public function addRelated(Request $request, string $locale, ContactProfile $contactProfile)
    {
        abort_unless($contactProfile->is_primary, 404);
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:10000'],
            ...$this->detailRules(),
        ]);
        $this->profiles->addRelated($request->user(), $contactProfile, $data);
        return back();
    }

    public function linkExisting(Request $request, string $locale, ContactProfile $contactProfile)
    {
        abort_unless($contactProfile->is_primary, 404);
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'relationship' => ['nullable', 'string', 'max:255'],
        ]);
        $this->profiles->linkExisting($request->user(), $contactProfile, $data);
        return back();
    }

    public function updateProfile(Request $request, string $locale, ContactProfile $contactProfile)
    {
        $this->profiles->update($request->user(), $contactProfile, $request->validate($this->detailRules()));
        return back();
    }

    public function addProfileNote(Request $request, string $locale, ContactProfile $contactProfile)
    {
        abort_unless($request->user()->gym_id !== null
            && (int) $contactProfile->gym_id === (int) $request->user()->gym_id, 403);
        $data = $request->validate(['note' => ['required', 'string', 'max:10000']]);
        $this->ownership->addToProfile($request->user(), $contactProfile, trim($data['note']));
        return back();
    }

    public function addNote(Request $request, string $locale, ContactNote $contactNote)
    {
        abort_unless($request->user()->gym_id !== null
            && (int) ($contactNote->profile?->gym_id ?? $contactNote->user?->gym_id) === (int) $request->user()->gym_id
            && ($contactNote->profile !== null || $request->user()->hasRole('sales_manager')), 403);
        $data = $request->validate(['note' => ['required', 'string', 'max:10000']]);
        if ($contactNote->profile) {
            $this->ownership->addToProfile($request->user(), $contactNote->profile, trim($data['note']));
        } else {
            $this->ownership->create($request->user(), $contactNote->phone_number, trim($data['note']));
        }
        return back();
    }

    public function lookup(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['manager', 'sales_manager', 'super_admin', 'admin']), 403);
        $data = $request->validate(['phone' => ['required', 'string', 'max:50']]);
        $gymId = (int) $request->user()->gym_id;
        $phone = $data['phone'];
        $profile = $this->profiles->forPhone($gymId, $phone);
        $variants = ContactNotePhone::variants($phone);
        $person = $variants === [] ? null : Person::query()
            ->whereHas('gyms', fn ($query) => $query->where('gyms.id', $gymId))
            ->whereIn(DB::raw(ContactNotePhone::normalizedSql('phone')), $variants)
            ->first(['id', 'phone']);

        return response()->json([
            'profile' => $profile ? [
                ...$profile->only(['uuid', 'phone_number', 'name', 'surname', 'email', 'gender', 'relationship', 'group_uuid']),
                'birth_date' => $profile->birth_date?->format('Y-m-d'),
            ] : null,
            'existing_person_id' => $person?->id,
        ]);
    }

    private function detailRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'relationship' => ['nullable', 'string', 'max:255'],
        ];
    }
}
