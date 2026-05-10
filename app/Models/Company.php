<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['lead', 'prospect', 'customer', 'churned'];

    /**
     * @var array<int, string>
     */
    public const PREFERRED_CONTACT_METHODS = [
        'email',
        'phone',
        'linkedin',
        'any',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'legal_name',
        'status',
        'industry',
        'source',
        'ownership_type',
        'founded_year',
        'employee_count',
        'annual_revenue',
        'website',
        'linkedin_url',
        'email',
        'billing_email',
        'phone',
        'support_phone',
        'timezone',
        'preferred_contact_method',
        'tax_id',
        'primary_contact_name',
        'primary_contact_email',
        'primary_contact_phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'last_contacted_at',
        'next_follow_up_at',
        'is_active',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
            'annual_revenue' => 'decimal:2',
            'employee_count' => 'integer',
            'last_contacted_at' => 'date',
            'next_follow_up_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return self::STATUSES;
    }

    /**
     * @return array<int, string>
     */
    public static function preferredContactMethods(): array
    {
        return self::PREFERRED_CONTACT_METHODS;
    }

    /**
     * Scope a query to companies owned by a given user.
     *
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Get the user that owns the company.
     *
     * @return BelongsTo<User, Company>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get contacts associated with the company.
     *
     * @return HasMany<Contact>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Get activities associated with the company.
     *
     * @return HasMany<Activity>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Get tasks associated with the company.
     *
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
