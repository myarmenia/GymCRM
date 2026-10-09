<?php

namespace App\Http\Controllers;

use App\Models\ContactNote;
use App\Models\User;
use App\Services\MembershipSales\ContactNoteOwnershipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactNoteController extends Controller
{
    public function __construct(private readonly ContactNoteOwnershipService $ownership) {}

    public function index(Request $request)
    {
        $viewer = $request->user();
        abort_unless($viewer->hasAnyRole(['sales_manager', 'manager', 'admin', 'super_admin']), 403);

        $filters = $request->validate([
            'sales_manager_id' => ['nullable', 'integer'],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $managers = User::query()
            ->role('sales_manager')
            ->where('gym_id', $viewer->gym_id)
            ->orderBy('name')
            ->orderBy('surname')
            ->get(['id', 'name', 'surname']);

        $query = ContactNote::query()
            ->with('user:id,name,surname')
            ->whereHas('user', function (Builder $query) use ($viewer) {
                $query->role('sales_manager')->where('gym_id', $viewer->gym_id);
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('contact_notes as earlier')
                    ->whereColumn('earlier.user_id', 'contact_notes.user_id')
                    ->whereColumn('earlier.phone_number', 'contact_notes.phone_number')
                    ->where(function ($query) {
                        $query->whereColumn('earlier.created_at', '<', 'contact_notes.created_at')
                            ->orWhere(function ($query) {
                                $query->whereColumn('earlier.created_at', 'contact_notes.created_at')
                                    ->whereColumn('earlier.id', '<', 'contact_notes.id');
                            });
                    });
            });

        if ($viewer->hasRole('sales_manager')) {
            $query->where('contact_notes.user_id', $viewer->id);
        } elseif (!empty($filters['sales_manager_id'])) {
            $query->where('contact_notes.user_id', $filters['sales_manager_id']);
        }

        if (!empty($filters['phone_number'])) {
            $query->where('contact_notes.phone_number', 'like', '%'.$filters['phone_number'].'%');
        }

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $query->whereExists(function ($query) use ($filters) {
                $query->selectRaw('1')
                    ->from('contact_notes as dated')
                    ->whereColumn('dated.user_id', 'contact_notes.user_id')
                    ->whereColumn('dated.phone_number', 'contact_notes.phone_number')
                    ->when(!empty($filters['date_from']), fn ($query) => $query->whereDate('dated.created_at', '>=', $filters['date_from']))
                    ->when(!empty($filters['date_to']), fn ($query) => $query->whereDate('dated.created_at', '<=', $filters['date_to']));
            });
        }

        $contacts = $query->orderByDesc(function ($query) {
                $query->selectRaw('MAX(recent.created_at)')
                    ->from('contact_notes as recent')
                    ->whereColumn('recent.user_id', 'contact_notes.user_id')
                    ->whereColumn('recent.phone_number', 'contact_notes.phone_number');
            })
            ->orderByDesc('contact_notes.created_at')
            ->orderByDesc('contact_notes.id')
            ->paginate(15)
            ->withQueryString();

        $roots = $contacts->getCollection();
        $notes = $roots->isEmpty() ? collect() : ContactNote::query()
            ->where(function ($query) use ($roots) {
                foreach ($roots as $root) {
                    $query->orWhere(fn ($query) => $query
                        ->where('user_id', $root->user_id)
                        ->where('phone_number', $root->phone_number));
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'phone_number', 'note', 'created_at'])
            ->groupBy(fn ($note) => $note->user_id.'|'.$note->phone_number);

        $contacts->setCollection($roots->map(function ($root) use ($notes) {
            $root->setAttribute('children', ($notes[$root->user_id.'|'.$root->phone_number] ?? collect())
                ->where('id', '!=', $root->id)
                ->values());
            return $root;
        }));

        return Inertia::render('ContactNotes/Index', [
            'contacts' => $contacts,
            'salesManagers' => $viewer->hasRole('sales_manager') ? [] : $managers,
            'filters' => $filters,
            'canCreate' => $viewer->hasRole('sales_manager'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasRole('sales_manager'), 403);

        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'note' => ['required', 'string', 'max:10000'],
        ]);

        $phone = trim($data['phone_number']);
        $note = trim($data['note']);
        if ($phone === '' || $note === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $phone === '' ? 'phone_number' : 'note' => __('validation.required', ['attribute' => $phone === '' ? 'phone_number' : 'note']),
            ]);
        }

        $this->ownership->create($request->user(), $phone, $note);

        return redirect()->route('contact-notes.index', ['locale' => app()->getLocale()]);
    }

    public function addNote(Request $request, string $locale, ContactNote $contactNote)
    {
        abort_unless($request->user()->hasRole('sales_manager') && $contactNote->user_id === $request->user()->id, 403);

        $data = $request->validate(['note' => ['required', 'string', 'max:10000']]);
        $note = trim($data['note']);
        if ($note === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['note' => __('validation.required', ['attribute' => 'note'])]);
        }

        $this->ownership->create($request->user(), $contactNote->phone_number, $note);

        return back();
    }
}
