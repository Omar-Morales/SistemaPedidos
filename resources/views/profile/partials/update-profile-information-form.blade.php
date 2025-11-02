<section>
  <form id="profileForm">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Nombre</label>
        <input type="text" class="form-control" name="name" value="{{ old('name', $user->name) }}" readonly>
      </div>
      <div class="col-md-6">
        <label class="form-label">Correo Electrónico</label>
        <input type="email" class="form-control" name="email" value="{{ old('email', $user->email) }}" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Teléfono</label>
        <input type="text" class="form-control" name="phone" maxlength="9" value="{{ old('phone', $user->phone) }}">
      </div>
      @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
        <div class="col-12">
          <p class="text-warning">Tu correo electrónico no está verificado.</p>
          <button form="send-verification" class="btn btn-link p-0">Reenviar enlace de verificación</button>
        </div>
      @endif

      <div class="col-12">
        <button id="profileSubmit" type="button" class="btn btn-success">Guardar Cambios</button>
      </div>
    </div>
  </form>
</section>
