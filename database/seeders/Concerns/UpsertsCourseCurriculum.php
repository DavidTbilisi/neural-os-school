<?php

namespace Database\Seeders\Concerns;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Page;
use App\Support\KnowledgeLadder;

/**
 * In-place course curriculum upsert — the enrollment-safe replacement for the
 * old delete+recreate pattern, which cascade-wiped learner state (enrollments,
 * lesson completions) and churned the module IDs that gym items tag.
 *
 * Identity keys: the course by slug, modules by title, lessons by page —
 * looked up course-wide, so a lesson moved between modules keeps its id and
 * its completions. Renaming a module is re-authoring: the old row (and any
 * lessons still under it) is pruned. Whatever leaves the curriculum is
 * deleted; everything else is updated in place. A page can appear as at most
 * one lesson per course.
 */
trait UpsertsCourseCurriculum
{
    /**
     * @param  array  $attributes  course attributes (everything but the slug)
     * @param  array  $curriculum  rows of [title, summary, page-slugs[], ...flags];
     *                             flags: 'optional' and/or an int Knowledge Ladder target rung
     * @return array{course: Course, lessons: int, missing: list<string>}
     */
    protected function upsertCourse(string $slug, array $attributes, array $curriculum): array
    {
        $course = Course::updateOrCreate(['slug' => $slug], $attributes);

        $existingModules = $course->modules()->get()->keyBy('title');
        $existingLessons = Lesson::whereIn('module_id', $existingModules->pluck('id'))
            ->get()->keyBy('page_id');

        $pages = Page::public()->pluck('id', 'slug');
        $missing = [];
        $lessonCount = 0;
        $keptModuleIds = [];
        $keptLessonIds = [];

        foreach ($curriculum as $mi => $row) {
            [$title, $summary, $slugs] = $row;
            $flags = array_slice($row, 3);
            $optional = in_array('optional', $flags, true);
            $targetRung = collect($flags)->first(fn ($v) => is_int($v)) ?? KnowledgeLadder::DEFAULT_TARGET;

            $module = $existingModules->get($title) ?? $course->modules()->make(['title' => $title]);
            $module->fill(['summary' => $summary, 'target_rung' => $targetRung, 'sort' => $mi])->save();
            $keptModuleIds[] = $module->id;

            foreach ($slugs as $li => $pageSlug) {
                $pageId = $pages->get($pageSlug);
                if (! $pageId) {
                    $missing[] = $pageSlug;

                    continue;
                }

                $lesson = $existingLessons->get($pageId) ?? new Lesson(['page_id' => $pageId]);
                $lesson->fill([
                    'module_id' => $module->id,
                    'title' => Page::whereKey($pageId)->value('title'),
                    'optional' => $optional,
                    'sort' => $li,
                ])->save();
                $keptLessonIds[] = $lesson->id;
                $lessonCount++;
            }
        }

        // Prune what left the curriculum — stale lessons under kept modules
        // first (re-parented ones are already in the kept set), then stale
        // modules (their remaining lessons cascade).
        Lesson::whereIn('module_id', $keptModuleIds)->whereNotIn('id', $keptLessonIds)->delete();
        $course->modules()->whereNotIn('id', $keptModuleIds)->get()->each->delete();

        return ['course' => $course, 'lessons' => $lessonCount, 'missing' => $missing];
    }
}
