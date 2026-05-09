<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
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
        'company_id',
        'name',
        'job_title',
        'status',
        'department',
        'source',
        'email',
        'alternate_email',
        'phone',
        'mobile_phone',
        'linkedin_url',
        'timezone',
        'preferred_contact_method',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'birthday',
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
            'birthday' => 'date',
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
     * Scope a query to contacts owned by a given user.
     *
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Get the user that owns the contact.
     *
     * @return BelongsTo<User, Contact>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company associated with the contact.
     *
     * @return BelongsTo<Company, Contact>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get activities associated with the contact.
     *
     * @return HasMany<Activity>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
