<?php

use App\Models\User;

test('there is no public registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->where('email', 'x@example.com')->exists())->toBeFalse();
});

test('guests are redirected to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('active users can log in and land on home', function () {
    $user = staff();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('inactive users cannot log in', function () {
    $user = User::factory()->staff()->inactive()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a user deactivated mid-session is logged out on the next request', function () {
    $user = staff();
    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('users who must change their password are forced to the password page', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)->get('/')->assertRedirect(route('user-password.edit'));
    $this->actingAs($user)->get(route('user-password.edit'))->assertOk();

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->must_change_password)->toBeFalse();
    $this->actingAs($user->fresh())->get('/')->assertOk();
});
