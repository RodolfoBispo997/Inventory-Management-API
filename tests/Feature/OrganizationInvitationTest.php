<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_invitation_without_returning_the_token(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/organization-invitations', [
            'email' => 'operator@example.test',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'operator@example.test')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.token_hash');

        $invitation = OrganizationInvitation::query()->firstOrFail();
        $this->assertSame($organization->id, $invitation->organization_id);
        $this->assertSame($admin->id, $invitation->invited_by_user_id);
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertGreaterThan(now()->addDays(6)->timestamp, $invitation->expires_at->timestamp);
        $this->assertStringNotContainsString($invitation->token_hash, $response->getContent());
        $emailToken = null;

        Notification::assertSentOnDemand(OrganizationInvitationNotification::class, function (
            OrganizationInvitationNotification $notification,
            array $channels,
            object $notifiable,
        ) use ($invitation, &$emailToken): bool {
            $this->assertSame(['mail'], $channels);
            $this->assertSame($invitation->email, $notifiable->routes['mail']);
            $this->assertSame(hash('sha256', $notification->token), $invitation->token_hash);
            $emailToken = $notification->token;

            return true;
        });

        $this->assertIsString($emailToken);
        $this->assertStringNotContainsString($emailToken, $response->getContent());
    }

    public function test_admin_cannot_invite_registered_emails_or_assign_invitation_role_and_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create();
        $existingUser = User::factory()->for($otherOrganization)->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/organization-invitations', [
            'email' => 'new-operator@example.test',
            'role' => User::ROLE_ADMIN,
            'organization_id' => $otherOrganization->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'organization_id']);

        $this->postJson('/api/organization-invitations', [
            'email' => $existingUser->email,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('organization_invitations', 0);
    }

    public function test_operator_cannot_create_or_consult_invitations(): void
    {
        $organization = Organization::factory()->create();
        $operator = User::factory()->for($organization)->operator()->create();
        Sanctum::actingAs($operator);

        $this->postJson('/api/organization-invitations', ['email' => 'blocked@example.test'])
            ->assertForbidden();
        $this->getJson('/api/organization-invitations')->assertForbidden();
        $this->getJson('/api/organization-invitations/1')->assertForbidden();
    }

    public function test_an_admin_cannot_consult_another_organizations_invitation(): void
    {
        $inviter = User::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $otherInvitation = OrganizationInvitation::query()->create([
            'organization_id' => $otherOrganization->id,
            'invited_by_user_id' => User::factory()->for($otherOrganization)->create()->id,
            'email' => 'private@example.test',
            'token_hash' => hash('sha256', 'a-private-invitation-token'),
            'expires_at' => now()->addDays(7),
        ]);
        Sanctum::actingAs($inviter);

        $this->getJson('/api/organization-invitations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/organization-invitations/{$otherInvitation->id}")
            ->assertNotFound();
    }

    public function test_invitation_acceptance_rejects_invalid_expired_and_previously_used_tokens(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create();
        $expiredToken = bin2hex(random_bytes(32));
        $expiredInvitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $admin->id,
            'email' => 'expired@example.test',
            'token_hash' => hash('sha256', $expiredToken),
            'expires_at' => now()->subSecond(),
        ]);

        $this->postJson('/api/invitations/accept', [
            'token' => str_repeat('0', 64),
            'name' => 'Invalid Invite',
            'password' => 'a-secure-password',
        ])->assertUnprocessable();

        $this->postJson('/api/invitations/accept', [
            'token' => $expiredToken,
            'name' => 'Expired Invite',
            'password' => 'a-secure-password',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['email' => 'expired@example.test']);
        $this->assertNull($expiredInvitation->fresh()->accepted_at);

        $validToken = bin2hex(random_bytes(32));
        $usedInvitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $admin->id,
            'email' => 'used@example.test',
            'token_hash' => hash('sha256', $validToken),
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson('/api/invitations/accept', [
            'token' => $validToken,
            'name' => 'Accepted User',
            'password' => 'a-secure-password',
        ])->assertCreated();

        $this->postJson('/api/invitations/accept', [
            'token' => $validToken,
            'name' => 'Second User',
            'password' => 'a-secure-password',
        ])->assertUnprocessable();

        $this->assertNotNull($usedInvitation->fresh()->accepted_at);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_acceptance_creates_an_operator_in_the_invited_organization_and_rejects_client_assignments(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create();
        $token = bin2hex(random_bytes(32));
        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $admin->id,
            'email' => 'new-operator@example.test',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson('/api/invitations/accept', [
            'token' => $token,
            'name' => 'New Operator',
            'password' => 'a-secure-password',
            'role' => User::ROLE_ADMIN,
            'organization_id' => $otherOrganization->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'organization_id']);

        $this->assertDatabaseMissing('users', ['email' => 'new-operator@example.test']);
        $this->assertNull($invitation->fresh()->accepted_at);

        $this->postJson('/api/invitations/accept', [
            'token' => $token,
            'name' => 'New Operator',
            'password' => 'a-secure-password',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'new-operator@example.test');

        $operator = User::query()->where('email', 'new-operator@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_OPERATOR, $operator->role);
        $this->assertSame($organization->id, $operator->organization_id);
        $this->assertNotSame($otherOrganization->id, $operator->organization_id);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_failed_acceptance_does_not_create_a_user_or_mark_the_invitation_accepted(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create();
        $token = bin2hex(random_bytes(32));
        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $admin->id,
            'email' => 'already-registered@example.test',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);
        User::factory()->for($organization)->create(['email' => 'already-registered@example.test']);

        $this->postJson('/api/invitations/accept', [
            'token' => $token,
            'name' => 'Duplicate User',
            'password' => 'a-secure-password',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('users', 2);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_acceptance_rolls_back_user_creation_if_marking_invitation_used_fails(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create();
        $token = bin2hex(random_bytes(32));
        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $admin->id,
            'email' => 'atomic-operator@example.test',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);
        $eventName = 'eloquent.saving: '.OrganizationInvitation::class;

        OrganizationInvitation::saving(function (OrganizationInvitation $model): void {
            if ($model->accepted_at !== null) {
                throw new \RuntimeException('Simulated failure while accepting invitation.');
            }
        });

        try {
            $this->postJson('/api/invitations/accept', [
                'token' => $token,
                'name' => 'Atomic Operator',
                'password' => 'a-secure-password',
            ])->assertServerError();
        } finally {
            Event::forget($eventName);
        }

        $this->assertDatabaseMissing('users', ['email' => 'atomic-operator@example.test']);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_unauthenticated_users_cannot_consult_administrator_invitation_routes(): void
    {
        $this->getJson('/api/organization-invitations')->assertUnauthorized();
        $this->postJson('/api/organization-invitations', ['email' => 'anonymous@example.test'])
            ->assertUnauthorized();
    }
}
