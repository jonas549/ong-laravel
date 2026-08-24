<div class="seclabel" style="margin-bottom:10px;">Paso 3</div>
<h2 style="font-weight:800;font-size:28px;margin:0 0 24px;">Cuéntanos de tu organización</h2>

<div style="display:flex;flex-direction:column;gap:18px;">
    <div>
        <label class="helper" for="org_nombre" style="display:block;margin-bottom:6px;font-weight:600;">Nombre de la organización *</label>
        <input class="fld @error('org_nombre') is-invalid @enderror" type="text" id="org_nombre" name="org_nombre"
               value="{{ old('org_nombre') }}" placeholder="Ej. Fundación Junto al Barrio" required>
        @error('org_nombre') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="helper" style="display:block;margin-bottom:6px;font-weight:600;">Tipo de organización</label>
        <input class="fld" type="text" x-bind:value="tipo" disabled style="background:var(--gris-100);">
        <span class="helper">Prellenado del paso anterior.</span>
    </div>

    <div x-show="tipo === 'Otra'" x-cloak>
        <label class="helper" for="org_tipo_otro" style="display:block;margin-bottom:6px;font-weight:600;">Otra (especificar) *</label>
        <input class="fld @error('org_tipo_otro') is-invalid @enderror" type="text" id="org_tipo_otro" name="org_tipo_otro"
               value="{{ old('org_tipo_otro') }}">
        @error('org_tipo_otro') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div x-show="tipo === 'Empresa o institución privada'" x-cloak>
        <label class="helper" for="org_num_voluntarios" style="display:block;margin-bottom:6px;font-weight:600;">¿Cuántos trabajadores participan como voluntarios? *</label>
        <input class="fld @error('org_num_voluntarios') is-invalid @enderror" type="number" min="0" id="org_num_voluntarios"
               name="org_num_voluntarios" value="{{ old('org_num_voluntarios') }}" placeholder="Ej. 25">
        <span class="helper">Número aproximado. Escribe 0 si no aplica.</span>
        @error('org_num_voluntarios') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div x-show="tipo === 'Institución educativa'" x-cloak>
        <label class="helper" for="org_unidad_educativa" style="display:block;margin-bottom:6px;font-weight:600;">¿Qué unidad, grupo o comunidad educativa organiza la actividad? *</label>
        <input class="fld @error('org_unidad_educativa') is-invalid @enderror" type="text" id="org_unidad_educativa"
               name="org_unidad_educativa" value="{{ old('org_unidad_educativa') }}"
               placeholder="Ej. Facultad de Enfermería, Centro de Estudiantes, 3° medio B">
        @error('org_unidad_educativa') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="helper" for="org_descripcion" style="display:block;margin-bottom:6px;font-weight:600;">Describe tu organización *</label>
        <textarea class="fld @error('org_descripcion') is-invalid @enderror" id="org_descripcion" name="org_descripcion"
                  rows="4" required>{{ old('org_descripcion') }}</textarea>
        @error('org_descripcion') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <hr style="border:0;border-top:1px solid var(--linea);margin:8px 0;">

    <div class="seclabel">Crea tu acceso</div>

    <div>
        <label class="helper" for="email" style="display:block;margin-bottom:6px;font-weight:600;">Correo electrónico *</label>
        <input class="fld @error('email') is-invalid @enderror" type="email" id="email" name="email"
               value="{{ old('email') }}" placeholder="contacto@organizacion.cl" required autocomplete="email">
        @error('email') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
        <div>
            <label class="helper" for="password" style="display:block;margin-bottom:6px;font-weight:600;">Contraseña *</label>
            <input class="fld @error('password') is-invalid @enderror" type="password" id="password" name="password"
                   placeholder="••••••••" required autocomplete="new-password">
            <span class="helper">Mínimo 8 caracteres.</span>
            @error('password') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="helper" for="password_confirmation" style="display:block;margin-bottom:6px;font-weight:600;">Confirmar contraseña *</label>
            <input class="fld" type="password" id="password_confirmation" name="password_confirmation"
                   placeholder="••••••••" required autocomplete="new-password">
        </div>
    </div>
</div>
