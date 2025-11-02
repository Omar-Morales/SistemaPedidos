<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\View\View;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
    $user = $request->user();
    $sessions = $this->getSessions($request);

    return view('profile.edit', compact('user', 'sessions'));
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
    $user->fill($request->validated());

    if ($request->user()->isDirty('email')) {
        $user->email_verified_at = null;
    }

    $user->save();

    // Misma lógica que en el provider
    $fields = ['name', 'email', 'phone', 'photo'];

    $filled = collect($fields)->filter(function ($field) use ($user) {
        if ($field === 'photo') {
            return $user->photo && Storage::disk('public')->exists($user->photo);
        }
        return !empty($user->$field);
    })->count();

    $percent = round(($filled / count($fields)) * 100);

    return response()->json([
        'message' => 'Perfil actualizado con éxito.',
        'user' => [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'photo' => $user->photo && Storage::disk('public')->exists($user->photo) ? $user->photo : null,
        ],
        'percent' => $percent,
    ]);
    }


    public function updatePassword(Request $request)
    {
    $request->validate([
        'current_password' => ['required', 'current_password'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    // Actualiza la contraseña del usuario
    $user = $request->user();
    $user->password = Hash::make($request->password);
    $user->save();

    // Cerrar sesión y regenerar el token CSRF
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // Asegurarse de que no quede en memoria la sesión anterior
    session()->flush();

    // Redirigir al login
    return response()->json([
        'message' => 'Contraseña actualizada con éxito. Por favor inicia sesión nuevamente.'
    ], 200);
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048']
        ]);

        $user = $request->user();

        // Elimina la anterior si existe
        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        $path = $request->file('photo')->store('users', 'public');
        $user->photo = $path;
        $user->save();

        // Cálculo del porcentaje (como en AppServiceProvider)
        $fields = ['name', 'email', 'phone', 'photo'];
        $filled = collect($fields)->filter(function ($field) use ($user) {
            if ($field === 'photo') {
                return $user->photo && Storage::disk('public')->exists($user->photo);
            }
            return !empty($user->$field);
        })->count();

        $percent = round(($filled / count($fields)) * 100);

        return response()->json([
            'message' => 'Foto actualizada',
            'photoUrl' => asset("storage/{$path}"),
            'percent' => $percent,
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function destroySession($id)
    {
    $session = DB::table('sessions')->where('id', $id)->first();

    // Verifica que la sesión exista y que pertenezca al usuario logueado
    if ($session && $session->user_id == auth()->id()) {
        DB::table('sessions')->where('id', $id)->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    return response()->json(['error' => 'Sesión no válida.'], 403);
    }

public function destroySessions(Request $request)
{
    $request->validate(['password' => ['required', 'current_password']]);

    $current = $request->session()->getId();

    DB::table('sessions')
        ->where('user_id', $request->user()->id)
        ->where('id', '!=', $current)
        ->delete();

    return response()->json(['message' => 'Otras sesiones cerradas correctamente.']);
}

    /**
     * Obtener sesiones activas del usuario.
     */
private function getSessions(Request $request): array
{
    if (config('session.driver') !== 'database') {
        return [];
    }

    $sessions = DB::table('sessions')
        ->where('user_id', $request->user()->id)
        ->orderBy('last_activity', 'desc')
        ->get();

    return $sessions->map(function ($session) use ($request) {
        return [
        'id' => $session->id, // <- Agregado aquí
        'agent' => $session->user_agent,
        'ip_address' => $session->ip_address,
        'last_active' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
        'current' => $session->id === $request->session()->getId(),
        ];
    })->toArray();
}

}
