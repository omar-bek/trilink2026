<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ScimUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Phase 7 — SCIM 2.0 user provisioning endpoint. Lets enterprise IdPs
 * (Okta, Azure AD, OneLogin, JumpCloud) push and pull user records into
 * TriLink without manual administration.
 *
 * Implements the minimum surface area required by Okta + Azure AD:
 *   POST   /scim/v2/Users         — create user
 *   GET    /scim/v2/Users/{id}    — fetch user by SCIM external id
 *   GET    /scim/v2/Users         — list users (filter=userName eq "...")
 *   PATCH  /scim/v2/Users/{id}    — update single attribute
 *   PUT    /scim/v2/Users/{id}    — replace entire user
 *   DELETE /scim/v2/Users/{id}    — soft-deactivate
 *
 * Authentication is the same Sanctum bearer token used by the public
 * API; the token must hold the `scim` ability. Tenancy is the company
 * the token's user belongs to.
 */
class ScimController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $filter    = (string) $request->query('filter', '');

        $query = User::query()->where('company_id', $companyId);

        // Minimal SCIM filter: `userName eq "..."`. The full filter
        // grammar is huge; this is the only one Okta + Azure use for
        // de-duping during provisioning.
        if (preg_match('/userName eq "(.+)"/', $filter, $m)) {
            $query->where('email', $m[1]);
        }

        $users = $query->orderBy('id')->limit(100)->get();

        return response()->json([
            'schemas'      => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => $users->count(),
            'startIndex'   => 1,
            'itemsPerPage' => $users->count(),
            'Resources'    => $users->map(fn ($u) => $this->resource($u))->all(),
        ]);
    }

    public function show(Request $request, string $externalId): JsonResponse
    {
        $user = $this->findByExternalId($request->user()->company_id, $externalId);
        if (!$user) {
            return $this->scimError(404, 'User not found');
        }
        return response()->json($this->resource($user));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $email   = $payload['userName'] ?? ($payload['emails'][0]['value'] ?? null);
        $given   = $payload['name']['givenName']  ?? '';
        $family  = $payload['name']['familyName'] ?? '';
        $active  = (bool) ($payload['active'] ?? true);

        if (!$email) {
            return $this->scimError(400, 'userName is required');
        }

        return DB::transaction(function () use ($request, $email, $given, $family, $active, $payload) {
            $companyId = $request->user()->company_id;

            // Idempotent on email — if the user already exists in this
            // tenant, we link the SCIM record instead of creating a dupe.
            // Note: the users table uses a `status` enum (ACTIVE/INACTIVE/
            // PENDING) rather than a boolean is_active column — the SCIM
            // payload's `active` flag maps to ACTIVE/INACTIVE here.
            $user = User::firstOrCreate(
                ['email' => $email, 'company_id' => $companyId],
                [
                    'first_name' => $given,
                    'last_name'  => $family,
                    'password'   => Hash::make(Str::random(40)),
                    'status'     => $active ? UserStatus::ACTIVE->value : UserStatus::INACTIVE->value,
                    'role'       => 'buyer',
                ],
            );

            // Persist the SCIM shadow row so subsequent PATCH/DELETE
            // requests can be routed back to this user. updateOrCreate
            // is idempotent on the external_id unique key — re-running
            // the same provisioning request never creates a duplicate.
            $externalId = (string) ($payload['externalId'] ?? Str::uuid());
            ScimUser::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'user_id'      => $user->id,
                    'is_active'    => $active,
                    'scim_payload' => $payload,
                ],
            );

            return response()->json($this->resource($user, $externalId), 201);
        });
    }

    public function update(Request $request, string $externalId): JsonResponse
    {
        $user = $this->findByExternalId($request->user()->company_id, $externalId);
        if (!$user) {
            return $this->scimError(404, 'User not found');
        }

        $payload = $request->json()->all();

        // PATCH ops follow the SCIM 2.0 spec. We support replace-only
        // ops on the few attributes most IdPs actually push.
        if (isset($payload['Operations'])) {
            foreach ($payload['Operations'] as $op) {
                if (($op['op'] ?? '') !== 'replace') {
                    continue;
                }
                $path  = $op['path']  ?? '';
                $value = $op['value'] ?? null;

                match ($path) {
                    'active'        => $user->update(['status' => ((bool) $value) ? UserStatus::ACTIVE->value : UserStatus::INACTIVE->value]),
                    'name.givenName'=> $user->update(['first_name' => $value]),
                    'name.familyName'=> $user->update(['last_name' => $value]),
                    default         => null,
                };
            }
        } else {
            // PUT — replace whole user.
            $currentlyActive = ($user->status instanceof \BackedEnum ? $user->status->value : (string) $user->status) === UserStatus::ACTIVE->value;
            $newActive = (bool) ($payload['active'] ?? $currentlyActive);
            $user->update([
                'first_name' => $payload['name']['givenName']  ?? $user->first_name,
                'last_name'  => $payload['name']['familyName'] ?? $user->last_name,
                'status'     => $newActive ? UserStatus::ACTIVE->value : UserStatus::INACTIVE->value,
            ]);
        }

        return response()->json($this->resource($user->fresh(), $externalId));
    }

    public function destroy(Request $request, string $externalId): JsonResponse
    {
        $user = $this->findByExternalId($request->user()->company_id, $externalId);
        if (!$user) {
            return $this->scimError(404, 'User not found');
        }

        // SCIM treats DELETE as deactivation rather than a hard delete —
        // matches our soft-delete model so user history stays intact.
        $user->update(['status' => UserStatus::INACTIVE->value]);
        ScimUser::where('external_id', $externalId)->update(['is_active' => false]);

        return response()->json(null, 204);
    }

    private function findByExternalId(int $companyId, string $externalId): ?User
    {
        $shadow = ScimUser::where('external_id', $externalId)->first();
        if (!$shadow) {
            return null;
        }
        return User::where('id', $shadow->user_id)->where('company_id', $companyId)->first();
    }

    /**
     * Build a SCIM 2.0 User resource representation. Returned by show(),
     * store(), and update() so the IdP gets a consistent shape.
     */
    private function resource(User $user, ?string $externalId = null): array
    {
        $externalId = $externalId ?? (string) ScimUser::where('user_id', $user->id)->value('external_id');
        $statusValue = $user->status instanceof \BackedEnum ? $user->status->value : (string) $user->status;

        return [
            'schemas'  => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'id'       => $externalId ?: (string) $user->id,
            'externalId' => $externalId,
            'userName' => $user->email,
            'name'     => [
                'givenName'  => $user->first_name,
                'familyName' => $user->last_name,
            ],
            'emails'   => [
                ['value' => $user->email, 'type' => 'work', 'primary' => true],
            ],
            'active'   => $statusValue === UserStatus::ACTIVE->value,
            'meta'     => [
                'resourceType' => 'User',
                'created'      => $user->created_at?->toIso8601String(),
                'lastModified' => $user->updated_at?->toIso8601String(),
            ],
        ];
    }

    private function scimError(int $status, string $detail): JsonResponse
    {
        return response()->json([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'status'  => (string) $status,
            'detail'  => $detail,
        ], $status);
    }
}
