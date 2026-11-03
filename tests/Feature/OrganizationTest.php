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

test('access can be granted to a group and reaches its members', function () {
    [$user, $client] = orgUser();
    $this->actingAs($user)->post(route('client.organization.departments.store'), ['name' => 'Ventas']);
    $g = ClientOrg::get($client)['departments'][0]['id'];
    $this->actingAs($user)->post(route('client.organization.members.store'), ['name' => 'Eva M', 'email' => 'eva@example.com', 'role' => 'user', 'departments' => [$g]]);
    $this->actingAs($user)->post(route('client.organization.members.store'), ['name' => 'Raúl P', 'email' => 'raul@example.com', 'role' => 'user']);
    $org = ClientOrg::get($client);
    [$eva, $raul] = [$org['members'][0]['id'], $org['members'][1]['id']];

    $service = \App\Models\Service::factory()->create(['client_id' => $client->id, 'domain' => 'srv1']);
    $this->actingAs($user)->put(route('client.organization.access.update', $service->id), ['groups' => [$g], 'members' => [$raul, 'ghost']])->assertRedirect();

    $org = ClientOrg::get($client);
    expect(ClientOrg::effectiveMemberIds($org, (string) $service->id))->toEqualCanonicalizing([$eva, $raul])
        ->and(ClientOrg::serviceIdsFor($org, $eva))->toBe([(string) $service->id]);
});

test('a member only sees the services granted to them', function () {
    [$owner, $client] = orgUser();
    $member = User::factory()->create(['email' => 'eva_'.uniqid().'@example.com']);
    $client->users()->attach($member->id, ['owner' => false]);
    $granted = \App\Models\Service::factory()->create(['client_id' => $client->id, 'domain' => 'ok.example']);
    $other = \App\Models\Service::factory()->create(['client_id' => $client->id, 'domain' => 'no.example']);

    $this->actingAs($owner)->post(route('client.organization.members.store'), ['name' => 'Eva M', 'email' => $member->email, 'role' => 'user']);
    $mid = ClientOrg::get($client)['members'][0]['id'];
    $this->actingAs($owner)->put(route('client.organization.access.update', $granted->id), ['members' => [$mid]]);

    $this->actingAs($member)->get(route('client.services.show', $granted->id))->assertOk();
    $this->actingAs($member)->get(route('client.services.show', $other->id))->assertNotFound();
    $this->actingAs($owner)->get(route('client.services.show', $other->id))->assertOk();
});
