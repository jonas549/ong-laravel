<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Punto 7 del 09/10: la página de Preguntas frecuentes, con el contenido del
 * documento del cliente («FINAL_Preguntas_frecuentes_…_personas.docx»).
 *
 * Es una página suelta más (`pages`), así que después se edita desde el panel
 * como la de privacidad: Páginas → Preguntas frecuentes. Cada pregunta va en
 * un `<details>`, que se abre y se cierra sin JavaScript; los estilos viven en
 * `.pagina-texto .faq` de app.css.
 *
 * **Sólo si no existe ya** (ni en la papelera): si alguien la creó a mano en
 * el panel, manda lo que escribió.
 */
return new class extends Migration
{
    private const SLUG = 'preguntas-frecuentes';

    public function up(): void
    {
        if (DB::table('pages')->where('slug', self::SLUG)->exists()) {
            return;
        }

        DB::table('pages')->insert([
            'titulo' => 'Preguntas frecuentes',
            'slug' => self::SLUG,
            'meta_descripcion' => 'Qué es el Día del Patrimonio Social, cómo participar en panoramas solidarios y cómo ser voluntario o voluntaria.',
            'contenido' => <<<'HTML'
<div class="faq">
<h2>1. Información general</h2>
<details>
  <summary>¿Qué es el Patrimonio Social?</summary>
  <div>
    <p>El Patrimonio Social es todo aquello que construimos como comunidad cuando nos unimos para cuidar, compartir y colaborar con otras personas. Es un patrimonio vivo que se fortalece con nuestras acciones y nos pertenece a todas y todos. Nuestro mayor patrimonio social es la solidaridad: cada acción que fortalece los vínculos comunitarios y nos ayuda a enfrentar desafíos que tenemos en común contribuye a construirlo.</p>
  </div>
</details>
<details>
  <summary>¿Qué es el Día del Patrimonio Social?</summary>
  <div>
    <p>El Día del Patrimonio Social es una celebración que pone en valor la solidaridad, el voluntariado y el compromiso social como parte del patrimonio vivo de Chile. Es una invitación a que organizaciones, empresas, comunidades y personas se unan para realizar acciones que generen bienestar colectivo. En 2026 se celebra especialmente el 4 y 5 de diciembre, pero el movimiento busca impulsar acciones solidarias durante todo el año.</p>
  </div>
</details>
<details>
  <summary>¿Quién organiza el Día del Patrimonio Social?</summary>
  <div>
    <p>El Día del Patrimonio Social es una iniciativa de la <a href="https://comunidad-org.cl/" target="_blank" rel="noopener">Comunidad de Organizaciones Solidarias</a> y sus organizaciones socias, que se celebra en colaboración junto con las organizaciones, empresas, instituciones, comunidades y personas que se suman al movimiento.</p>
  </div>
</details>
<details>
  <summary>¿Cuándo se celebra el Día del Patrimonio Social 2026?</summary>
  <div>
    <p>El Día del Patrimonio Social 2026 se celebra el 4 y 5 de diciembre, en torno al Día Internacional del Voluntariado y en el contexto del Año Internacional de las Personas Voluntarias para el Desarrollo Sostenible.</p>
  </div>
</details>
<details>
  <summary>¿Por qué se celebra el Día del Patrimonio Social?</summary>
  <div>
    <p>Porque creemos en la fuerza de lo que construimos cuando actuamos juntos. Esta celebración busca visibilizar el aporte de la solidaridad y el voluntariado, conectar a más personas con iniciativas sociales y motivarnos a realizar acciones concretas que contribuyan al bienestar de nuestras comunidades.</p>
  </div>
</details>
<details>
  <summary>¿Por qué debería sumarme al Día del Patrimonio Social?</summary>
  <div>
    <p>Porque puedes encontrar una forma concreta de aportar a una causa que te importa, de acuerdo con tus intereses, habilidades, tiempo y posibilidades. Puedes participar como voluntario o voluntaria, asistir a una actividad solidaria abierta al público o incluso organizar tu propia actividad. Cada participación cuenta para construir comunidades más solidarias.</p>
  </div>
</details>
<details>
  <summary>¿Cómo puedo participar si soy una persona?</summary>
  <div>
    <p>Puedes participar de distintas maneras:</p>
    <ul>
      <li>Ser voluntario o voluntaria en una oportunidad publicada por una organización o participante.</li>
      <li>Ir a un panorama solidario y participar en una actividad abierta al público.</li>
      <li>Organizar una actividad solidaria o comunitaria e invitar a otras personas a sumarse.</li>
      <li>Promover la solidaridad y la acción colectiva, difundiendo el movimiento del Día del Patrimonio Social.</li>
    </ul>
  </div>
</details>
<details>
  <summary>¿Cuál es la diferencia entre ser voluntario y participar en un panorama solidario?</summary>
  <div>
    <p>Si eres voluntario o voluntaria, te inscribes en una convocatoria para colaborar activamente en una actividad y la postulación se gestiona a través de Voluntariados Chile. Si participas en un panorama solidario, buscas una actividad abierta al público que quieras visitar o en la que quieras participar; dependiendo de la actividad, puede ser necesario inscribirse o simplemente asistir.</p>
  </div>
</details>
<details>
  <summary>¿Hay que pagar para participar?</summary>
  <div>
    <p>No. La inscripción para participar en el Día del Patrimonio Social es gratuita.</p>
  </div>
</details>
<details>
  <summary>¿Puedo participar otro día que no sea el 4 o 5 de diciembre?</summary>
  <div>
    <p>Sí. El Día del Patrimonio Social busca impulsar un movimiento solidario durante todo el año. Puedes participar en oportunidades de voluntariado o en panoramas solidarios que se realicen en otras fechas, y también puedes organizar una acción que ya tenías planificada en otra fecha y enmarcarla en el movimiento. Para ello, te recomendamos utilizar la <a href="https://www.canva.com/design/DAHGZdhU2ZE/daWOTMwxbN7B8xjFdDXANQ/view?utm_content=DAHGZdhU2ZE&amp;utm_campaign=designshare&amp;utm_medium=link2&amp;utm_source=uniquelinks&amp;utlId=h8cb9d9d1ca" target="_blank" rel="noopener">Guía para organizadores de actividades</a>.</p>
  </div>
</details>
<details>
  <summary>¿El Día del Patrimonio Social se celebra solo en Santiago?</summary>
  <div>
    <p>No. El Día del Patrimonio Social se celebra en todo Chile. La plataforma permite explorar actividades por región y comuna para que puedas encontrar oportunidades de participación en distintos territorios del país.</p>
  </div>
</details>

<h2>2. Participación en panoramas solidarios</h2>
<details>
  <summary>¿Cómo encuentro actividades solidarias cerca de mí?</summary>
  <div>
    <p>Entra a la sección “Actividades” de la plataforma y explora las actividades disponibles. Puedes buscar por nombre de actividad u organización y filtrar por región, comuna, tema y formato. También puedes revisar las actividades en formato de lista o calendario para encontrar una opción que se ajuste a tu ubicación e intereses.</p>
  </div>
</details>
<details>
  <summary>¿Puedo buscar actividades por región o comuna?</summary>
  <div>
    <p>Sí. La sección de actividades permite filtrar por región y comuna, además de tema y formato. Así puedes encontrar actividades solidarias y comunitarias en distintos lugares de Chile y elegir las que estén más cerca o sean más convenientes para ti.</p>
  </div>
</details>
<details>
  <summary>¿Qué tipo de actividades puedo encontrar?</summary>
  <div>
    <p>Hay actividades de distintos tipos, según lo que publiquen las organizaciones y participantes. En la plataforma puedes encontrar, por ejemplo, iniciativas relacionadas con salud, educación y formación, medio ambiente, desarrollo comunitario, arte, cultura y oficios, animales y biodiversidad, entre otras. Algunas actividades además indican características como ser familiares, al aire libre, accesibles en transporte público o dirigidas a determinados grupos.</p>
  </div>
</details>
<details>
  <summary>¿Puedo participar en una actividad si voy con mi familia?</summary>
  <div>
    <p>Sí, cuando la actividad está indicada como apta para participar en familia. Revisa las características y el público al que está dirigida en la ficha de cada actividad antes de inscribirte o asistir.</p>
  </div>
</details>
<details>
  <summary>¿Hay actividades para jóvenes o personas mayores?</summary>
  <div>
    <p>Sí. Las fichas de las actividades pueden indicar a qué público están dirigidas, por ejemplo, jóvenes, personas mayores, familias, niñas y niños o personas con discapacidad. Revisa esa información en cada actividad para elegir una experiencia adecuada para ti.</p>
  </div>
</details>
<details>
  <summary>¿Puedo participar si tengo una discapacidad o necesito alguna condición de accesibilidad?</summary>
  <div>
    <p>Depende de cada actividad. Algunas fichas indican características de accesibilidad o condiciones que pueden facilitar la participación. Te recomendamos revisar la ficha de la actividad antes de inscribirte y, si necesitas información adicional, contactar a la organización responsable utilizando los datos que entregue la convocatoria.</p>
  </div>
</details>
<details>
  <summary>¿Puedo participar aunque no sea voluntario o voluntaria?</summary>
  <div>
    <p>Sí. Puedes participar como asistente en los panoramas solidarios abiertos al público. En la plataforma encontrarás actividades comunitarias y solidarias que puedes visitar y disfrutar, como talleres, actividades culturales, deportivas, charlas y otras iniciativas. Los encuentras en “Quiero ir a panoramas solidarios”</p>
  </div>
</details>
<details>
  <summary>¿A qué me comprometo cuando me inscribo para participar en un panorama solidario?</summary>
  <div>
    <p>Al inscribirte te comprometes a participar de manera responsable en la actividad en la que te inscribiste. Si finalmente no puedes asistir, avisa con anticipación a la organización responsable utilizando los datos que recibas al inscribirte, para liberar tu cupo para alguien más.</p>
  </div>
</details>
<details>
  <summary>¿Qué pasa después de inscribirme en una actividad?</summary>
  <div>
    <p>Depende del tipo de participación. En un voluntariado, la organización responsable te contactará con la información y los pasos necesarios. En un panorama solidario, revisa la ficha para saber si necesitas inscripción previa o si basta con asistir. Guarda la información de fecha, horario y lugar para participar según lo indicado por la organización.</p>
  </div>
</details>
<details>
  <summary>¿Cómo puedo difundir mi participación en la celebración del Día del Patrimonio Social?</summary>
  <div>
    <p>Si eres participante en un panorama solidario, al inscribirte recibirás la imagen para compartir en tus redes sociales y motivar a otros a sumarse al Día del Patrimonio Social. También puedes compartir directamente la actividad donde vas a participar desde la sección “Comparte esta actividad” de la ficha de la actividad, y compartir en tus redes sociales los materiales del <a href="https://drive.google.com/drive/folders/1MHkgL8Cfstr_AP19yRDhQ5Z8SUomHOaL" target="_blank" rel="noopener">kit de difusión</a>.</p>
  </div>
</details>
<details>
  <summary>¿Qué información recibiré al inscribirme en un panorama solidario?</summary>
  <div>
    <p>Recibirás un correo de confirmación con la información de la actividad a la que te inscribiste, su organizador, el link para agregar la actividad a tu calendario y el link para cancelar tu inscripción, en caso de que no puedas asistir.</p>
    <p>También recibirás la imagen que puedes compartir en tus redes sociales para motivar a otros a sumarse.</p>
    <p>Unos días antes de la actividad recibirás un correo de recordatorio.</p>
  </div>
</details>

<h2>3. Participación como voluntario o voluntaria</h2>
<details>
  <summary>¿Cómo puedo encontrar una oportunidad de voluntariado?</summary>
  <div>
    <p>Selecciona “Quiero ser voluntario” en la plataforma. Allí encontrarás el acceso a Voluntariados Chile, plataforma aliada que conecta a personas con organizaciones que ofrecen oportunidades de voluntariado. Puedes explorar las oportunidades disponibles y postular a la que mejor se ajuste a tus intereses y disponibilidad.</p>
  </div>
</details>
<details>
  <summary>¿Puedo hacer voluntariado durante todo el año?</summary>
  <div>
    <p>Sí. El Día del Patrimonio Social es parte de un movimiento que busca visibilizar y fortalecer las acciones solidarias durante todo el año. En Voluntariados Chile puedes encontrar oportunidades de voluntariado que se realizan en distintas fechas, incluyendo convocatorias puntuales y oportunidades de participación más permanente.</p>
  </div>
</details>
<details>
  <summary>¿Qué tipo de voluntariado puedo hacer?</summary>
  <div>
    <p>Las oportunidades pueden ser muy diversas. Por ejemplo, puedes encontrar actividades de acompañamiento y cuidado, educación y tutorías, medio ambiente y conservación, apoyo comunitario, actividades culturales y recreativas, mentorías, comunicación, apoyo administrativo, servicios profesionales y otras formas de colaboración. Revisa cada convocatoria para conocer sus requisitos y responsabilidades.</p>
  </div>
</details>
<details>
  <summary>¿Puedo hacer voluntariado usando mis conocimientos o experiencia profesional?</summary>
  <div>
    <p>Sí. También existen oportunidades de voluntariado profesional o pro bono en áreas como comunicación, diseño, tecnología, gestión, administración, contabilidad, proyectos, formación y otras especialidades. Si tienes conocimientos que quieres poner al servicio de una organización, busca oportunidades que requieran ese tipo de apoyo.</p>
  </div>
</details>
<details>
  <summary>¿Necesito experiencia para ser voluntario o voluntaria?</summary>
  <div>
    <p>No necesariamente. Los requisitos dependen de cada oportunidad. Algunas actividades requieren conocimientos o experiencia específicos, mientras que otras están abiertas a personas que simplemente quieran aportar su tiempo y disposición. Revisa siempre los requisitos indicados en la convocatoria antes de postular.</p>
  </div>
</details>
<details>
  <summary>¿Cómo me inscribo a un voluntariado?</summary>
  <div>
    <p>Cuando encuentres una oportunidad que te interese, revisa su información y postula a través de Voluntariados Chile. La organización responsable de la actividad será la encargada de entregarte la información y los siguientes pasos.</p>
  </div>
</details>
<details>
  <summary>¿A qué me comprometo cuando me inscribo como voluntario o voluntaria?</summary>
  <div>
    <p>Al inscribirte te comprometes a participar de manera responsable en la actividad a la que postulaste. Si finalmente no puedes asistir, avisa con anticipación a la organización responsable utilizando los datos de contacto que recibas al inscribirte.</p>
  </div>
</details>

<h2>4. Otras formas de participar y compartir</h2>
<details>
  <summary>¿Puedo organizar mi propia actividad solidaria?</summary>
  <div>
    <p>Sí. Si quieres realizar una acción solidaria o comunitaria sin fines de lucro, puedes publicarla en la plataforma mediante la opción “Quiero organizar actividades”. La actividad puede realizarse el 4 o 5 de diciembre o en otra fecha durante el año, y debe ser gestionada por quien la organiza.</p>
  </div>
</details>
<details>
  <summary>¿Cómo puedo compartir una actividad que me interesa?</summary>
  <div>
    <p>En la ficha de cada actividad encontrarás opciones para compartirla, incluyendo WhatsApp, Facebook y la posibilidad de copiar el enlace. Así puedes invitar a familiares, amistades y otras personas de tu comunidad a participar.</p>
  </div>
</details>
<details>
  <summary>¿Cómo puedo difundir mi participación en la celebración del Día del Patrimonio Social?</summary>
  <div>
    <p>Si eres voluntario, puedes difundir tu participación utilizando los materiales del <a href="https://drive.google.com/drive/folders/1MHkgL8Cfstr_AP19yRDhQ5Z8SUomHOaL" target="_blank" rel="noopener">kit de difusión</a>.</p>
  </div>
</details>
</div>
HTML,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', self::SLUG)->delete();
    }
};
