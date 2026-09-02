@props(['grupo'])

{{--
    La marca que dice, con la palabra y no con un símbolo, que un grupo de chips
    hay que rellenarlo.

    El asterisco del prototipo funciona en un campo con recuadro: se lee como
    parte de la etiqueta de algo que se rellena. En un grupo de chips no, porque
    los chips no parecen un campo —parecen filtros que se pueden mirar y dejar—,
    y el asterisco queda pegado al final de una pregunta larga. Ese fue
    exactamente el campo que hizo creer que estaban todos completos: «¿Quién es
    el público beneficiado por esta actividad? *».

    Cambia en cuanto se elige algo. Mientras esté pendiente tiene que verse; una
    vez resuelto, insistir es ruido y deja de distinguirse lo que falta de lo que
    ya está.

    Se usa `:class` y no `:style`: en Alpine un `:style` con una cadena reemplaza
    el atributo entero y se lleva por delante el estilo estático del elemento.
    Este proyecto ya se cayó dos veces por ahí, con los círculos del wizard y con
    la lista de secciones del panel.

    Necesita `cuantos(grupo)` en el ámbito de Alpine; lo trae el componente
    `wizard` (resources/js/wizard.js).
--}}

<span class="marca-obligatoria"
      x-bind:class="{ 'listo': cuantos('{{ $grupo }}') > 0 }"
      x-text="cuantos('{{ $grupo }}') > 0 ? '✓ Listo' : 'Obligatorio'">Obligatorio</span>
