<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->string('education_level')->nullable();
            $table->string('institution')->nullable();
            $table->string('major')->nullable();
            $table->string('nik', 16)->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedBigInteger('expected_salary')->nullable();
            $table->string('transcript_path')->nullable();
            $table->string('certificate_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'education_level',
                'institution',
                'major',
                'nik',
                'province',
                'city',
                'gender',
                'expected_salary',
                'transcript_path',
                'certificate_path',
            ]);
        });
    }
};
