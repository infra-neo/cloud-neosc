<?php

use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;

test('the server wizard lists machine plans and leaves hosting products out', function () {
    Currency::factory()->create(['is_default' => true]);

    $vm = Product::factory()->create(['server_type' => 'opennebula', 'name' => 'Workstation M', 'show_domain_options' => false]);
    Pricing::factory()->create(['type' => 'product', 'rel_id' => $vm->id, 'monthly' => 890.00]);

    $hosting = Product::factory()->create(['server_type' => 'panelica', 'name' => 'Hosting Basico']);
    Pricing::factory()->create(['type' => 'product', 'rel_id' => $hosting->id, 'monthly' => 99.00]);

    $this->get(route('client.wizard.servers'))
        ->assertOk()
        ->assertSee('Workstation M')
        ->assertDontSee('Hosting Basico');
});

test('the wizard shows an empty state when no machine plan is sold', function () {
    Currency::factory()->create(['is_default' => true]);

    $this->get(route('client.wizard.servers'))->assertOk()->assertSee('wz-empty', false);
});
