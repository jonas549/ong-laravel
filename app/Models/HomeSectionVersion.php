<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una foto de cómo quedó una sección al publicarla.
 *
 * El historial **sólo crece**: restaurar una versión antigua no borra las que
 * vinieron después, publica una copia de la antigua y deja constancia de dónde
 * salió. Un historial del que se pueda borrar no sirve para lo único que sirve
 * un historial, que es volver atrás cuando ya no te acuerdas de qué cambiaste.
 *
 * `autor` guarda el nombre además del `user_id` porque el usuario se puede
 * borrar y la versión tiene que seguir diciendo quién la publicó.
 */
class HomeSectionVersion extends Model
{
    protected $fillable = ['home_section_id', 'user_id', 'autor', 'contenido', 'nota'];

    protected function casts(): array
    {
        return ['contenido' => 'array'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quien(): string
    {
        return $this->autor ?: ($this->user?->name ?? 'Alguien que ya no está');
    }
}
