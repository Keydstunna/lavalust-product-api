<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->call->library('api');
    }

    public function register()
    {
        $this->api->require_method('POST');
        $in = $this->api->body();

        $username = $in['username'] ?? '';
        $email    = $in['email'] ?? '';
        $password = $in['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            $this->api->respond_error('username, email and password are required', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api->respond_error('Invalid email address', 422);
        }
        if (strlen($password) <= 7) {
            $this->api->respond_error('Password must be at least 8 characters', 422);
        }

        $exists = $this->db->raw(
            "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$username, $email]
        )->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            $this->api->respond_error('Username or email already taken', 409);
        }

        $this->db->raw(
            "INSERT INTO users (username, email, password) VALUES (?, ?, ?)",
            [$username, $email, password_hash($password, PASSWORD_DEFAULT)]
        );

        $this->api->respond(['message' => 'Registered successfully'], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');
        $in = $this->api->body();

        $login    = $in['username'] ?? ($in['email'] ?? '');
        $password = $in['password'] ?? '';

        if ($login === '' || $password === '') {
            $this->api->respond_error('username and password are required', 422);
        }

        $user = $this->db->raw(
            "SELECT id, username, email, password, role, is_active
             FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$login, $login]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid credentials', 401);
        }
        if (!$user['is_active']) {
            $this->api->respond_error('Account is disabled', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id'   => $user['id'],
            'role' => $user['role'],
        ]);

        $this->api->respond(array_merge($tokens, [
            'user' => [
                'id'       => (int) $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ],
        ]));
    }

    public function refresh()
    {
        $this->api->require_method('POST');
        $in = $this->api->body();
        $token = $in['refresh_token'] ?? '';

        if ($token === '') {
            $this->api->respond_error('refresh_token is required', 422);
        }
        $this->api->refresh_access_token($token);
    }

    public function logout()
    {
        $this->api->require_method('POST');
        $in = $this->api->body();
        $token = $in['refresh_token'] ?? '';

        if ($token !== '') {
            $this->api->revoke_refresh_token($token);
        }
        $this->api->respond(['message' => 'Logged out']);
    }

    public function me()
    {
        $this->api->require_method('GET');
        $auth = $this->api->require_jwt();

        $user = $this->db->raw(
            "SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1",
            [$auth['sub']]
        )->fetch(PDO::FETCH_ASSOC);

        $this->api->respond(['user' => $user]);
    }
}
