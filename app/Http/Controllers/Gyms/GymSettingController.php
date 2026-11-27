<?php

namespace App\Http\Controllers\Gyms;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GymSettingController extends Controller
{
    public function create(string $locale, Gym $gym): Response|RedirectResponse
    {
        $this->authorizeOwner();

        if ($gym->setting()->exists()) {
            return redirect()->route('gym.settings.edit', ['locale' => $locale, 'gym' => $gym->id]);
        }

        return Inertia::render('Gyms/Settings', [
            'gym' => $gym->only('id', 'name'),
            'mode' => GymSetting::AUTO_UP_TO_TWO,
            'startMode' => GymSetting::SALE_DATE,
            'isCreate' => true,
        ]);
    }

    public function store(Request $request, string $locale, Gym $gym): RedirectResponse
    {
        $this->authorizeOwner();
        $data = $this->validated($request);

        if ($gym->setting()->exists()) {
            return redirect()->route('gym.settings.edit', ['locale' => $locale, 'gym' => $gym->id]);
        }

        $gym->setting()->create($data);
        if ((int) $request->session()->get('pending_gym_settings_id') === $gym->id) {
            $request->session()->forget('pending_gym_settings_id');
        }

        return redirect()->route('gym.list', ['locale' => $locale])
            ->with('success', 'Gym settings saved successfully.');
    }

    public function edit(string $locale, Gym $gym): Response
    {
        $this->authorizeOwner();

        return Inertia::render('Gyms/Settings', [
            'gym' => $gym->only('id', 'name'),
            'mode' => $gym->setting?->membership_selection_mode ?? GymSetting::AUTO_UP_TO_TWO,
            'startMode' => $gym->setting?->membership_start_mode ?? GymSetting::SALE_DATE,
            'isCreate' => false,
        ]);
    }

    public function update(Request $request, string $locale, Gym $gym): RedirectResponse
    {
        $this->authorizeOwner();
        $data = $this->validated($request);
        $gym->setting()->updateOrCreate([], $data);

        return redirect()->route('gym.list', ['locale' => $locale])
            ->with('success', 'Gym settings updated successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'membership_selection_mode' => ['required', 'string', Rule::in(GymSetting::MODES)],
            'membership_start_mode' => ['sometimes', 'required', 'string', Rule::in(GymSetting::START_MODES)],
        ]);
    }

    private function authorizeOwner(): void
    {
        abort_unless(Auth::user()?->hasRole('owner'), 403);
    }
}
