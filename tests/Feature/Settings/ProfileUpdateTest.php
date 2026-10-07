<?php

use App\Models\User;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'test@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com');
});

test('users cannot delete their own account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/settings/profile')->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
});
