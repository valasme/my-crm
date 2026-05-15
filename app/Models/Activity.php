<?php

namespace App\Models;

use App\Observers\ActivityTimelineObserver;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ActivityTimelineObserver::class])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['planned', 'completed', 'canceled'];

    /**
     * @var array<int, string>
     */
    public const TYPES = ['call', 'email', 'meeting', 'note'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'contact_id',
        'name',
        'type',
        'status',
        'source',
        'activity_at',
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
            'activity_at' => 'date',
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
     * Scope a query to activities owned by a given user.
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Get the user that owns the activity.
     *
     * @return BelongsTo<User, Activity>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company associated with the activity.
     *
     * @return BelongsTo<Company, Activity>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact associated with the activity.
     *
     * @return BelongsTo<Contact, Activity>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get tasks associated with the activity.
     *
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get deals associated with the activity.
     *
     * @return HasMany<Deal>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
