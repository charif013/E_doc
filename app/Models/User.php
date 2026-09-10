<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{

use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'name_prefix',
        'email',
        'password',
        'department', 
        'division',
        'work_unit',
        'position',
        'pin',         
        'signature',
        'gender',   
        'line_id',
        'line_friend_status',
        'line_connected_at',
        'line_followed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'line_friend_status' => 'boolean',
        'line_connected_at' => 'datetime',
        'line_followed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            $connection = $user->getConnectionName();
            if (! config('edoc.v2.enabled')
                || ! Schema::connection($connection)->hasTable('organization_units')) {
                return;
            }

            $db = DB::connection($connection);
            $parentId = null;
            foreach ([['DEPARTMENT', $user->department], ['DIVISION', $user->division], ['WORK_UNIT', $user->work_unit]] as [$type, $name]) {
                $name = trim((string) $name);
                if ($name === '') { continue; }
                $unit = $db->table('organization_units')->where([
                    'parent_id' => $parentId, 'unit_type' => $type, 'name' => $name,
                ])->first();
                if (! $unit) {
                    $parentId = $db->table('organization_units')->insertGetId([
                        'parent_id' => $parentId, 'unit_type' => $type, 'name' => $name,
                        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else { $parentId = $unit->id; }
            }

            $positionId = null;
            if (trim((string) $user->position) !== '') {
                $position = $db->table('positions')->where('name', $user->position)->first();
                $positionId = $position?->id ?: $db->table('positions')->insertGetId([
                    'name' => $user->position, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $db->table('users')->where('id', $user->id)->update([
                'organization_unit_id' => $parentId, 'position_id' => $positionId,
            ]);

            if ($user->wasChanged('signature') && Schema::connection($connection)->hasTable('user_signatures')) {
                $db->table('user_signatures')->where('user_id', $user->id)->update([
                    'is_active' => false, 'revoked_at' => now(), 'updated_at' => now(),
                ]);
                if ($user->signature) {
                    $encoded = preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', (string) $user->signature);
                    $binary = base64_decode((string) $encoded, true);
                    if ($binary !== false) {
                        $hash = hash('sha256', $binary);
                        $path = "private/v2-signatures/{$user->id}-{$hash}.png";
                        Storage::disk('local')->put($path, $binary);
                        $db->table('user_signatures')->updateOrInsert(
                            ['user_id' => $user->id, 'sha256' => $hash],
                            ['file_path' => $path, 'is_active' => true, 'valid_from' => now(),
                                'valid_until' => null, 'revoked_at' => null, 'created_at' => now(), 'updated_at' => now()]
                        );
                    }
                }
            }
        });
    }
}
