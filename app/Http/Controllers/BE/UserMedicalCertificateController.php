<?php

namespace App\Http\Controllers\BE;

use App\Constants\StatusCodeConstants;
use App\Filters\UserFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserMedicalCertificateShowRequest;
use App\Http\Requests\UserMedicalCertificateStoreRequest;
use App\Http\Requests\UserMedicalCertificateUpdateRequest;
use App\Http\Requests\UserMedicalCertificateUpdateStatusRequest;
use App\Http\Requests\UserIndexRequest;
use App\Http\Resources\UserMedicalCertificateResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserMedicalCertificate;
use Illuminate\Support\Facades\DB;

class UserMedicalCertificateController extends Controller
{
    public function __construct(private UserFilter $user_filter)
    {
    }

    public function index(UserIndexRequest $request)
    {
        $user = User::with([
            'personal',
            'contact',
            'employment.office',
            'employment.department',
            'employment.position',
            'emergency',
            'certificates',
            'medicalCertificates',
            'roles.permissions',
            'roles' => function ($query) {
                $query->where('is_active', StatusCodeConstants::ACTIVE);  
            },
        ]);
        
        $user = $this->user_filter->apply($request, $request->size, $user);
        
        return self::responsePaginated(UserResource::collection($user), $user);
    }

    public function store(UserMedicalCertificateStoreRequest $request)
    {
        $user = User::findByUuid($request->user_uuid);

        DB::beginTransaction();

        try {
            $attachment_path = null;
            $attachment_name = null;

            if ($request->hasFile('attachment'))
            {
                $file = $request->file('attachment');

                $filename = time() . '_' . self::uuid() . '.' . $file->getClientOriginalExtension();

                $attachment_path = $file->storeAs('user-medical-certificates', $filename, 'public');

                $attachment_name = $file->getClientOriginalName();
            }

            $user_medical_certificate = UserMedicalCertificate::create([
                'uuid' => self::uuid(),
                'user_id' => $user->id,
                'name' => $request->name,
                'description' => $request->description,
                'date' => $request->date,
                'attachment_path' => $attachment_path,
                'attachment_name' => $attachment_name,
                'is_active' => StatusCodeConstants::ACTIVE,
                'created_by' => self::auth()->uuid,
                'created_at' => self::currentDateTime(),
                'updated_by' => self::auth()->uuid,
                'updated_at' => self::currentDateTime(),
            ]);

            $user_medical_certificate->load([
                'user.personal',
                'user.contact',
                'user.employment.office',
                'user.employment.position',
                'user.employment.department',
                'user.emergency',
            ]);

            DB::commit();

            return self::response(new UserMedicalCertificateResource($user_medical_certificate));

        } catch (\Exception $exception) {
            DB::rollback();
            throw $exception;
        }
    }

    public function update(UserMedicalCertificateUpdateRequest $request, string $uuid)
    {
        $user_medical_certificate = UserMedicalCertificate::findByUuid($uuid);

        DB::beginTransaction();

        try {
            $attachment_path = null;
            $attachment_name = null;

            if ($request->hasFile('attachment'))
            {
                $file = $request->file('attachment');

                $filename = time() . '_' . self::uuid() . '.' . $file->getClientOriginalExtension();

                $attachment_path = $file->storeAs('user-medical-certificates', $filename, 'public');

                $attachment_name = $file->getClientOriginalName();
            }

            $user_medical_certificate->update([
                'user_id' => $user_medical_certificate->user_id,
                'name' => $request->name,
                'description' => $request->description,
                'date' => $request->date,
                'attachment_path' => $attachment_path ? $attachment_path : $user_medical_certificate->attachment_path,
                'attachment_name' => $attachment_name ? $attachment_name : $user_medical_certificate->attachment_name,
                'is_active' => StatusCodeConstants::ACTIVE,
                'updated_by' => self::auth()->uuid,
                'updated_at' => self::currentDateTime(),
            ]);

            $user_medical_certificate->load([
                'user.personal',
                'user.contact',
                'user.employment.office',
                'user.employment.position',
                'user.employment.department',
                'user.emergency',
            ]);

            DB::commit();

            return self::response(new UserMedicalCertificateResource($user_medical_certificate));

        } catch (\Exception $exception) {
            DB::rollback();
            throw $exception;
        }
    }

    public function updateStatus(UserMedicalCertificateUpdateStatusRequest $request, string $uuid)
    {
        $user_medical_certificate = UserMedicalCertificate::findByUuid($uuid);

        $user_medical_certificate->update([
            'is_active' => $request->is_active ? StatusCodeConstants::ACTIVE : StatusCodeConstants::INACTIVE,
            'updated_by' => self::auth()->uuid,
            'updated_at' => self::currentDateTime(),
        ]);

        $user_medical_certificate->load([
            'user.personal',
            'user.contact',
            'user.employment.office',
            'user.employment.position',
            'user.employment.department',
            'user.emergency',
        ]);

        return self::response(new UserMedicalCertificateResource($user_medical_certificate));
    }

    public function show(UserMedicalCertificateShowRequest $request, string $uuid)
    {
        $user_medical_certificate = UserMedicalCertificate::with([
            'user.personal',
            'user.contact',
            'user.employment.office',
            'user.employment.position',
            'user.employment.department',
            'user.emergency',
        ])->where('uuid', $uuid)->active()->firstOrFail();

        return self::response(new UserMedicalCertificateResource($user_medical_certificate));
    }
}
