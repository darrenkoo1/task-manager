<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use App\Events\TaskUpdated;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $tasks = Task::with('dependencies')->get()->map(function ($task) {
            $task->complete = $task->isComplete();
            return $task;
        });

        if ($status) {
            $tasks = $tasks->filter(function ($task) use ($status) {
                return $task->status === $status;
            });
        }

        return response()->json($tasks->values());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:tasks,id',
        ]);

        if (isset($validated['parent_id'])) {
            $parentTask = Task::find($validated['parent_id']);
            $newTask = new Task(['name' => $validated['name'], 'parent_id' => $validated['parent_id']]);

            if ($newTask->isDescendantOf($parentTask->id)) {
                return response()->json(['error' => 'Circular dependency detected.'], 422);
            }
        }

        $task = Task::create([
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'status' => 'IN_PROGRESS'
        ]);

        return response()->json($task, 201);
    }

    public function updateStatus($id)
    {
        try {
            \DB::beginTransaction();

            $task = Task::findOrFail($id);

            // Check if this is a parent task trying to revert to IN_PROGRESS
            if (($task->status === 'DONE' || $task->status === 'COMPLETE') &&
                $task->dependencies()->exists()
            ) {
                return response()->json(['error' => 'Cannot revert parent task to IN_PROGRESS once it has been marked as DONE'], 400);
            }

            if ($task->status === 'DONE' || $task->status === 'COMPLETE') {
                $task->status = 'IN_PROGRESS';
            } else {
                // Check if we can mark it as DONE
                if ($this->hasIncompleteDependencies($task)) {
                    return response()->json(['error' => 'Dependencies incomplete'], 400);
                }
                $task->status = 'DONE';
            }

            $task->save();

            // After saving the task, check if we need to update parent status
            $this->updateParentStatus($task);

            broadcast(new TaskUpdated($task))->toOthers();

            \DB::commit();
            return response()->json($task);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Failed to update task status. Please try again.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'sometimes|nullable|exists:tasks,id',
        ]);

        if (isset($validated['parent_id'])) {
            // Check if the new parent would create a circular dependency
            $newParentTask = Task::find($validated['parent_id']);

            if ($newParentTask && $newParentTask->isDescendantOf($task->id)) {
                return response()->json(['error' => 'Circular dependency detected.'], 422);
            }

            // Check if trying to set itself as parent
            if ($validated['parent_id'] == $task->id) {
                return response()->json(['error' => 'Task cannot be its own parent.'], 422);
            }
        }

        $task->update($validated);

        broadcast(new TaskUpdated($task))->toOthers();

        return response()->json($task);
    }

    private function hasIncompleteDependencies(Task $task): bool
    {
        $dependencies = Task::where('parent_id', $task->id)->get();
        return $dependencies->contains(fn($dep) => !in_array($dep->status, ['DONE', 'COMPLETE']));
    }

    private function updateParentStatus(Task $task)
    {
        if (!$task->parent_id) {
            return;
        }

        $parent = Task::find($task->parent_id);
        if (!$parent) {
            return;
        }

        // Get all siblings (including this task)
        $siblings = Task::where('parent_id', $parent->id)->get();

        // Check if all siblings are DONE or COMPLETE
        $allSiblingsDone = $siblings->every(function($sibling) {
            return in_array($sibling->status, ['DONE', 'COMPLETE']);
        });

        if ($allSiblingsDone) {
            // If all siblings are done, mark parent as COMPLETE
            $parent->status = 'COMPLETE';
        } elseif ($parent->status === 'COMPLETE') {
            // If not all siblings are done but parent was COMPLETE, mark as DONE
            $parent->status = 'DONE';
        }

        $parent->save();

        // Recursively update the parent's parent
        $this->updateParentStatus($parent);
    }
}
