<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
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

        // $lang = in_array(request()->segment(1), ['hy', 'ru', 'en']) ? request()->segment(1) : 'hy';
        // $langs = ['en', 'ru', 'hy'];

        $gym = $user?->gym;

        $gymLangs = $gym
            ? $gym->languages()->wherePivot('active', true)->get()
            : collect();

        $langs = $gymLangs->pluck('code')->values()->toArray();

        if (empty($langs)) {
            $langs = ['hy'];
        }


        $langFromUrl = request()->segment(1);

        $lang = in_array($langFromUrl, $langs)
            ? $langFromUrl
            : $langs[0];


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
                    'roles' => $user->roles,
                    'verified' => $user->email_verified_at,
                ] : null,
            ],
            'translations' => [
                'form' => File::exists($formFile) ? File::json($formFile) : [],
                'page' => File::exists($file) ? File::json($file) : [],
                'navbar' => File::exists($navbarFile) ? File::json($navbarFile) : [],
                // 'modal' => File::exists($modal) ? File::json($modal) : [],
                'app' => File::exists($app) ? File::json($app) : [],

            ],
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
