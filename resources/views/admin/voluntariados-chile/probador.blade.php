@extends('layouts.admin')
@section('title', 'Probar API Voluntariados Chile')

{{--
    Herramienta para probar la API, sólo para administradores. Ver
    Admin\ProbadorVoluntariadosController para el porqué de cada decisión.
--}}

@section('content')
<div x-data="probadorVch({ rutaConsultar: @js(route('admin.voluntariados.probador.consultar')), rutaRevisar: @js(route('admin.voluntariados.probador.revisar')) })"
     style="display:flex;flex-direction:column;gap:18px;">

    <p class="helper" style="margin:0;max-width:80ch;">
        Consulta el endpoint de oportunidades del Día del Patrimonio Social y compara lo que devuelve con
        la documentación de Voluntariados Chile. La API Key se usa para esta consulta y no se guarda en
        ningún sitio: al recargar la página hay que volver a pegarla.
        <br>Endpoint: <code style="font-size:12px;">{{ $url }}</code>
    </p>

    <div style="display:flex;gap:8px;">
        <button type="button" class="btn btn-sm" x-bind:class="modo === 'api' ? 'btn-primary' : 'btn-outline'" x-on:click="modo = 'api'">Consultar la API</button>
        <button type="button" class="btn btn-sm" x-bind:class="modo === 'pegado' ? 'btn-primary' : 'btn-outline'" x-on:click="modo = 'pegado'">Revisar un JSON pegado</button>
    </div>

    {{-- ── Consultar ── --}}
    <form class="card" style="padding:22px;display:grid;gap:16px;" x-show="modo === 'api'"
          x-on:submit.prevent="consultar()" data-sin-carga autocomplete="off">
        <label class="lbl">API Key
            {{-- Sin `name`: no viaja en ningún formulario y el navegador no la recuerda.
                 El ojo para verla lo pone campos.js, como en toda contraseña. --}}
            <input class="fld" type="password" x-model="clave" autocomplete="off" spellcheck="false"
                   placeholder="Pégala aquí (vacía: la API responde 401)"
                   style="font-family:ui-monospace,monospace;" data-probador-clave>
            <span class="helper">Se envía como <code>Authorization: Bearer …</code>. No se guarda ni se escribe en el registro.</span>
        </label>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
            <label class="lbl">updated_since
                <input class="fld" x-model="updatedSince" placeholder="2026-09-01T00:00:00Z" data-probador-desde>
                <span class="helper">ISO 8601. Vacío: todas.</span>
            </label>
            <label class="lbl">page
                <input class="fld" x-model="page" inputmode="numeric" placeholder="1" data-probador-pagina>
                <span class="helper">Por defecto 1.</span>
            </label>
            <label class="lbl">page_size
                <input class="fld" x-model="pageSize" inputmode="numeric" placeholder="50" data-probador-tamano>
                <span class="helper">Por defecto 50, máximo 200.</span>
            </label>
        </div>
        <p class="helper" style="margin:0;">
            Los parámetros se mandan tal cual, sin validar aquí: si alguno está mal, lo que interesa es ver qué contesta la API.
        </p>

        <div>
            <button type="submit" class="btn btn-primary" x-bind:disabled="cargando" data-probador-consultar>
                <span x-text="cargando ? 'Consultando…' : 'Consultar'">Consultar</span>
            </button>
        </div>
    </form>

    {{-- ── Pegar ── --}}
    <form class="card" style="padding:22px;display:grid;gap:12px;" x-show="modo === 'pegado'" x-cloak
          x-on:submit.prevent="revisarPegado()" data-sin-carga>
        <label class="lbl">Respuesta JSON
            <textarea class="fld" x-model="pegado" rows="10" spellcheck="false"
                      style="font-family:ui-monospace,monospace;font-size:12.5px;" data-probador-pegado></textarea>
            <span class="helper">Para revisar una respuesta que no venga de aquí. No llama a la API.</span>
        </label>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary" x-bind:disabled="cargando || ! pegado.trim()" data-probador-revisar>Revisar</button>
            <button type="button" class="btn btn-outline" x-on:click="rellenarEjemplo()" data-probador-ejemplo>Usar el ejemplo del PDF</button>
        </div>
    </form>

    <div class="alert alert-error" x-show="falloLocal" x-cloak x-text="falloLocal" data-probador-fallo-local></div>

    {{-- ── Resultado ── --}}
    <template x-if="resultado">
        <div style="display:flex;flex-direction:column;gap:18px;" data-probador-resultado>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;">
                <div class="kpi"><span class="v" x-bind:style="'color:' + tonoHttp()" x-text="resultado.http ?? '—'" data-probador-http></span><span class="l">código HTTP</span></div>
                <div class="kpi" x-show="resultado.ms !== undefined"><span class="v" x-text="resultado.ms + ' ms'" data-probador-ms></span><span class="l">tiempo de respuesta</span></div>
                <div class="kpi" x-show="informe"><span class="v" x-text="informe?.oportunidades ?? 0"></span><span class="l">oportunidades en la página</span></div>
                <div class="kpi" x-show="informe"><span class="v" x-bind:style="'color:' + (informe?.problemas ? 'var(--rosa)' : 'var(--turquesa)')" x-text="informe?.problemas ?? 0" data-probador-problemas></span><span class="l">problemas contra la documentación</span></div>
            </div>

            <p class="helper" style="margin:0;" x-show="resultado.url">
                <span x-text="resultado.con_clave ? 'Con API Key' : 'Sin API Key'"></span> ·
                <code style="font-size:12px;overflow-wrap:anywhere;" x-text="resultado.url"></code>
                <span x-show="resultado.bytes"> · <span x-text="resultado.bytes"></span> bytes</span>
            </p>

            <div class="alert alert-error" x-show="resultado.fallo_de_red" x-text="resultado.fallo_de_red"></div>

            {{-- El error de la API, tal cual. --}}
            <template x-if="errorApi">
                <div class="card" style="padding:18px 20px;border-left:4px solid var(--rosa);" data-probador-error>
                    <div class="seclabel" style="margin-bottom:8px;">Error devuelto por la API</div>
                    <div style="font-size:14.5px;"><strong>code:</strong> <code x-text="errorApi.code" data-probador-error-code></code></div>
                    <div style="font-size:14.5px;margin-top:4px;"><strong>message:</strong> <span x-text="errorApi.message" data-probador-error-message></span></div>
                </div>
            </template>

            {{-- El informe contra la documentación. --}}
            <template x-if="informe">
                <div class="card" style="padding:20px 22px;display:flex;flex-direction:column;gap:18px;" data-probador-informe>
                    <div>
                        <div class="seclabel" style="margin-bottom:8px;">Contra la documentación</div>
                        <ul style="margin:0;padding-left:18px;font-size:14px;line-height:1.6;">
                            <template x-for="s in informe.sobre"><li x-bind:style="s.grave ? 'color:var(--rosa);' : ''" x-text="s.texto"></li></template>
                            <li x-show="! informe.sobre.length && ! informe.campos.length && ! informe.no_documentados.length">Nada que señalar.</li>
                        </ul>
                    </div>

                    <div x-show="informe.campos.length">
                        <div class="seclabel" style="margin-bottom:8px;">Campos documentados que faltan, vienen vacíos o distintos</div>
                        <div style="overflow-x:auto;">
                            <table class="panel-tabla" style="width:100%;font-size:13.5px;" data-probador-campos>
                                <thead><tr><th style="text-align:left;">Campo</th><th style="text-align:left;">Qué pasa</th><th style="text-align:left;">Detalle</th><th style="text-align:left;">Ejemplos (id)</th></tr></thead>
                                <tbody>
                                    <template x-for="c in informe.campos" x-bind:key="c.ruta">
                                        <tr x-bind:style="esProblema(c) ? '' : 'color:var(--gris);'">
                                            <td><code x-text="c.ruta"></code></td>
                                            <td x-text="conteo(c)"></td>
                                            <td x-text="c.detalles.join(' · ')"></td>
                                            <td style="font-size:12px;overflow-wrap:anywhere;" x-text="c.ids.join(', ')"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <p class="helper" style="margin:6px 0 0;">En gris, lo que la documentación admite vacío («string o null», listas que pueden venir vacías): no es un error, pero conviene saber cuántas.</p>
                    </div>

                    <div x-show="informe.no_documentados.length">
                        <div class="seclabel" style="margin-bottom:8px;">Llegan y no estaban documentados</div>
                        <ul style="margin:0;padding-left:18px;font-size:14px;line-height:1.6;" data-probador-no-documentados>
                            <template x-for="n in informe.no_documentados"><li><code x-text="n.ruta"></code> — <span x-text="n.veces"></span> vez/veces, p. ej. <code x-text="n.ejemplo"></code></li></template>
                        </ul>
                    </div>

                    <div x-show="informe.coherencia.length">
                        <div class="seclabel" style="margin-bottom:8px;">Datos que no cuadran entre sí</div>
                        <ul style="margin:0;padding-left:18px;font-size:14px;line-height:1.6;" data-probador-coherencia>
                            <template x-for="a in informe.coherencia"><li x-bind:style="a.grave ? 'color:var(--rosa);' : ''"><code x-text="a.id" style="font-size:12px;"></code>: <span x-text="a.texto"></span></li></template>
                        </ul>
                    </div>

                    <div x-show="Object.keys(informe.catalogos).length">
                        <div class="seclabel" style="margin-bottom:8px;">Valores reales de lo que aún no está cerrado</div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;" data-probador-catalogos>
                            <template x-for="(valores, grupo) in informe.catalogos" x-bind:key="grupo">
                                <div style="border:1px solid var(--linea);border-radius:12px;padding:12px 14px;">
                                    <div style="font-weight:700;font-size:13.5px;margin-bottom:6px;"><code x-text="grupo"></code></div>
                                    <ul style="margin:0;padding-left:16px;font-size:13px;line-height:1.55;">
                                        <template x-for="v in valores"><li><strong x-text="v.valor"></strong> <span x-show="v.nombre" x-text="'— ' + v.nombre" x-bind:style="/SIN EQUIVALENTE|NUEVO/.test(v.nombre ?? '') ? 'color:var(--rosa);font-weight:700;' : ''"></span> <span class="helper" x-text="'(' + v.veces + ')'"></span></li></template>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            {{-- La respuesta cruda. --}}
            <div class="card" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;">
                    <div class="seclabel">Respuesta cruda</div>
                    <button type="button" class="btn btn-outline btn-sm" x-on:click="copiar()">Copiar</button>
                </div>
                <pre style="margin:0;max-height:560px;overflow:auto;background:#fbfbfc;border:1px solid var(--linea);border-radius:10px;padding:14px;font-size:12.5px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere;" x-text="crudo" data-probador-crudo></pre>
            </div>
        </div>
    </template>

    {{-- El ejemplo de la sección 6 del PDF para probar el revisor sin clave. Igual que en el
         PDF salvo los datos de contacto y el enlace personal, que se cambian por ficticios:
         son de personas y no van en el repositorio. --}}
    <script type="application/json" id="ejemplo-pdf">
{
  "generated_at": "2026-09-24T18:53:22.918Z",
  "pagination": { "page": 1, "page_size": 50, "total": 2 },
  "data": [
    {
      "id": "ee3f9f86-dbd3-4821-8b50-58d76c939151",
      "status": "postulación_abierta",
      "updated_at": "2026-09-24T18:51:02.614Z",
      "opportunity_url": "https://voluntariadoschile.cl/oportunidades?id=ee3f9f86-dbd3-4821-8b50-58d76c939151",
      "organization": {
        "name": "Voluntariados Chile",
        "logo_url": "https://wepgoxkyujickiwvldrs.supabase.co/storage/v1/object/public/organization-logos/71f9d7eb-dd30-4d81-8fec-c1e1315c3846/1789050256891.png",
        "type": "Organizaciones sin fines de lucro",
        "impact_areas": [ { "code": "AI_EMP", "name": "Emprendimiento" } ]
      },
      "title": "prueba",
      "description": "prueba ",
      "responsibilities": "prueba",
      "external_link": "https://www.ejemplo.cl/portafolio",
      "cover_image_url": null,
      "format": "En persona",
      "volunteers_needed": 30,
      "location": { "regions": ["Arica y Parinacota"], "comunas": ["Camarones", "Arica"] },
      "schedule": { "type": "fixed_range", "start_date": "2026-09-24", "end_date": "2026-09-29" },
      "category": { "code": "CAT_ACE", "name": "Acción comunitaria y emergencias" },
      "requirements": {
        "minimum_age": { "required": false, "detail": "25" },
        "certificate": { "required": true, "detail": "De manejo" },
        "profession_or_study": { "required": false, "detail": null },
        "prior_experience": { "required": false, "detail": null }
      },
      "contact": { "name": "Persona de contacto", "email": "contacto@ejemplo.cl" }
    },
    {
      "id": "cea8eae2-0599-473b-af73-e2274d8f9457",
      "status": "postulación_abierta",
      "updated_at": "2026-09-16T19:57:14.691Z",
      "opportunity_url": "https://voluntariadoschile.cl/oportunidades?id=cea8eae2-0599-473b-af73-e2274d8f9457",
      "organization": {
        "name": "COANIQUEM",
        "logo_url": "https://wepgoxkyujickiwvldrs.supabase.co/storage/v1/object/public/organization-logos/238c513b-70bd-46d0-a8fe-b2c568a47ca8/1789395503456.png",
        "type": "Organizaciones sin fines de lucro",
        "impact_areas": [
          { "code": "AI_PDESC", "name": "Desarrollo comunitario" },
          { "code": "AI_IJUV", "name": "Infancia y juventud" },
          { "code": "AI_SAL", "name": "Salud" }
        ]
      },
      "title": "Voluntariado COANIQUEM",
      "description": "Programa de Voluntariado COANIQUEM que contempla 7 opciones: Tiendas Solidarias, Casabierta, Prevención Online, Sala de espera, Servicio en Santuario, Mantenimiento y Otras opciones.",
      "responsibilities": "Diversas responsabilidades dependiendo del tipo de voluntariado",
      "external_link": "https://coaniquem.cl/voluntarios/",
      "cover_image_url": "https://wepgoxkyujickiwvldrs.supabase.co/storage/v1/object/public/opportunity-headers/238c513b-70bd-46d0-a8fe-b2c568a47ca8/1789588235480.jpg",
      "format": "Híbrido",
      "volunteers_needed": null,
      "location": { "regions": ["Metropolitana"], "comunas": [] },
      "schedule": { "type": "continuous", "start_date": null, "end_date": null },
      "category": { "code": "CAT_ACE", "name": "Acción comunitaria y emergencias" },
      "requirements": {
        "minimum_age": { "required": false, "detail": null },
        "certificate": { "required": true, "detail": "Dependiendo del voluntariado escogido" },
        "profession_or_study": { "required": false, "detail": null },
        "prior_experience": { "required": false, "detail": null }
      },
      "contact": { "name": "Persona de contacto", "email": "voluntarios@ejemplo.cl" }
    }
  ]
}
    </script>
</div>
@endsection
