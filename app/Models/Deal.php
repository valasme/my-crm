<?php

namespace App\Models;

use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        'lead',
        'qualified',
        'proposal',
        'negotiation',
        'won',
        'lost',
    ];

    /**
     * @var array<int, string>
     */
    public const TYPES = ['new_business', 'expansion', 'renewal', 'services'];

    /**
     * @var array<int, string>
     */
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'contact_id',
        'activity_id',
        'name',
        'type',
        'status',
        'source',
        'amount',
        'currency',
        'probability',
        'deal_at',
        'expected_close_at',
        'closed_at',
        'next_follow_up_at',
        'is_active',
        'sort_order',
        'outcome',
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
            'amount' => 'decimal:2',
            'probability' => 'integer',
            'deal_at' => 'date',
            'expected_close_at' => 'date',
            'closed_at' => 'date',
            'next_follow_up_at' => 'date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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
    public static function types(): array
    {
        return self::TYPES;
    }

    /**
     * @return array<int, string>
     */
    public static function currencies(): array
    {
        return self::CURRENCIES;
    }

    /**
     * @return array<int, string>
     */
    public static function closedStatuses(): array
    {
        return ['won', 'lost'];
    }

    /**
     * Scope a query to deals owned by a given user.
     *
     * @param  Builder<Deal>  $query
     * @return Builder<Deal>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Get the user that owns the deal.
     *
     * @return BelongsTo<User, Deal>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company associated with the deal.
     *
     * @return BelongsTo<Company, Deal>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact associated with the deal.
     *
     * @return BelongsTo<Contact, Deal>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the related activity for this deal.
     *
     * @return BelongsTo<Activity, Deal>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
