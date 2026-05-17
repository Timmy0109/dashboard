<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'invite_code', 'invite_code_expires_at', 'status', 'created_by'];

    protected static function booted(): void
    {
        static::deleting(function (Company $company) {
            // Suspend active users when company is soft-deleted, mark them so restore can undo this.
            $company->users()
                ->where('status', 'active')
                ->update(['status' => 'suspended', 'suspended_by_company_delete' => true]);
        });

        static::restoring(function (Company $company) {
            // Re-activate only users that were suspended by this company's deletion.
            $company->users()
                ->where('suspended_by_company_delete', true)
                ->update(['status' => 'active', 'suspended_by_company_delete' => false]);
        });
    }

    protected function casts(): array
    {
        return [
            'invite_code_expires_at' => 'datetime',
        ];
    }

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

    private ?array $cachedFeatureKeys = null;

    /** Return list of enabled feature keys, memoized per model instance. */
    public function enabledFeatureKeys(): array
    {
        if ($this->cachedFeatureKeys === null) {
            $this->cachedFeatureKeys = $this->companyFeatures()
                ->where('enabled', true)
                ->pluck('feature_key')
                ->toArray();
        }

        return $this->cachedFeatureKeys;
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->enabledFeatureKeys(), true);
    }

    public function regenerateInviteCode(int $validDays = 90): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = strtoupper(Str::random(12));
            try {
                $this->update([
                    'invite_code'            => $code,
                    'invite_code_expires_at' => now()->addDays($validDays),
                ]);
                return $code;
            } catch (QueryException $e) {
                if ($i === 4) throw $e;
            }
        }
        throw new \RuntimeException('Failed to generate unique invite code');
    }

    public static function findByInviteCode(string $code): ?self
    {
        return self::whereRaw('UPPER(invite_code) = ?', [strtoupper($code)])
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('invite_code_expires_at')
                  ->orWhere('invite_code_expires_at', '>', now());
            })
            ->first();
    }
}
