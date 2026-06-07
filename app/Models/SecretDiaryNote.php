<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretDiaryNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_date',
        'title',
        'body',
        'cover_image',
        'photo_dedication',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coverImageUrl(): ?string
    {
        return $this->cover_image
            ? '/storage/'.ltrim($this->cover_image, '/')
            : null;
    }
}
