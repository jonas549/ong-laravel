<?php

namespace App\Console\Commands;

use App\Models\EmailTemplate;
use App\Models\Setting;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RegionCommuneSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Console\Command;

/**
 * `php artisan dps:instalar` — deja la base con lo que el sistema necesita
 * para funcionar, sin datos de demostración.
 *
 * Existe por un agujero del despliegue: el cron del servidor corre `migrate`
 * pero nunca `db:seed`, así que en producción las tablas estaban creadas y
 * vacías. Sin filas en `email_templates`, `CorreoTransaccional` no encuentra la
 * plantilla y devuelve false en silencio: los cinco correos automáticos no se
 * envían y no dejan rastro en ninguna parte.
 *
 * Es idempotente —todos los seeders que llama usan `firstOrCreate`— así que se
 * puede poner en el cron de despliegue junto a `migrate --force` y correrlo en
 * cada pull sin miedo a pisar lo que la ONG haya editado.
 *
 * Deliberadamente NO llama a UserSeeder (crea cuentas con contraseñas de
 * prueba), ni a ContentSeeder ni ActivitySeeder (contenido de ejemplo).
 */
class Instalar extends Command
{
    protected $signature = 'dps:instalar {--seco : Dice qué falta sin escribir nada}';

    protected $description = 'Siembra lo imprescindible (ajustes, plantillas de correo, regiones y taxonomías) sin datos de demo';

    public function handle(): int
    {
        $seco = (bool) $this->option('seco');

        $faltanPlantillas = collect(EmailTemplate::CATALOGO)
            ->keys()
            ->reject(fn (string $clave) => EmailTemplate::where('clave', $clave)->exists());

        $ajustes = Setting::count();

        $this->newLine();
        $this->line('  <options=bold>Estado antes de sembrar</>');
        $this->line(sprintf('     <fg=gray>%-28s</> %s', 'Plantillas de correo', EmailTemplate::count().' de '.count(EmailTemplate::CATALOGO)));
        $this->line(sprintf('     <fg=gray>%-28s</> %s', 'Ajustes', (string) $ajustes));

        if ($faltanPlantillas->isNotEmpty()) {
            $this->line('     <fg=yellow>!</> Faltan: '.$faltanPlantillas->implode(', '));
        }

        if ($seco) {
            $this->newLine();
            $this->line('  <fg=gray>Modo seco: no se ha escrito nada.</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  <options=bold>Sembrando</>');

        foreach ([
            'Regiones y comunas' => RegionCommuneSeeder::class,
            'Taxonomías' => TaxonomySeeder::class,
            'Ajustes' => SettingsSeeder::class,
            'Plantillas de correo' => EmailTemplateSeeder::class,
        ] as $etiqueta => $seeder) {
            (new $seeder)->setContainer(app())->setCommand($this)->run();
            $this->line("     <fg=green>✓</> {$etiqueta}");
        }

        // Los ajustes se leen de una caché que vive para siempre; sin esto, el
        // proceso siguiente seguiría viendo la foto anterior.
        cache()->forget(Setting::CACHE_KEY);

        $this->newLine();
        $this->line('  <fg=green;options=bold>Listo.</> Comprueba el correo con <fg=yellow>php artisan dps:correo</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
