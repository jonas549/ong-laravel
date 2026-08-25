@extends('layouts.admin')
@section('title', $plantilla->nombre)

@section('actions')
    <a class="btn btn-outline btn-sm" href="{{ route('admin.templates.index') }}">← Volver a plantillas</a>
@endsection

@section('content')
<p class="helper" style="margin:0 0 22px;max-width:70ch;">{{ $plantilla->descripcion }}</p>

@if (session('aviso'))
    <div class="alert alert-info" style="margin-bottom:18px;">{{ session('aviso') }}</div>
@endif

<div x-data="editorPlantilla({{ Js::from(route('admin.templates.preview', $plantilla)) }})"
     style="display:grid;grid-template-columns:1.15fr .85fr;gap:22px;align-items:start;" class="grid-2">

    {{-- ── Editor ── --}}
    <form method="POST" action="{{ route('admin.templates.update', $plantilla) }}" x-ref="form"
          class="card" style="padding:24px;">
        @csrf
        @method('PUT')

        <label class="lbl" style="margin-bottom:16px;">Asunto
            <input class="fld @error('asunto') is-invalid @enderror" name="asunto" x-ref="asunto"
                   value="@viejo('asunto', $plantilla->asunto)" required>
            @error('asunto') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="lbl">Cuerpo del correo (HTML)
            <textarea class="fld @error('cuerpo_html') is-invalid @enderror" name="cuerpo_html" x-ref="cuerpo"
                      rows="18" style="resize:vertical;font-family:ui-monospace,Consolas,monospace;font-size:13px;line-height:1.6;"
                      required>{{ \App\Support\Formulario::viejo('cuerpo_html', $plantilla->cuerpo_html) }}</textarea>
            @error('cuerpo_html') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label style="display:flex;align-items:center;gap:10px;margin:18px 0;cursor:pointer;font-size:14.5px;">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $plantilla->activo))>
            Enviar este correo automáticamente
        </label>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <button type="button" class="btn btn-outline" x-on:click="previsualizar()">Vista previa</button>
        </div>
    </form>

    {{-- ── Ayuda y pruebas ── --}}
    <div style="display:flex;flex-direction:column;gap:18px;">

        <div class="card" style="padding:22px;">
            <div class="seclabel" style="margin-bottom:12px;">Marcadores disponibles</div>
            <p class="helper" style="margin:0 0 12px;">Escríbelos tal cual en el asunto o el cuerpo. Se sustituyen al enviar.</p>

            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @foreach ($variables as $v)
                    {{-- El marcador se arma en PHP: escribirlo literal en Blade
                         cerraría el eco en las llaves internas. --}}
                    @php $marcador = '{{ ' . $v . ' }}'; @endphp
                    <button type="button" class="chip" style="font-family:ui-monospace,Consolas,monospace;"
                            x-on:click="insertar({{ Js::from($marcador) }})">{{ $marcador }}</button>
                @endforeach
            </div>

            <div x-show="desconocidas.length" x-cloak class="alert alert-error" style="margin-top:14px;font-size:13.5px;">
                No sabemos resolver estos marcadores y saldrán literales en el correo:
                <span x-text="desconocidas.map(v => '&#123;&#123;' + v + '&#125;&#125;').join(', ')"></span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.templates.test', $plantilla) }}" class="card" style="padding:22px;">
            @csrf
            <div class="seclabel" style="margin-bottom:12px;">Enviar una prueba</div>
            <p class="helper" style="margin:0 0 12px;">Se envía con datos de ejemplo, sin tocar ninguna inscripción real.</p>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <input class="fld" style="flex:1;min-width:200px;" type="email" name="destino"
                       value="@viejo('destino', auth()->user()->email)" required>
                <button type="submit" class="btn btn-outline btn-sm">Enviar</button>
            </div>
            @error('destino') <span class="field-error">{{ $message }}</span> @enderror
        </form>
    </div>

    {{-- ── Vista previa ── --}}
    <div x-show="abierta" x-cloak
         style="position:fixed;inset:0;z-index:80;background:rgba(51,54,58,.5);display:grid;place-items:center;padding:24px;"
         x-on:click.self="abierta = false" x-on:keydown.escape.window="abierta = false">
        <div style="background:#fff;border-radius:20px;width:100%;max-width:680px;max-height:86vh;display:flex;flex-direction:column;overflow:hidden;">
            <div style="padding:18px 22px;border-bottom:1px solid var(--linea);display:flex;align-items:center;gap:14px;">
                <div style="min-width:0;">
                    <div class="helper">Asunto</div>
                    <div style="font-weight:700;" x-text="asunto"></div>
                </div>
                <button type="button" class="btn btn-outline btn-sm" style="margin-left:auto;" x-on:click="abierta = false">Cerrar</button>
            </div>
            {{--
                En iframe para que los estilos del correo no se mezclen con los
                del panel, y con sandbox vacío: el cuerpo de la plantilla es
                HTML que escribe una persona, y sin esto un <script> pegado ahí
                se ejecutaría con la sesión del admin.
            --}}
            <iframe x-ref="marco" title="Vista previa" sandbox
                    style="flex:1;width:100%;min-height:420px;border:0;background:#faf7f3;"></iframe>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function editorPlantilla(urlPrevia) {
        return {
            abierta: false,
            asunto: '',
            desconocidas: [],

            /** Inserta el marcador donde esté el cursor del cuerpo. */
            insertar(texto) {
                const campo = this.$refs.cuerpo;
                const ini = campo.selectionStart ?? campo.value.length;
                const fin = campo.selectionEnd ?? campo.value.length;

                campo.value = campo.value.slice(0, ini) + texto + campo.value.slice(fin);
                campo.focus();
                campo.selectionStart = campo.selectionEnd = ini + texto.length;
            },

            async previsualizar() {
                const cuerpo = new FormData();
                cuerpo.append('asunto', this.$refs.asunto.value);
                cuerpo.append('cuerpo_html', this.$refs.cuerpo.value);
                cuerpo.append('_token', document.querySelector('meta[name=csrf-token]')?.content ?? '');

                const r = await fetch(urlPrevia, {
                    method: 'POST',
                    body: cuerpo,
                    headers: { 'Accept': 'application/json' },
                });

                if (!r.ok) {
                    alert('No se pudo generar la vista previa. Revisa el asunto y el cuerpo.');
                    return;
                }

                const datos = await r.json();

                this.asunto = datos.asunto;
                this.desconocidas = datos.desconocidas;
                this.abierta = true;

                // srcdoc y no document.write: con el iframe en sandbox,
                // document.write escribiría en un documento del mismo origen.
                this.$nextTick(() => {
                    this.$refs.marco.srcdoc = datos.html;
                });
            },
        };
    }
</script>
@endpush
