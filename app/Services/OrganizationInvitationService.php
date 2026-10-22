<?php

namespace App\Services;

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OrganizationInvitationService
{
    public function create(User $inviter, string $email, string $acceptanceEndpoint): OrganizationInvitation
    {
        $token = bin2hex(random_bytes(32));

        return DB::transaction(function () use ($inviter, $email, $acceptanceEndpoint, $token): OrganizationInvitation {
            $invitation = OrganizationInvitation::query()->create([
                'organization_id' => $inviter->organization_id,
                'invited_by_user_id' => $inviter->id,
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);

            Notification::route('mail', $email)->notify(new OrganizationInvitationNotification(
                organizationName: $inviter->organization->name,
                token: $token,
                acceptanceEndpoint: $acceptanceEndpoint,
            ));

            return $invitation;
        });
    }

    public function accept(string $token, string $name, string $password): User
    {
        return DB::transaction(function () use ($token, $name, $password): User {
            $invitation = OrganizationInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || $invitation->accepted_at !== null || $invitation->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => ['O convite é inválido, expirou ou já foi utilizado.'],
                ]);
            }

            if (User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'token' => ['O convite é inválido, expirou ou já foi utilizado.'],
                ]);
            }

            $user = $invitation->organization->users()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);
            $user->forceFill(['role' => User::ROLE_OPERATOR])->save();

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }
}
