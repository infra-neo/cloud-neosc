<?php

use App\Models\Client;
use App\Models\User;
use App\Support\ClientBrand;

function brandingUser(): array
{
    $user = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Torres', 'email' => 'ana_'.uniqid().'@example.com']);
    $client = Client::factory()->create(['email' => $user->email, 'first_name' => 'Ana', 'last_name' => 'Torres']);
    $user->clients()->attach($client->id, ['owner' => true, 'permissions' => null]);

    return [$user, $client];
}

test('guests cannot open the branding page', function () {
    $this->get(route('client.branding.edit'))->assertRedirect();
});

test('a customer can save colour, name and domain', function () {
    [$user, $client] = brandingUser();

    $this->actingAs($user)->put(route('client.branding.update'), [
        'name' => 'Contoso Remoto',
        'color' => '#c0392b',
        'domain' => 'https://Escritorio.Contoso.mx/',
    ])->assertRedirect(route('client.branding.edit'));

    $brand = ClientBrand::get($client);
    expect($brand['name'])->toBe('Contoso Remoto')
        ->and($brand['color'])->toBe('#c0392b')
        ->and($brand['domain'])->toBe('escritorio.contoso.mx');
});

test('a colour that is not #rrggbb is refused', function () {
    [$user] = brandingUser();

    $this->actingAs($user)->put(route('client.branding.update'), ['color' => 'red;}</style><script>'])
        ->assertSessionHasErrors('color');
});

test('a domain cannot be claimed by two accounts', function () {
    [$userA] = brandingUser();
    [$userB] = brandingUser();

    $this->actingAs($userA)->put(route('client.branding.update'), ['domain' => 'portal.acme.mx']);
    $this->actingAs($userB)->put(route('client.branding.update'), ['domain' => 'portal.acme.mx'])
        ->assertSessionHasErrors('domain');
});

test('the signed-in layout renders the sidebar and the customer colour', function () {
    [$user, $client] = brandingUser();
    ClientBrand::save($client, ['color' => '#c0392b', 'name' => 'Contoso Remoto']);

    $this->actingAs($user)->get(route('client.home'))
        ->assertOk()
        ->assertSee('nsc-side', false)
        ->assertSee('--primary:#c0392b', false)
        ->assertSee('Contoso Remoto');
});
