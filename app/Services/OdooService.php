<?php

namespace App\Services;

use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;
use PhpXmlRpc\Encoder;
use Exception;

class OdooService
{
    protected string $url;
    protected string $db;
    protected string $username;
    protected string $password;
    protected ?int $uid = null;
    protected Encoder $encoder;

    public function __construct()
    {
        $this->url = rtrim(config('services.odoo.host'), '/');
        $this->db = config('services.odoo.database');
        $this->username = config('services.odoo.username');
        $this->password = config('services.odoo.password');
        $this->encoder = new Encoder();
    }

    protected function call(string $endpoint, string $method, array $params)
    {
        $client = new Client($this->url . $endpoint);
        $request = new Request($method, $params);
        $response = $client->send($request);

        if ($response->faultCode()) {
            throw new Exception($response->faultString());
        }

        return $response->value();
    }

    public function authenticate()
    {
        $result = $this->call('/xmlrpc/2/common', 'authenticate', [
            new Value($this->db, 'string'),
            new Value($this->username, 'string'),
            new Value($this->password, 'string'),
            $this->encoder->encode([]), // dùng encoder thay vì new Value([], 'array')
        ]);

        $this->uid = (int) $result->scalarval();

        if (!$this->uid) {
            throw new Exception('Authentication failed.');
        }
    }

    public function searchRead(string $model, array $domain = [], array $fields = [], int $limit = 0)
    {
        if (!$this->uid) $this->authenticate();

        $kwargs = [
            'fields' => $fields,
        ];
        if ($limit > 0) $kwargs['limit'] = $limit;

        $params = [
            new Value($this->db, 'string'),
            new Value($this->uid, 'int'),
            new Value($this->password, 'string'),
            new Value($model, 'string'),
            new Value('search_read', 'string'),
            $this->encoder->encode([$domain]), // domain
            $this->encoder->encode($kwargs),   // kwargs
        ];

        $result = $this->call('/xmlrpc/2/object', 'execute_kw', $params);
        return $this->encoder->decode($result);
    }

    public function create(string $model, array $data, array $context = [])
    {
        if (!$this->uid) $this->authenticate();

        $params = [
            new Value($this->db, 'string'),
            new Value($this->uid, 'int'),
            new Value($this->password, 'string'),
            new Value($model, 'string'),
            new Value('create', 'string'),
            $this->encoder->encode([$data]),
            ['context' => $context],
        ];

        $result = $this->call('/xmlrpc/2/object', 'execute_kw', $params);
        return $this->encoder->decode($result);
    }

    public function write(string $model, array $ids, array $data): bool
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $params = [
            new Value($this->db, 'string'),
            new Value($this->uid, 'int'),
            new Value($this->password, 'string'),
            new Value($model, 'string'),
            new Value('write', 'string'),
            $this->encoder->encode([$ids, $data]),
        ];

        $result = $this->call('/xmlrpc/2/object', 'execute_kw', $params);

        return (bool) $this->encoder->decode($result);
    }
}
