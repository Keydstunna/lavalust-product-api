<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->call->library('api');
    }

    private function find($id)
    {
        return $this->db->raw(
            "SELECT * FROM products WHERE id = ? LIMIT 1",
            [(int) $id]
        )->fetch(PDO::FETCH_ASSOC);
    }

    private function format($p)
    {
        $p['id']       = (int) $p['id'];
        $p['price']    = (float) $p['price'];
        $p['quantity'] = (int) $p['quantity'];
        return $p;
    }

    private function text($value)
    {
        return htmlspecialchars_decode((string) $value, ENT_QUOTES);
    }

    private function check($d)
    {
        if ($d['product_name'] === '' || strlen($d['product_name']) > 100) {
            $this->api->respond_error('product_name is required (max 100 characters)', 422);
        }
        if (!is_numeric($d['price']) || $d['price'] < 0) {
            $this->api->respond_error('price must be a number, zero or higher', 422);
        }
        if (filter_var($d['quantity'], FILTER_VALIDATE_INT) === false || $d['quantity'] < 0) {
            $this->api->respond_error('quantity must be a whole number, zero or higher', 422);
        }
    }

    public function index()
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $rows = $this->db->raw("SELECT * FROM products ORDER BY id DESC", [])
                         ->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond(['data' => array_map([$this, 'format'], $rows)]);
    }

    public function show($id)
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $product = $this->find($id);
        if (!$product) {
            $this->api->respond_error('Product not found', 404);
        }
        $this->api->respond(['data' => $this->format($product)]);
    }

    public function store()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();
        $in = $this->api->body();

        $data = [
            'product_name' => $this->text($in['product_name'] ?? ''),
            'description'  => $this->text($in['description'] ?? ''),
            'price'        => $in['price'] ?? '',
            'quantity'     => $in['quantity'] ?? '',
        ];
        $this->check($data);

        $this->db->raw(
            "INSERT INTO products (product_name, description, price, quantity) VALUES (?, ?, ?, ?)",
            [$data['product_name'], $data['description'], $data['price'], $data['quantity']]
        );

        $row = $this->db->raw("SELECT LAST_INSERT_ID() AS id", [])->fetch(PDO::FETCH_ASSOC);

        $this->api->respond([
            'message' => 'Product created',
            'data'    => $this->format($this->find($row['id'])),
        ], 201);
    }

    public function update($id)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'])) {
            $this->api->respond_error('Method Not Allowed', 405);
        }
        $this->api->require_jwt();

        $product = $this->find($id);
        if (!$product) {
            $this->api->respond_error('Product not found', 404);
        }

        $in = $this->api->body();
        $data = [
            'product_name' => array_key_exists('product_name', $in) ? $this->text($in['product_name']) : $product['product_name'],
            'description'  => array_key_exists('description', $in) ? $this->text($in['description']) : $product['description'],
            'price'        => array_key_exists('price', $in) ? $in['price'] : $product['price'],
            'quantity'     => array_key_exists('quantity', $in) ? $in['quantity'] : $product['quantity'],
        ];
        $this->check($data);

        $this->db->raw(
            "UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?",
            [$data['product_name'], $data['description'], $data['price'], $data['quantity'], (int) $id]
        );

        $this->api->respond([
            'message' => 'Product updated',
            'data'    => $this->format($this->find($id)),
        ]);
    }

    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->api->require_jwt();

        if (!$this->find($id)) {
            $this->api->respond_error('Product not found', 404);
        }

        $this->db->raw("DELETE FROM products WHERE id = ?", [(int) $id]);
        $this->api->respond(['message' => 'Product deleted']);
    }
}