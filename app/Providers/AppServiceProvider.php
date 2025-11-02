<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
     View::composer(['partials.header', 'profile.edit'], function ($view) {
        $user = Auth::user();

        $photoPath = $user->photo;
        $hasCustomPhoto = $photoPath && Storage::disk('public')->exists($photoPath);

        $photoUrl = $hasCustomPhoto
            ? asset("storage/{$photoPath}")
            : asset("assets/images/users.jpg");

        // Incluir siempre 'photo' como campo requerido
        $fields = ['name', 'email', 'phone', 'photo'];

        // Contar solo los campos realmente llenos
        $filled = collect($fields)->filter(function ($field) use ($user) {
            if ($field === 'photo') {
                return $user->photo && Storage::disk('public')->exists($user->photo);
            }
            return !empty($user->$field);
        })->count();

        $percent = round(($filled / count($fields)) * 100);

        $view->with(compact('user', 'photoUrl', 'percent'));
    });
    }
}
