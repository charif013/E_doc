<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasRoles, SoftDeletes;

    protected $connection = 'mysql_v2';
    protected $guarded = [];
    protected $hidden = ['password', 'pin', 'remember_token'];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'pin_reset_requested' => 'boolean',
        'line_friend_status' => 'boolean',
        'line_connected_at' => 'datetime',
        'line_followed_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function organizationUnit(): BelongsTo { return $this->belongsTo(OrganizationUnit::class); }
    public function positionRecord(): BelongsTo { return $this->belongsTo(Position::class, 'position_id'); }
    public function signatures(): HasMany { return $this->hasMany(UserSignature::class); }

    protected function department(): Attribute { return Attribute::get(fn () => $this->unitName('DEPARTMENT')); }
    protected function division(): Attribute { return Attribute::get(fn () => $this->unitName('DIVISION')); }
    protected function workUnit(): Attribute { return Attribute::get(fn () => $this->unitName('WORK_UNIT')); }
    protected function position(): Attribute { return Attribute::get(fn () => $this->positionRecord?->name); }
    protected function signature(): Attribute
    {
        return Attribute::get(function () {
            $signature = $this->signatures()->where('is_active', true)->whereNull('revoked_at')->latest('id')->first();
            if (! $signature || ! Storage::disk('local')->exists($signature->file_path)) { return null; }
            return 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($signature->file_path));
        });
    }

    private function unitName(string $type): ?string
    {
        $unit = $this->organizationUnit;
        while ($unit) {
            if ($unit->unit_type === $type) { return $unit->name; }
            $unit = $unit->parent;
        }
        return null;
    }
}
