{{--
    Editor de texto con formato.

    Un `contenteditable` con su barra de botones, sin librería. No es
    minimalismo por deporte: lo que la ONG necesita escribir aquí son párrafos
    con alguna negrita y algún enlace, y traer un editor completo significaría
    cargar cientos de kilobytes —y sus estilos, que compiten con los del sitio—
    para eso. El proyecto ya quitó support.js por descargar React y Babel de
    unpkg en cada carga.

    **Nada de lo que salga de aquí se cree.** El HTML se vuelve a limpiar en el
    servidor con la lista blanca de SanitizadorHtml, y otra vez al pintarlo. Esta
    barra decide lo que es cómodo escribir, no lo que es seguro guardar.

    Espera `$clave` y `$valor`.
--}}

<div x-data="editorRico({{ Js::from($clave) }})" class="editor-rico">

    <div class="editor-rico-barra" role="toolbar" aria-label="Formato del texto">
        @foreach ([
            ['bold', 'Negrita', 'B', 'font-weight:800;'],
            ['italic', 'Cursiva', 'I', 'font-style:italic;font-family:Georgia,serif;'],
        ] as [$orden, $titulo, $etiqueta, $estilo])
            <button type="button" title="{{ $titulo }}" aria-label="{{ $titulo }}"
                    x-on:mousedown.prevent="mandar('{{ $orden }}')" style="{{ $estilo }}">{{ $etiqueta }}</button>
        @endforeach

        <span class="editor-rico-sep" aria-hidden="true"></span>

        <button type="button" title="Encabezado" aria-label="Encabezado"
                x-on:mousedown.prevent="bloque('h3')">H</button>
        <button type="button" title="Párrafo normal" aria-label="Párrafo normal"
                x-on:mousedown.prevent="bloque('p')">¶</button>

        <span class="editor-rico-sep" aria-hidden="true"></span>

        <button type="button" title="Lista con viñetas" aria-label="Lista con viñetas"
                x-on:mousedown.prevent="mandar('insertUnorderedList')">•—</button>
        <button type="button" title="Lista numerada" aria-label="Lista numerada"
                x-on:mousedown.prevent="mandar('insertOrderedList')">1.</button>

        <span class="editor-rico-sep" aria-hidden="true"></span>

        <button type="button" title="Enlace" aria-label="Enlace" x-on:mousedown.prevent="enlazar()">🔗</button>
        <button type="button" title="Quitar el enlace" aria-label="Quitar el enlace"
                x-on:mousedown.prevent="mandar('unlink')">⛓</button>

        <span style="flex:1;"></span>

        <button type="button" title="Quitar todo el formato del texto pegado" class="editor-rico-limpiar"
                x-on:mousedown.prevent="limpiarFormato()">Limpiar formato</button>
    </div>

    {{--
        `x-on:paste` es la mitad del trabajo: pegar desde Word trae una nube de
        <span style="mso-...">, tablas de maquetación y fuentes en puntos. El
        servidor lo tiraría igual, pero entonces la persona ve una cosa mientras
        escribe y otra distinta al publicar. Aquí se pega el texto plano y lo que
        se ve es lo que va a quedar.
    --}}
    <div contenteditable="true"
         class="editor-rico-campo texto-editable"
         x-ref="campo"
         x-on:input="cambio()"
         x-on:blur="cambio()"
         x-on:paste.prevent="pegar($event)"
         role="textbox"
         aria-multiline="true"
         aria-label="{{ $campo['label'] ?? 'Texto' }}">{!! $valor !!}</div>

    <input type="hidden" name="{{ $clave }}" x-ref="oculto" value="{{ $valor }}">
</div>
