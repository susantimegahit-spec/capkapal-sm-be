<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_applications', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nik', 16)->nullable();
            $table->string('gender')->nullable();
            $table->string('education_level')->nullable();
            $table->string('major')->nullable();
            $table->string('institution')->nullable();
            $table->unsignedBigInteger('expected_salary')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('transcript_path')->nullable();
            $table->string('certificate_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('career_applications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'nik',
                'gender',
                'education_level',
                'major',
                'institution',
                'expected_salary',
                'province',
                'city',
                'transcript_path',
                'certificate_path',
            ]);
        });
    }
};
