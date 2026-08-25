<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityCollaborator extends Model
{
    use HasFactory;

    /** Los tipos que ofrece el select de mi-cuenta.html: TIPOS sin "Otra". */
    public const TIPOS = [
        'Organización sin fines de lucro',
        'Empresa o institución privada',
        'Institución educativa',
        'Municipalidad u organismo público',
    ];

    protected $fillable = ['activity_id', 'nombre', 'tipo', 'orden'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
