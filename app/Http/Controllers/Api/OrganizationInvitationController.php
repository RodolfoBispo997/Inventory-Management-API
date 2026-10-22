<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptOrganizationInvitationRequest;
use App\Http\Requests\StoreOrganizationInvitationRequest;
use App\Http\Resources\OrganizationInvitationResource;
use App\Http\Resources\UserResource;
use App\Services\OrganizationInvitationService;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationInvitationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrganizationInvitationResource::collection(
            $request->user()->organization->invitations()
                ->with('invitedBy:id,name')
                ->orderByDesc('id')
                ->paginate(15)
        );
    }

    public function store(
        StoreOrganizationInvitationRequest $request,
        OrganizationInvitationService $service,
    ): JsonResponse {
        $invitation = $service->create(
            $request->user(),
            $request->validated('email'),
            route('api.invitations.accept'),
        );

        return (new OrganizationInvitationResource($invitation->load('invitedBy:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    #[PathParameter('invitation', type: 'int')]
    #[Response(status: 404, description: 'Invitation not found in the authenticated organization.')]
    public function show(Request $request, string $invitation): OrganizationInvitationResource
    {
        return new OrganizationInvitationResource(
            $request->user()->organization->invitations()
                ->with('invitedBy:id,name')
                ->findOrFail($invitation)
        );
    }

    public function accept(
        AcceptOrganizationInvitationRequest $request,
        OrganizationInvitationService $service,
    ): JsonResponse {
        $user = $service->accept(
            $request->validated('token'),
            $request->validated('name'),
            $request->validated('password'),
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
