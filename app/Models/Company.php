<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class Company extends Model
{
    protected $fillable = ['name', 'invite_code', 'status', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function managers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'manager');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'member');
    }

    public function companyFeatures(): HasMany
    {
        return $this->hasMany(CompanyFeature::class);
    }

    /** Return list of enabled feature keys for this company. */
    public function enabledFeatureKeys(): array
    {
        return $this->companyFeatures()
            ->where('enabled', true)
            ->pluck('feature_key')
            ->toArray();
    }

    public function hasFeature(string $key): bool
    {
        return $this->companyFeatures()
            ->where('feature_key', $key)
            ->where('enabled', true)
            ->exists();
    }

    public function regenerateInviteCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = strtoupper(Str::random(12));
            try {
                $this->update(['invite_code' => $code]);
                return $code;
            } catch (QueryException $e) {
                if ($i === 4) throw $e;
            }
        }
        throw new \RuntimeException('Failed to generate unique invite code');
    }

    public static function findByInviteCode(string $code): ?self
    {
        return self::where('invite_code', strtoupper($code))->where('status', 'active')->first();
    }
}
