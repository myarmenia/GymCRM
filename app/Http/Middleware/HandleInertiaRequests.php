<?php

namespace App\Http\Middleware;

use App\Support\SupportedLocales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use App\Services\Notifications\NotificationService;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // return [
        //     ...parent::share($request),
        //     'auth' => [
        //         'user' => $request->user(),
        //     ],
        // ];
        $user = Auth::user();

        $gym = $user?->gym;

        $langs = $gym
            ? $gym->languages()
                ->wherePivot('active', true)
                ->pluck('code')
                ->values()
                ->all()
            : SupportedLocales::CODES;

        if ($langs === []) {
            $langs = ['hy'];
        }

        $lang = app()->getLocale();

        $roleName = $user?->roles?->first()?->name;

        $name = request()->route()?->getName();
        $group = $name ? explode('.', $name)[0] : 'app';

        // $file = lang_path($lang . '/' . $group . ".json");
        // $formFile = lang_path($lang . "/form.json");
        // $navbarFile = lang_path($lang . "/navbar.json");
        // $app = lang_path($lang . "/app.json");

        // ✅ ФРОНТЕНД: JSON из resources/lang/
        $basePath = resource_path("lang/{$lang}");
        $file = "{$basePath}/{$group}.json";
        $formFile = "{$basePath}/form.json";
        $navbarFile = "{$basePath}/navbar.json";
        $app = "{$basePath}/app.json";

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'surname' => $user->surname,
                    'email' => $user->email,
                    'gym_id' => $user->gym_id,
                    'client_id' => $user->gym_id,
                    'role' => $roleName,
                    'role_name' => $roleName,
                    'roles' => $user->roles,
                    'verified' => $user->email_verified_at,
                ] : null,
            ],

            'client_id' => $user?->gym_id,

            'notificationUnreadCount' => $user
                ? app(NotificationService::class)->unreadCount($user)
                : 0,

            'translations' => [
                'form' => File::exists($formFile) ? File::json($formFile) : [],
                'page' => File::exists($file) ? File::json($file) : [],
                'navbar' => File::exists($navbarFile) ? File::json($navbarFile) : [],
                // 'modal' => File::exists($modal) ? File::json($modal) : [],
                'app' => File::exists($app) ? File::json($app) : [],

            ],
            'locale' => $lang,
            'lang' => $lang,
            'langs' => $langs,
            'err' => function () use ($request) {
                return $request->session()->get('errors')
                    ? $request->session()->get('errors')->getBag('default')->toArray()
                    : (object) [];
            },
        ];
    }
}
