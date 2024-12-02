<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Task;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase; // Reset the database for each test

    public function test_can_list_tasks()
    {
        // Arrange: Create some test tasks
        Task::create(['name' => 'Task A', 'status' => 'IN_PROGRESS']);
        Task::create(['name' => 'Task B', 'status' => 'DONE']);

        // Act: Call the API endpoint
        $response = $this->getJson('/api/tasks');

        // Assert: Check the response
        $response->assertStatus(200)
                ->assertJsonCount(2);
    }

    public function test_can_create_task()
    {
        $response = $this->postJson('/api/tasks', [
            'name' => 'New Task'
        ]);

        $response->assertStatus(201)
                ->assertJson(['name' => 'New Task']);
    }

    public function test_can_update_task_status()
    {
        // Arrange
        $task = Task::create(['name' => 'Task A', 'status' => 'IN_PROGRESS']);

        // Act
        $response = $this->postJson("/api/tasks/{$task->id}/toggle");

        // Assert
        $response->assertStatus(200)
                ->assertJson(['status' => 'DONE']);
    }
}
