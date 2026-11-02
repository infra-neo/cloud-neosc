<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Support\ClientBrand;
use Illuminate\Http\Request;

/**
 * "Personalización": the customer's own look for the portal their users see.
 */
class BrandingController extends Controller
{
    use ResolvesClient;

    public function edit()
    {
        $client = $this->currentClient();
        abort_unless($client, 404);

        return view('client.account.branding', [
            'client' => $client,
            'brand' => ClientBrand::get($client),
            'cnameTarget' => ClientBrand::cnameTarget(),
        ]);
    }

    public function update(Request $request)
    {
        $client = $this->currentClient();
        abort_unless($client, 404);

        $request->merge(['domain' => ClientBrand::normalizeDomain($request->input('domain'))]);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'domain' => ['nullable', 'string', 'max:253'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:256'],
        ]);

        $current = ClientBrand::get($client);
        $domain = $data['domain'] ?? '';

        if ($domain !== '') {
            if (! ClientBrand::isValidDomain($domain)) {
                return back()->withErrors(['domain' => __('portal.brand.domain_invalid')])->withInput();
            }
            if (ClientBrand::domainTaken($domain, (int) $client->id)) {
                return back()->withErrors(['domain' => __('portal.brand.domain_taken')])->withInput();
            }
        }

        $update = [
            'name' => trim((string) ($data['name'] ?? '')),
            'color' => ClientBrand::normalizeColor($data['color'] ?? '') ?? '',
            'domain' => $domain,
            // A changed domain has to be verified again.
            'domain_verified_at' => $domain === $current['domain'] ? $current['domain_verified_at'] : '',
        ];

        foreach (['logo' => 'logo', 'favicon' => 'favicon'] as $field => $stem) {
            if ($request->boolean('remove_'.$field)) {
                $update[$field] = '';
            }
            if ($request->hasFile($field)) {
                $update[$field] = $this->storeImage($request->file($field), (int) $client->id, $stem);
            }
        }

        ClientBrand::save($client, $update);

        return redirect()->route('client.branding.edit')->with('success', __('portal.brand.saved'));
    }

    public function verifyDomain()
    {
        $client = $this->currentClient();
        abort_unless($client, 404);

        $brand = ClientBrand::get($client);
        if ($brand['domain'] === '') {
            return back()->with('error', __('portal.brand.no_domain'));
        }

        $target = strtolower(rtrim(ClientBrand::cnameTarget(), '.'));
        $found = false;

        try {
            foreach ((array) @dns_get_record($brand['domain'], DNS_CNAME) as $record) {
                if (strtolower(rtrim((string) ($record['target'] ?? ''), '.')) === $target) {
                    $found = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            $found = false;
        }

        if (! $found) {
            return back()->with('error', __('portal.brand.cname_missing', ['host' => $brand['domain'], 'target' => $target]));
        }

        ClientBrand::save($client, ['domain_verified_at' => now()->toDateTimeString()]);

        return back()->with('success', __('portal.brand.cname_ok'));
    }

    /** Saves an uploaded image under public/uploads/brand/<client> and returns its web path. */
    private function storeImage($file, int $clientId, string $stem): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
        $ext = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'ico'], true) ? $ext : 'png';

        $dir = public_path('uploads/brand/'.$clientId);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $name = $stem.'-'.substr(sha1_file($file->getRealPath()), 0, 10).'.'.$ext;
        $file->move($dir, $name);

        return '/uploads/brand/'.$clientId.'/'.$name;
    }
}
