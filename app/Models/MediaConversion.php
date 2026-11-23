<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MediaConversion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['audio_index' => 'integer', 'percent' => 'integer', 'cancel_requested' => 'boolean'];
    }

    public function publicState(): array
    {
        return [
            'key' => $this->id, 'state' => $this->status, 'percent' => $this->percent,
            'message' => $this->message, 'audio' => $this->audio_index,
            'url' => $this->status === 'ready' ? route('conversions.stream', $this->id, false) : null,
        ];
    }
}
