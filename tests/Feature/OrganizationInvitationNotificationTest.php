<?php

namespace Tests\Feature;

use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class OrganizationInvitationNotificationTest extends TestCase
{
    public function test_email_contains_a_frontend_acceptance_link_and_plain_text_fallback(): void
    {
        config(['app.frontend_url' => 'https://inventory.example.test/']);
        $token = 'token+with&reserved/characters';
        $notification = new OrganizationInvitationNotification(
            organizationName: 'Armazém Central',
            token: $token,
            acceptanceEndpoint: 'https://api.example.test/api/invitations/accept',
        );
        $mail = $notification->toMail(new AnonymousNotifiable);
        $acceptanceUrl = 'https://inventory.example.test/accept-invitation?token=token%2Bwith%26reserved%2Fcharacters';

        $this->assertSame('Aceitar convite', $mail->actionText);
        $this->assertSame($acceptanceUrl, $mail->actionUrl);
        $this->assertContains("Token de aceite: {$token}", $mail->introLines);
        $this->assertContains("Se o botão não funcionar, abra este link no navegador: {$acceptanceUrl}", $mail->outroLines);
        $this->assertContains(
            'Envie o token, seu nome e uma senha para POST https://api.example.test/api/invitations/accept.',
            $mail->introLines,
        );
    }
}
