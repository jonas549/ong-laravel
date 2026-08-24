<form method="POST" action="{{ route('registrations.store', $activity) }}" style="display:flex;flex-direction:column;gap:12px;">
    @csrf

    <div class="seclabel">Inscríbete</div>

    <div>
        <label class="helper" for="r-nombre" style="display:block;margin-bottom:5px;font-weight:600;">Nombre *</label>
        <input class="fld @error('nombre') is-invalid @enderror" type="text" id="r-nombre" name="nombre"
               value="{{ old('nombre') }}" required>
        @error('nombre') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="helper" for="r-correo" style="display:block;margin-bottom:5px;font-weight:600;">Correo *</label>
        <input class="fld @error('correo') is-invalid @enderror" type="email" id="r-correo" name="correo"
               value="{{ old('correo') }}" required>
        @error('correo') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="helper" for="r-telefono" style="display:block;margin-bottom:5px;font-weight:600;">Teléfono</label>
        <input class="fld" type="tel" id="r-telefono" name="telefono" value="{{ old('telefono') }}">
    </div>

    <label style="display:flex;align-items:center;gap:9px;font-size:14px;color:var(--gris-700);cursor:pointer;">
        <input type="checkbox" name="es_mayor_edad" value="1" @checked(old('es_mayor_edad', true))>
        Soy mayor de edad
    </label>

    <button type="submit" class="btn btn-primary" style="justify-content:center;">Reservar mi cupo</button>
</form>
