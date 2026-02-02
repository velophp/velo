<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\OtpType;
use App\Domain\Auth\Models\AuthOtp;
use App\Domain\Auth\Models\AuthSession;
use App\Domain\Auth\Models\Mail\LoginAlert;
use App\Domain\Auth\Models\Mail\Otp;
use App\Domain\Collection\Enums\CollectionType;
use App\Domain\Collection\Models\Collection;
use App\Domain\Field\Enums\FieldType;
use App\Domain\Field\Models\CollectionField;
use App\Domain\Project\Models\Project;
use App\Domain\Record\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
            'name'       => 'users',
            'type'       => CollectionType::Auth,
            'api_rules'  => [
                'authenticate' => '',
                'list'         => '',
                'view'         => '@request.auth.id = id',
                'update'       => '@request.auth.id = id',
                'delete'       => '@request.auth.id = id',
            ],
            'options' => array_merge(Collection::getDefaultAuthOptions(), [
                'auth_methods' => [
                    'standard' => [
                        'enabled' => true,
                        'fields'  => ['email'],
                    ],
                ],
            ]),
        ]);

        // Add fields
        CollectionField::create(['collection_id' => $this->collection->id, 'name' => 'email', 'type' => FieldType::Email, 'options' => []]);
        CollectionField::create(['collection_id' => $this->collection->id, 'name' => 'password', 'type' => FieldType::Text, 'options' => []]);
        CollectionField::create(['collection_id' => $this->collection->id, 'name' => 'verified', 'type' => FieldType::Bool, 'options' => []]);
    }

    public function test_can_authenticate_with_password()
    {
        Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'test@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        $response = $this->postJson('/api/collections/users/auth/authenticate-with-password', [
            'identifier' => 'test@example.com',
            'password'   => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_get_me()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'test@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        [$token, $hashed] = AuthSession::generateToken();
        AuthSession::create([
            'project_id'    => $this->project->id,
            'collection_id' => $this->collection->id,
            'record_id'     => $record->id,
            'token_hash'    => $hashed,
            'expires_at'    => now()->addHours(24),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/collections/users/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'test@example.com');
    }

    public function test_can_logout()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'test@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        [$token, $hashed] = AuthSession::generateToken();
        AuthSession::create([
            'project_id'    => $this->project->id,
            'collection_id' => $this->collection->id,
            'record_id'     => $record->id,
            'token_hash'    => $hashed,
            'expires_at'    => now()->addHours(24),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/collections/users/auth/logout');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('auth_sessions', ['token_hash' => $hashed]);
    }

    public function test_can_logout_all()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'test@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        [$token1, $hashed1] = AuthSession::generateToken();
        AuthSession::create([
            'project_id'    => $this->project->id,
            'collection_id' => $this->collection->id,
            'record_id'     => $record->id,
            'token_hash'    => $hashed1,
            'expires_at'    => now()->addHours(24),
        ]);

        [$token2, $hashed2] = AuthSession::generateToken();
        AuthSession::create([
            'project_id'    => $this->project->id,
            'collection_id' => $this->collection->id,
            'record_id'     => $record->id,
            'token_hash'    => $hashed2,
            'expires_at'    => now()->addHours(24),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token1")
            ->postJson('/api/collections/users/auth/logout-all');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('auth_sessions', ['record_id' => $record->id]);
    }

    public function test_authenticate_rule_verified_only()
    {
        // Set authenticate rule to verified = true
        $this->collection->update([
            'api_rules' => array_merge($this->collection->api_rules, ['authenticate' => 'verified = true']),
        ]);

        // Non-verified user
        Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'unverified@example.com',
                'password' => 'password123',
                'verified' => false,
            ],
        ]);

        $response = $this->postJson('/api/collections/users/auth/authenticate-with-password', [
            'identifier' => 'unverified@example.com',
            'password'   => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);

        // Verified user
        Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'verified@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        $response = $this->postJson('/api/collections/users/auth/authenticate-with-password', [
            'identifier' => 'verified@example.com',
            'password'   => 'password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_can_request_password_reset()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'test@example.com',
                'password' => Hash::make('password123'),
                'verified' => true,
            ],
        ]);

        Mail::fake();

        $response = $this->postJson('/api/collections/users/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('auth_otps', [
            'record_id' => $record->id,
            'action'    => OtpType::PASSWORD_RESET,
        ]);
    }

    public function test_can_confirm_password_reset()
    {
        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'test@example.com',
                'password' => Hash::make('password123'),
                'verified' => true,
            ],
        ]);
        Mail::fake();

        // Let's use the endpoint to generate the token first to keep it simple and test integration
        $response = $this->postJson('/api/collections/users/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $otp = null;
        Mail::assertQueued(Otp::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return $mail->hasTo('test@example.com');
        });

        $this->assertNotNull($otp);

        $response = $this->postJson('/api/collections/users/auth/confirm-forgot-password', [
            'otp'                       => $otp,
            'new_password'              => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
            'invalidate_sessions'       => true,
        ]);

        $response->assertStatus(200);

        // Verify password changed
        $record->refresh();
        $this->assertTrue(Hash::check('newpassword123', $record->data->get('password')));

        // Verify token marked used
        $reset = AuthOtp::where('record_id', $record->id)->where('action', OtpType::PASSWORD_RESET)->first();
        $this->assertNotNull($reset->used_at);
    }

    public function test_sends_login_alert_email()
    {
        $this->collection->update([
            'options' => array_merge($this->collection->options->toArray(), [
                'mail_templates' => array_merge($this->collection->options['mail_templates'] ?? [], [
                    'login_alert' => [
                        'subject' => 'Login Alert',
                        'body'    => 'Login from {{ip_address}}',
                    ],
                ]),
            ]),
        ]);

        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'test@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        Mail::fake();

        // First login (New IP)
        $response = $this->postJson('/api/collections/users/auth/authenticate-with-password', [
            'identifier'  => 'test@example.com',
            'password'    => 'password123',
            'device_name' => 'Device 1',
        ]);

        $response->assertStatus(200);

        Mail::assertQueued(LoginAlert::class, function ($mail) use ($record) {
            return $mail->hasTo('test@example.com') &&
                   $mail->record->id === $record->id;
        });

        // Second login (Same IP) - Should NOT send email
        Mail::fake(); // Reset fake

        $response = $this->postJson('/api/collections/users/auth/authenticate-with-password', [
            'identifier'  => 'test@example.com',
            'password'    => 'password123',
            'device_name' => 'Device 1',
        ]);

        $response->assertStatus(200);

        Mail::assertNothingQueued();

        // Third login (Different IP) - Should send email
        Mail::fake();

        $this->serverVariables = ['REMOTE_ADDR' => '1.2.3.4'];

        // Note: In tests, changing IP directly might be tricky depending on how the request is constructed.
        // We can pass server vars to postJson usually or simulate it.
        // But base TestCase postJson wrapper might not support it directly without `withServerVariables` or similar.
        // Let's rely on standard `call` underlying mechanism if possible, or just mock `request->ip()`.
        // Simplest way in standard Laravel test is using `server` parameter in parameters or `withServerVariables`.
        // Let's try passing it in server array.

        $response = $this->call('POST', '/api/collections/users/auth/authenticate-with-password', [
            'identifier'  => 'test@example.com',
            'password'    => 'password123',
            'device_name' => 'Device 2',
        ], [], [], ['REMOTE_ADDR' => '10.0.0.2']);

        $response->assertStatus(200);

        Mail::assertQueued(LoginAlert::class);
    }

    public function test_can_authenticate_with_otp()
    {
        // Enable OTP
        $this->collection->update([
            'options' => array_merge($this->collection->options->toArray(), [
                'auth_methods' => array_merge($this->collection->options->toArray()['auth_methods'], [
                    'otp' => [
                        'enabled' => true,
                        'config'  => [
                            'duration_s'               => 180,
                            'generate_password_length' => 6,
                        ],
                    ],
                ]),
            ]),
        ]);

        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'otpuser@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        Mail::fake();

        // 1. Request OTP
        $response = $this->postJson('/api/collections/users/auth/request-auth-otp', [
            'email' => 'otpuser@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        // Get OTP from mail
        $otp = '';
        Mail::assertQueued(Otp::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return $mail->hasTo('otpuser@example.com');
        });

        $this->assertNotNull($otp);

        // 2. Authenticate with OTP
        $response = $this->postJson('/api/collections/users/auth/authenticate-with-otp', [
            'email'       => 'otpuser@example.com',
            'otp'         => $otp,
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_request_email_update()
    {
        // Enable OTP
        $this->collection->update([
            'options' => array_merge($this->collection->options->toArray(), [
                'auth_methods' => array_merge($this->collection->options->toArray()['auth_methods'], [
                    'otp' => [
                        'enabled' => true,
                        'config'  => [
                            'duration_s'               => 180,
                            'generate_password_length' => 6,
                        ],
                    ],
                ]),
            ]),
        ]);

        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'old@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        Mail::fake();

        $response = $this->postJson('/api/collections/users/auth/request-update-email', [
            'email' => 'old@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('auth_otps', [
            'record_id' => $record->id,
            'action'    => OtpType::EMAIL_CHANGE,
        ]);

        Mail::assertQueued(Otp::class, function ($mail) {
            return $mail->hasTo('old@example.com');
        });
    }

    public function test_can_confirm_email_update()
    {
        // Enable OTP
        $this->collection->update([
            'options' => array_merge($this->collection->options->toArray(), [
                'auth_methods' => array_merge($this->collection->options->toArray()['auth_methods'], [
                    'otp' => [
                        'enabled' => true,
                        'config'  => [
                            'duration_s'               => 180,
                            'generate_password_length' => 6,
                        ],
                    ],
                ]),
            ]),
        ]);

        $record = Record::create([
            'collection_id' => $this->collection->id,
            'data'          => [
                'email'    => 'old@example.com',
                'password' => 'password123',
                'verified' => true,
            ],
        ]);

        Mail::fake();

        // Generate OTP
        $this->postJson('/api/collections/users/auth/request-update-email', [
            'email' => 'old@example.com',
        ]);

        $otp = '';
        Mail::assertQueued(Otp::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $response = $this->postJson('/api/collections/users/auth/confirm-update-email', [
            'otp'                    => $otp,
            'new_email'              => 'new@example.com',
            'new_email_confirmation' => 'new@example.com',
        ]);

        $response->assertStatus(200);

        $record->refresh();
        $this->assertEquals('new@example.com', $record->data->get('email'));
    }
}
