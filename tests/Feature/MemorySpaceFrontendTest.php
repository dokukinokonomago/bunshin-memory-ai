<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemorySpaceFrontendTest extends TestCase
{
    public function test_root_route_redirects_to_memory_space_screen(): void
    {
        $this->get('/')
            ->assertRedirect('/memory-space');
    }

    public function test_memory_space_screen_route_renders_vite_shell(): void
    {
        $this->withoutVite();

        $this->get('/memory-space')
            ->assertOk()
            ->assertSee('memory-space-root', false)
            ->assertSee('data-api-base="/api/v1"', false)
            ->assertSee('memory-space-canvas', false)
            ->assertSee('controls-toggle', false)
            ->assertSee('list-toggle', false)
            ->assertSee('admin-link', false)
            ->assertSee('href="http://localhost/admin"', false)
            ->assertSee('login-form', false)
            ->assertSee('login-email', false)
            ->assertSee('login-password', false)
            ->assertSee('login-status', false)
            ->assertSee('unlock-dialog', false);
    }

    public function test_admin_route_renders_connected_mockup_shell(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('分身AI', false)
            ->assertSee('memory-space-link', false)
            ->assertSee('href="/memory-space"', false)
            ->assertSee('/admin-assets/styles.css', false)
            ->assertSee('/admin-assets/app.js', false);
    }
}
