<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

/**
 * A curated, ordered path through wiki pages: course → modules → lessons.
 * DB-authored (the interactive layer on top of the read-only markdown mirror).
 */
class Course extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PUBLISHED => 'Published',
    ];

    protected $guarded = [];

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('sort');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function sourcePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'source_page_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** Courses that must be completed first (soft-gated recommendation). */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'required_course_id',
        );
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Gyms offered as "Practice" for this course. */
    public function gyms(): HasMany
    {
        return $this->hasMany(Gym::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** Listed on the public site. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Required (non-optional) lessons across the course — the denominator for
     * progress. Eager-loads modules.lessons if not already loaded.
     *
     * @return Collection<int, Lesson>
     */
    public function requiredLessons(): Collection
    {
        return $this->allLessons()->reject->optional->values();
    }

    /** @return Collection<int, Lesson> every lesson in module order. */
    public function allLessons(): Collection
    {
        $this->loadMissing('modules.lessons');

        return $this->modules->flatMap->lessons;
    }

    /**
     * Fraction complete (0.0–1.0) for a user, over required lessons. A course with
     * no required lessons is treated as 0% until it has content.
     */
    public function progressFor(?User $user): float
    {
        if (! $user) {
            return 0.0;
        }

        $required = $this->requiredLessons();
        if ($required->isEmpty()) {
            return 0.0;
        }

        $done = LessonCompletion::where('user_id', $user->id)
            ->whereIn('lesson_id', $required->pluck('id'))
            ->count();

        return $done / $required->count();
    }

    /** Whether every prerequisite course is completed by the user (soft signal). */
    public function prerequisitesMetBy(?User $user): bool
    {
        $prereqs = $this->prerequisites;
        if ($prereqs->isEmpty()) {
            return true;
        }
        if (! $user) {
            return false;
        }

        $completed = Enrollment::where('user_id', $user->id)
            ->whereIn('course_id', $prereqs->pluck('id'))
            ->whereNotNull('completed_at')
            ->pluck('course_id');

        return $prereqs->pluck('id')->diff($completed)->isEmpty();
    }
}
