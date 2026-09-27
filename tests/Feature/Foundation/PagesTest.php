<?php

use App\Models\Branch;

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk()->assertSee('Welcome back');
});

it('redirects guests to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('renders phase 1 pages for the owner', function (string $url) {
    actingAsRole('owner');
    $this->get($url)->assertOk();
})->with([
    '/dashboard', '/branches', '/branches/create', '/users', '/users/create', '/roles', '/roles/create',
    '/activity', '/settings', '/settings/currency', '/settings/receipt', '/settings/pos', '/settings/payments',
    '/settings/notifications', '/settings/localisation', '/settings/prefixes', '/profile', '/notifications',
]);

it('renders branch and user detail pages', function () {
    $owner = actingAsRole('owner');
    $branch = Branch::first();
    $this->get(route('branches.show', $branch))->assertOk()->assertSee($branch->name);
    $this->get(route('branches.edit', $branch))->assertOk();
    $this->get(route('users.show', $owner))->assertOk()->assertSee($owner->name);
    $this->get(route('users.edit', $owner))->assertOk();
});

it('forbids cashiers from settings and users', function () {
    actingAsRole('cashier');
    $this->get('/settings')->assertForbidden();
    $this->get('/users')->assertForbidden();
    $this->get('/roles')->assertForbidden();
});
