<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
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
        'activity_id',
        'name',
        'type',
        'status',
        'source',
        'task_at',
        'next_follow_up_at',
        'is_active',
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
            'task_at' => 'date',
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
    public static function types(): array
    {
        return self::TYPES;
    }

    /**
     * Scope a query to tasks owned by a given user.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Get the user that owns the task.
     *
     * @return BelongsTo<User, Task>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company associated with the task.
     *
     * @return BelongsTo<Company, Task>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact associated with the task.
     *
     * @return BelongsTo<Contact, Task>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the related activity for this task.
     *
     * @return BelongsTo<Activity, Task>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
