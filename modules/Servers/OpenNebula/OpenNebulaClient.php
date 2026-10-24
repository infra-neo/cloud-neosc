<?php

namespace Modules\Servers\OpenNebula;

use App\Models\Server;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

/**
 * Talks to one OpenNebula front end over its XML-RPC API (oned, port 2633).
 *
 * Signs in with "user:password" or "user:login-token", which is what oned
 * expects as the first parameter of every call. Where the endpoint lives:
 *
 *   - a hostname that is a full URL ("https://one.example.com/RPC2") is used
 *     as it is, which is how a TLS proxy in front of oned is addressed;
 *   - anything else is http://<host>:<port>/RPC2, port 2633 unless the server
 *     record says otherwise.
 */
class OpenNebulaClient
{
    public const DEFAULT_PORT = 2633;

    private const TIMEOUT = 20;

    public function __construct(private readonly Server $server, private readonly string $host) {}

    public function server(): Server
    {
        return $this->server;
    }

    public function endpoint(): string
    {
        $raw = trim((string) $this->server->hostname);
        if (preg_match('#^https?://#i', $raw)) {
            return rtrim($raw, '/').(str_ends_with(rtrim($raw, '/'), '/RPC2') ? '' : '/RPC2');
        }

        $host = str_contains($this->host, ':') && ! str_starts_with($this->host, '[') ? "[{$this->host}]" : $this->host;

        return "http://{$host}:".($this->server->port ?: self::DEFAULT_PORT).'/RPC2';
    }

    /** "user:secret", the secret being the password or, when there is one, the login token. */
    public function session(): string
    {
        $secret = trim((string) $this->server->access_hash) !== ''
            ? trim((string) $this->server->access_hash)
            : (string) $this->server->password;

        return trim((string) $this->server->username).':'.$secret;
    }

    /** The user the calls are made as, for messages. */
    public function actor(): string
    {
        return trim((string) $this->server->username);
    }

    /**
     * Call a method; the session parameter is added in front of $params.
     */
    public function call(string $method, array $params = []): OpenNebulaResult
    {
        $body = $this->encodeCall($method, array_merge([$this->session()], $params));

        try {
            $http = Http::withBody($body, 'text/xml')->timeout(self::TIMEOUT)->connectTimeout(8);
            if (! $this->server->setting('verify_tls', true)) {
                $http = $http->withoutVerifying();
            }
            $response = $http->post($this->endpoint());
        } catch (ConnectionException $e) {
            return OpenNebulaResult::failed(__('opennebula.error.unreachable', ['host' => $this->host]));
        }

        if (! $response->successful()) {
            return OpenNebulaResult::failed(__('opennebula.error.http', ['status' => $response->status()]), $response->status());
        }

        return $this->decodeResponse((string) $response->body());
    }

    /** The reply of a call that returns XML, parsed; null when it is not XML. */
    public static function xml(mixed $data): ?SimpleXMLElement
    {
        if (! is_string($data) || trim($data) === '') {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($data, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml === false ? null : $xml;
    }

    // ---------------------------------------------------------------------
    // XML-RPC
    // ---------------------------------------------------------------------

    private function encodeCall(string $method, array $params): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>'
            .htmlspecialchars($method, ENT_XML1, 'UTF-8').'</methodName><params>';
        foreach ($params as $p) {
            $out .= '<param>'.$this->encodeValue($p).'</param>';
        }

        return $out.'</params></methodCall>';
    }

    private function encodeValue(mixed $v): string
    {
        return match (true) {
            is_bool($v) => '<value><boolean>'.($v ? '1' : '0').'</boolean></value>',
            is_int($v) => '<value><int>'.$v.'</int></value>',
            default => '<value><string>'.htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</string></value>',
        };
    }

    private function decodeResponse(string $body): OpenNebulaResult
    {
        $xml = self::xml($body);
        if (! $xml) {
            return OpenNebulaResult::failed(__('opennebula.error.not_xmlrpc'));
        }
        if (isset($xml->fault)) {
            $fault = $this->decodeValue($xml->fault->value);

            return OpenNebulaResult::failed(is_array($fault) ? (string) ($fault['faultString'] ?? 'fault') : 'fault');
        }
        if (! isset($xml->params->param->value->array->data->value)) {
            return OpenNebulaResult::failed(__('opennebula.error.not_xmlrpc'));
        }

        $values = [];
        foreach ($xml->params->param->value->array->data->value as $v) {
            $values[] = $this->decodeValue($v);
        }

        $ok = (bool) ($values[0] ?? false);
        $data = $values[1] ?? null;
        $code = (int) ($values[2] ?? 0);

        return $ok
            ? new OpenNebulaResult(true, 0, $data)
            : new OpenNebulaResult(false, $code, null, is_string($data) ? trim($data) : __('opennebula.error.refused'));
    }

    private function decodeValue(SimpleXMLElement $v): mixed
    {
        $child = $v->children();
        if (count($child) === 0) {
            return (string) $v;
        }
        $node = $child[0];

        return match ($node->getName()) {
            'boolean' => (string) $node === '1',
            'int', 'i4', 'i8' => (int) $node,
            'double' => (float) $node,
            'array' => array_map(fn ($x) => $this->decodeValue($x), iterator_to_array($node->data->value, false)),
            'struct' => collect($node->member)->mapWithKeys(fn ($m) => [(string) $m->name => $this->decodeValue($m->value)])->all(),
            default => (string) $node,
        };
    }
}
