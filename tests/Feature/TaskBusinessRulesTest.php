<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_task_cannot_be_marked_in_progress_if_done()
    {
        // Arrange: Create parent with completed child
        $parent = Task::create(['name' => 'Parent', 'status' => 'DONE']);
        $child = Task::create([
            'name' => 'Child',
            'parent_id' => $parent->id,
            'status' => 'DONE'
        ]);

        // Act: Try to mark parent as in progress
        $response = $this->postJson("/api/tasks/{$parent->id}/toggle");

        // Assert: Should fail
        $response->assertStatus(400)
                ->assertJson(['error' => 'Cannot revert parent task to IN_PROGRESS once it has been marked as DONE']);
    }

    public function test_circular_dependencies_are_prevented()
    {
        // Arrange: Create a chain A -> B
        $taskA = Task::create(['name' => 'A', 'status' => 'IN_PROGRESS']);
        $taskB = Task::create([
            'name' => 'B',
            'parent_id' => $taskA->id,
            'status' => 'IN_PROGRESS'
        ]);

        // Act: Try to make A a child of B
        $response = $this->putJson("/api/tasks/{$taskA->id}", [
            'parent_id' => $taskB->id
        ]);

        // Assert: Should fail
        $response->assertStatus(422)
                ->assertJson(['error' => 'Circular dependency detected.']);
    }
} 
