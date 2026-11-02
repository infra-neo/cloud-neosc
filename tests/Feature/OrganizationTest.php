<?php

use App\Models\Client;
use App\Models\User;
use App\Support\ClientOrg;

function orgUser(bool $owner = true): array
{
    $user = User::factory()->create(['email' => 'org_'.uniqid().'@example.com']);
    $client = Client::factory()->create(['email' => $user->email]);
    $user->clients()->attach($client->id, ['owner' => $owner, 'permissions' => null]);

    return [$user, $client];
}

beforeEach(fn () => config(['services.zitadel.enabled' => false]));

test('guests are sent to login', function () {
    $this->get(route('client.organization.index'))->assertRedirect();
});

test('owner creates a department and a user with a role', function () {
    [$user, $client] = orgUser();

    $this->actingAs($user)->post(route('client.organization.departments.store'), ['name' => 'Contabilidad'])->assertRedirect();
    $dept = ClientOrg::get($client)['departments'][0];

    $this->actingAs($user)->post(route('client.organization.members.store'), [
        'name' => 'Luis Pérez', 'email' => 'Luis@Example.com', 'role' => 'manager', 'departments' => [$dept['id'], 'ghost'],
    ])->assertRedirect();

    $m = ClientOrg::get($client)['members'][0];
    expect($m['email'])->toBe('luis@example.com')
        ->and($m['role'])->toBe('manager')
        ->and($m['departments'])->toBe([$dept['id']]);
});

test('duplicate email and invalid role are refused', function () {
    [$user, $client] = orgUser();
    $payload = ['name' => 'A B', 'email' => 'a@example.com', 'role' => 'user'];
    $this->actingAs($user)->post(route('client.organization.members.store'), $payload);
    $this->actingAs($user)->post(route('client.organization.members.store'), $payload)->assertSessionHasErrors('email');
    $this->actingAs($user)->post(route('client.organization.members.store'), ['name' => 'X Y', 'email' => 'x@example.com', 'role' => 'root'])->assertSessionHasErrors('role');
    expect(ClientOrg::get($client)['members'])->toHaveCount(1);
});

test('a non-owner can look but not change', function () {
    [$user, $client] = orgUser(false);
    $this->actingAs($user)->get(route('client.organization.index'))->assertOk();
    $this->actingAs($user)->post(route('client.organization.departments.store'), ['name' => 'X'])->assertForbidden();
});

test('deleting a department removes it from members', function () {
    [$user, $client] = orgUser();
    $this->actingAs($user)->post(route('client.organization.departments.store'), ['name' => 'Ventas']);
    $d = ClientOrg::get($client)['departments'][0]['id'];
    $this->actingAs($user)->post(route('client.organization.members.store'), ['name' => 'Ana R', 'email' => 'ana@example.com', 'role' => 'user', 'departments' => [$d]]);
    $this->actingAs($user)->delete(route('client.organization.departments.destroy', $d))->assertRedirect();
    $org = ClientOrg::get($client);
    expect($org['departments'])->toBeEmpty()->and($org['members'][0]['departments'])->toBeEmpty();
});
