<?php

namespace App\Modules\Career\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Auth\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApplicantProfileController extends Controller
{
    use ApiResponseTrait;

    public function show(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($request->user()->load('applicantProfile'))
        );
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'phone'         => 'sometimes|nullable|string|max:20',
            'address'       => 'sometimes|nullable|string|max:1000',
            'linkedin_url'  => 'sometimes|nullable|url|max:255',
            'portfolio_url' => 'sometimes|nullable|url|max:255',
            'education_level' => 'sometimes|nullable|string|max:50',
            'institution'   => 'sometimes|nullable|string|max:255',
            'major'         => 'sometimes|nullable|string|max:255',
            'nik'           => 'sometimes|nullable|string|size:16',
            'province'      => 'sometimes|nullable|string|max:100',
            'city'          => 'sometimes|nullable|string|max:100',
            'gender'        => 'sometimes|nullable|string|max:20',
            'expected_salary' => 'sometimes|nullable|numeric',
        ]);

        $user = $request->user();

        // Update user name if provided
        if (isset($validated['name'])) {
            $user->update(['name' => $validated['name']]);
        }

        // Create or update applicant profile
        $user->applicantProfile()->updateOrCreate(
            ['user_id' => $user->id],
            \Illuminate\Support\Arr::only($validated, [
                'phone', 'address', 'linkedin_url', 'portfolio_url',
                'education_level', 'institution', 'major', 'nik',
                'province', 'city', 'gender', 'expected_salary'
            ])
        );

        return $this->success(
            new UserResource($user->load('applicantProfile')),
            'Profil lamaran berhasil diperbarui'
        );
    }

    public function uploadCv(Request $request): JsonResponse
    {
        $request->validate([
            'cv' => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $user = $request->user();
        $profile = $user->applicantProfile;
        
        // Delete old CV if exists
        if ($profile && $profile->cv_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->cv_path);
        }
        
        $path = $request->file('cv')->store('applicant-cvs', 'public');

        $user->applicantProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['cv_path' => $path]
        );

        return $this->success(
            new UserResource($user->load('applicantProfile')),
            'CV berhasil diunggah'
        );
    }

    public function deleteCv(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->applicantProfile;

        if ($profile && $profile->cv_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->cv_path);
            
            $profile->update(['cv_path' => null]);
        }

        return $this->success(
            new UserResource($user->load('applicantProfile')),
            'CV berhasil dihapus'
        );
    }

    public function uploadTranscript(Request $request): JsonResponse
    {
        $request->validate([
            'transcript' => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $user = $request->user();
        $profile = $user->applicantProfile;
        
        if ($profile && $profile->transcript_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->transcript_path);
        }
        
        $path = $request->file('transcript')->store('applicant-transcripts', 'public');

        $user->applicantProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['transcript_path' => $path]
        );

        return $this->success(
            new UserResource($user->load('applicantProfile')),
            'Transkrip berhasil diunggah'
        );
    }

    public function deleteTranscript(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->applicantProfile;

        if ($profile && $profile->transcript_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->transcript_path);
            $profile->update(['transcript_path' => null]);
        }

        return $this->success(
            new UserResource($user->load('applicantProfile')),
            'Transkrip berhasil dihapus'
        );
    }

    public function uploadCertificate(Request $request): JsonResponse
    {
        $request->validate([
            'certificate' => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $user = $request->user();
        $profile = $user->applicantProfile;
        
        if ($profile && $profile->certificate_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->certificate_path);
        }
        
        $path = $request->file('certificate')->store('applicant-certificates', 'public');

        $user->applicantProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['certificate_path' => $path]
        );

        return $this->success(
            new UserResource($user->load('applicantProfile')),
            'Ijazah berhasil diunggah'
        );
    }

    public function deleteCertificate(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->applicantProfile;

        if ($profile && $profile->certificate_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->certificate_path);
            $profile->update(['certificate_path' => null]);
        }

        return $this->success(
            new UserResource($user->load('applicantProfile')),
            'Ijazah berhasil dihapus'
        );
    }
}
