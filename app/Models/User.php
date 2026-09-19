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

    // The users table has no name column - names live in employee_numbers.Full_Name.
    // Every view should use $user->display_name instead of ->full_name / ->name.
    public function getDisplayNameAttribute(): string
    {
        $name = optional($this->employee_numbers)->Full_Name;

        if (! $name && $this->email) {
            $name = Str::before($this->email, '@');
        }

        $name = trim((string) $name);

        return $name !== '' ? $name : 'User';
    }

    public function getInitialsAttribute(): string
    {
        $name = $this->display_name;
        $parts = array_values(array_filter(preg_split('/\s+/', $name) ?: []));
        $first = (string) ($parts[0] ?? '');

        if (count($parts) > 1) {
            $initials = Str::substr($first, 0, 1).Str::substr((string) end($parts), 0, 1);
        } else {
            $initials = Str::substr($first, 0, 2);
        }

        return Str::upper($initials) ?: 'U';
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
