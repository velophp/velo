<?php

namespace Tests\Feature;

use App\Enums\CollectionType;
use App\Enums\FieldType;
use App\Models\AuthSession;
use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Project;
use App\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected $project;

    protected $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create(['name' => 'Test Project']);

        // Create users collection
        $this->collection = Collection::create([
            'project_id' => $this->project->id,
            'name' => 'users',
            'type' => CollectionType::Auth,
            'api_rules' => [
                'authenticate' => '',
                'list' => '',
                'view' => '@request.auth.id = id',
                'update' => '@request.auth.id = id',
                'delete' => '@request.auth.id = id',
            ],
            'options' => [
                'auth_methods' => [
                    'standard' => [
                        'enabled' => true,
                        'fields' => ['email'],
                    ],
                ],
            ],
        ]);

        // Add fields
        CollectionField::create(['collection_id' => $this->collection->id, 'name' => 'email', 'type' => FieldType::Email, 'options' => []]);
        CollectionField::create(['collection_id' => $this->collection->id, 'name' => 'password', 'type' => FieldType::Text, 'options' => []]);
        CollectionField::create(['collection_id' => $this->collection->id, 'name' => 'verified', 'type' => FieldType::Bool, 'options' => []]);
    }

    public function testCanAuthenticateWithPassword()
    {
        Record::create([
            'collection_id' => $this->collection->id,
            'data' => [
                'email' => 'test@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        $response = $this->postJson('/api/collections/users/auth/with-password', [
            'identifier' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'data']);
    }

    public function testCanGetMe()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data' => [
                'email' => 'test@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        [$token, $hashed] = AuthSession::generateToken();
        AuthSession::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->collection->id,
            'record_id' => $record->id,
            'token_hash' => $hashed,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/collections/users/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'test@example.com');
    }

    public function testCanLogout()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data' => [
                'email' => 'test@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        [$token, $hashed] = AuthSession::generateToken();
        AuthSession::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->collection->id,
            'record_id' => $record->id,
            'token_hash' => $hashed,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/collections/users/auth/logout');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('auth_sessions', ['token_hash' => $hashed]);
    }

    public function testCanLogoutAll()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data' => [
                'email' => 'test@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        [$token1, $hashed1] = AuthSession::generateToken();
        AuthSession::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->collection->id,
            'record_id' => $record->id,
            'token_hash' => $hashed1,
            'expires_at' => now()->addHours(24),
        ]);

        [$token2, $hashed2] = AuthSession::generateToken();
        AuthSession::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->collection->id,
            'record_id' => $record->id,
            'token_hash' => $hashed2,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token1")
            ->postJson('/api/collections/users/auth/logout-all');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('auth_sessions', ['record_id' => $record->id]);
    }

    public function testAuthenticateRuleVerifiedOnly()
    {
        // Set authenticate rule to verified = true
        $this->collection->update([
            'api_rules' => array_merge($this->collection->api_rules, ['authenticate' => 'verified = true']),
        ]);

        // Non-verified user
        Record::create([
            'collection_id' => $this->collection->id,
            'data' => [
                'email' => 'unverified@example.com',
                'password' => 'password123',
                'verified' => false,
            ],
        ]);

        $response = $this->postJson('/api/collections/users/auth/with-password', [
            'identifier' => 'unverified@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);

        // Verified user
        Record::create([
            'collection_id' => $this->collection->id,
            'data' => [
                'email' => 'verified@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        $response = $this->postJson('/api/collections/users/auth/with-password', [
            'identifier' => 'verified@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }
}
