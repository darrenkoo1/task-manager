<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_id', 'status'];

    protected $appends = ['complete', 'dependency_stats'];

    /**
     * Get the complete attribute.
     *
     * @return bool
     */
    public function getCompleteAttribute(): bool
    {
        return $this->isComplete();
    }

    public function isComplete(): bool
    {
        // If the task itself isn't DONE, it can't be complete
        if ($this->status !== 'DONE') {
            return false;
        }

        // Get all dependencies
        $dependencies = $this->dependencies;

        // If there are no dependencies, and the task is DONE, it's complete
        if ($dependencies->isEmpty()) {
            return true;
        }

        // Check if all dependencies are complete (recursive check)
        return $dependencies->every(function ($dependency) {
            return $dependency->isComplete();
        });
    }

    /**
     * Check if this task is a descendant of the given task ID.
     *
     * @param int $taskId
     * @return bool
     */
    public function isDescendantOf(int $taskId): bool
    {
        // For a new task, just check if the parent_id matches the taskId
        if (!$this->exists) {
            return $this->parent_id === $taskId ? false : true;
        }

        $parent = $this->parent_id;
        while ($parent !== null) {
            if ($parent === $taskId) {
                return true;
            }
            $parent = Task::find($parent)?->parent_id;
        }

        return false;
    }

    public function dependencies()
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function getDependencyStatsAttribute()
    {
        $allDescendants = $this->getAllDescendants();

        return [
            'total' => $allDescendants->count(),
            'done' => $allDescendants->where('status', 'DONE')->count(),
            'complete' => $allDescendants->where('status', 'COMPLETE')->count(),
        ];
    }

    public function getAllDescendants()
    {
        $descendants = collect();

        $children = $this->dependencies;
        foreach ($children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }
}
