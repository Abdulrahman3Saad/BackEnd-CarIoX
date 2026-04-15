<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CarImage extends Model
{
    use HasFactory;

    protected $fillable = ['car_id', 'image_path', 'image_url'];

    // ✅ Accessor: حساب الـ URL ديناميكياً
    public function getImageUrlAttribute(): string
    {
        return url(Storage::url($this->image_path));
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}