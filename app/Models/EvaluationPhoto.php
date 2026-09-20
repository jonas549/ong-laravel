<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fotografía de una respuesta a la encuesta.
 *
 * No lleva su propia autorización: el consentimiento va en la evaluación, que
 * es donde lo firmó quien respondió. Ver la migración que crea esta tabla.
 */
class EvaluationPhoto extends Model
{
    protected $table = 'activity_evaluation_photos';

    protected $fillable = ['activity_evaluation_id', 'ruta', 'orden'];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ActivityEvaluation::class, 'activity_evaluation_id');
    }

    /** Si la autorizó quien la subió. Vive en la evaluación, no aquí. */
    public function estaAutorizada(): bool
    {
        return (bool) $this->evaluation?->foto_autorizada;
    }

    /** La extensión, para nombrar el archivo al descargarlo o adoptarlo. */
    public function extension(): string
    {
        return pathinfo($this->ruta, PATHINFO_EXTENSION) ?: 'jpg';
    }
}
