@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('content')
<div class="page-content">
  <div class="container-fluid">
    <div class="row">
      <!-- Perfil lateral -->
      <div class="col-xl-4">
        <div class="card overflow-hidden">
          <div class="position-relative">
            <img src="{{ asset('assets/images/bg-home.jpg') }}" alt="" class="img-fluid" style="height: 140px; width: 100%; object-fit: cover;">
            <div class="position-absolute top-100 start-50 translate-middle">
              <div class="profile-user position-relative">
                <img src="{{ $photoUrl }}" alt="Avatar de {{ $user->name }}" class="rounded-circle avatar-xl">
                <div class="avatar-xs p-0 rounded-circle profile-photo-edit">
                  <input id="profile-img-file-input" type="file" class="profile-img-file-input" accept="image/jpeg,image/png,image/jpg,image/gif">
                  <label for="profile-img-file-input" class="profile-photo-edit avatar-xs">
                    <span class="avatar-title rounded-circle bg-light text-body">
                      <i class="ri-camera-fill"></i>
                    </span>
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body pt-5 mt-4 text-center">
            <h5 class="fs-16 mb-1">{{$user->name }}</h5>
            <p class="text-muted mb-0">{{$user->role->name ?? 'Usuario' }}</p>
            <p class="text-muted">Perú</p>
            <h6 class="text-muted text-uppercase mb-3">Completado del Perfil</h6>
            <div class="progress animated-progress custom-progress progress-label">
              <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%;">
                <div class="label">{{ round($percent) }}%</div>
              </div>
            </div>
          </div>

<div class="card-body border-top">
  <h6 class="text-muted text-uppercase mb-3">Portafolio</h6>

  <div class="mb-3 d-flex">
    <div class="avatar-xs d-block flex-shrink-0 me-3">
      <span class="avatar-title rounded-circle fs-16 bg-dark text-light">
        <i class="ri-user-fill"></i>
      </span>
    </div>
    <input type="text" class="form-control" readonly placeholder="Nombre" value="{{$user->name }}">
  </div>

  <div class="mb-3 d-flex">
    <div class="avatar-xs d-block flex-shrink-0 me-3">
      <span class="avatar-title rounded-circle fs-16 bg-primary text-light">
        <i class="ri-mail-fill"></i>
      </span>
    </div>
    <input type="text" class="form-control" readonly placeholder="Correo" value="{{$user->email }}">
  </div>

  <div class="mb-3 d-flex">
    <div class="avatar-xs d-block flex-shrink-0 me-3">
      <span class="avatar-title rounded-circle fs-16 bg-success text-light">
        <i class="ri-phone-fill"></i>
      </span>
    </div>
    <input type="text" class="form-control" readonly placeholder="Teléfono" value="{{$user->phone }}">
  </div>

</div>

        </div>
      </div>

      <!-- Tabs -->
      <div class="col-xl-8">
        <ul class="nav nav-tabs nav-tabs-custom nav-success mb-3" role="tablist">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#overview">Datos Personales</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#passwordtab">Contraseña</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sessions">Sesiones</a></li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active" id="overview">
            <div class="card">
              <div class="card-header"><h5 class="mb-0">Actualizar Información</h5></div>
              <div class="card-body">
                @include('profile.partials.update-profile-information-form')
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="passwordtab">
            <div class="card">
              <div class="card-header"><h5 class="mb-0">Cambiar Contraseña</h5></div>
              <div class="card-body">
                @include('profile.partials.update-password-form')
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="sessions">
            <div class="card">
              <div class="card-header"><h5 class="mb-0">Sesiones Activas</h5></div>
              <div class="card-body">
                @include('profile.partials.sessions-list', ['sessions' => $sessions])
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/profile.js')
@endpush
