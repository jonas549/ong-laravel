<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'to', 'cc', 'bcc', 'subject', 'body_html', 'mailable',
        'status', 'error', 'sent_at', 'attempts', 'related_type', 'related_id',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function related()
    {
        return $this->morphTo();
    }

    public function scopeFallidos($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeEnviados($query)
    {
        return $query->where('status', 'sent');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status === 'sent' ? 'Enviado' : 'Falló';
    }
}
