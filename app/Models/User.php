<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['employee_numbers_id', 'department_id', 'email', 'password', 'profile_photo', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationship to employee_numbers table
    public function employee_numbers()
    {
        return $this->belongsTo(EmployeeNumber::class, 'employee_numbers_id');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        $profilePhoto = $this->profile_photo;

        if (! $profilePhoto) {
            return null;
        }

        if (Str::startsWith($profilePhoto, ['http://', 'https://', '//'])) {
            return $profilePhoto;
        }

        if (Str::startsWith($profilePhoto, '/storage/')) {
            return asset(ltrim($profilePhoto, '/'));
        }

        return Storage::disk('public')->url($profilePhoto);
    }
}
