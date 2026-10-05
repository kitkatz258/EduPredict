<?php

namespace Tests\Feature;

use App\Livewire\Tables\DemoUsersTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FoundationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_renders_branded_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('EduPredict')
            ->assertSee('Sign in');
    }

    public function test_demo_table_page_renders(): void
    {
        User::factory()->create([
            'name' => 'Ana Santos',
            'email' => 'ana@example.com',
        ]);

        $this->get('/demo/table')
            ->assertOk()
            ->assertSee('Shared table demo')
            ->assertSeeLivewire(DemoUsersTable::class)
            ->assertSee('Ana Santos');
    }

    public function test_demo_table_search_sort_and_pagination(): void
    {
        User::factory()->create(['name' => 'Alpha User', 'email' => 'alpha@example.com']);
        User::factory()->create(['name' => 'Beta User', 'email' => 'beta@example.com']);
        User::factory()->count(12)->create();

        Livewire::test(DemoUsersTable::class)
            ->set('perPage', 10)
            ->assertSee('Alpha User')
            ->set('search', 'Beta User')
            ->assertSee('Beta User')
            ->assertDontSee('Alpha User')
            ->set('search', '')
            ->call('sortBy', 'name')
            ->assertSet('sortField', 'name');
    }
}
