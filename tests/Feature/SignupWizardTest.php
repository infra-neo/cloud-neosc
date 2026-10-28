<?php

use App\Mail\EmailVerificationMail;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * The sign-up wizard: identity -> billing address -> confirm your email.
 */
function wizardPayload(array $over = []): array
{
    return array_merge([
        'wizard' => '1',
        'first_name' => 'Ana',
        'last_name' => 'Lopez',
        'email' => 'ana.wizard@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'country' => 'MX',
        'address1' => 'Av. Reforma 1',
        'city' => 'CDMX',
        'postcode' => '06600',
        'tos' => '1',
    ], $over);
}

test('the register page renders the two-step wizard', function () {
    $this->get(route('client.register'))
        ->assertOk()
        ->assertSee('name="wizard"', false)
        ->assertSee('data-step="1"', false)
        ->assertSee('data-step="2"', false)
        ->assertSee('name="postcode"', false);
});

test('the wizard requires the billing address', function () {
    Mail::fake();

    $this->post(route('client.register.submit'), wizardPayload([
        'country' => '', 'address1' => '', 'city' => '', 'postcode' => '',
    ]))->assertSessionHasErrors(['country', 'address1', 'city', 'postcode']);

    expect(User::where('email', 'ana.wizard@example.com')->exists())->toBeFalse();
});

test('an unknown country code is rejected', function () {
    $this->post(route('client.register.submit'), wizardPayload(['country' => 'ZZ']))
        ->assertSessionHasErrors('country');
});

test('finishing the wizard stores the address and lands on the confirm-email page', function () {
    Mail::fake();

    $this->post(route('client.register.submit'), wizardPayload(['address2' => 'Piso 3']))
        ->assertRedirect(route('client.verification.notice'));

    $client = Client::where('email', 'ana.wizard@example.com')->firstOrFail();
    expect($client->address1)->toBe('Av. Reforma 1')
        ->and($client->address2)->toBe('Piso 3')
        ->and($client->city)->toBe('CDMX')
        ->and($client->postcode)->toBe('06600')
        ->and($client->country)->toBe('MX');

    Mail::assertSent(EmailVerificationMail::class, fn ($m) => $m->hasTo('ana.wizard@example.com'));

    $this->get(route('client.verification.notice'))
        ->assertOk()
        ->assertSee('ana.wizard@example.com');
});

test('the signed link confirms the address', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute('client.verification.verify', now()->addHour(), [
        'id' => $user->id, 'hash' => sha1($user->email),
    ]);

    $this->get($url)->assertRedirect();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('a tampered or unsigned link does not confirm anything', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->get(route('client.verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
        ->assertForbidden();

    $wrongHash = URL::temporarySignedRoute('client.verification.verify', now()->addHour(), [
        'id' => $user->id, 'hash' => sha1('someone@else.test'),
    ]);
    $this->get($wrongHash)->assertForbidden();

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('an unconfirmed address can be changed and the link is re-sent', function () {
    Mail::fake();
    $this->post(route('client.register.submit'), wizardPayload())->assertRedirect();

    $this->post(route('client.verification.change'), ['email' => 'corrected@example.com'])
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'corrected@example.com')->exists())->toBeTrue()
        ->and(Client::where('email', 'corrected@example.com')->exists())->toBeTrue();
    Mail::assertSent(EmailVerificationMail::class, fn ($m) => $m->hasTo('corrected@example.com'));
});

test('resend needs a session', function () {
    $this->post(route('client.verification.resend'))->assertRedirect(route('client.login'));
});
