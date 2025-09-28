<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Kiểm tra và tạo/cập nhật table users
        if (!Schema::hasTable('users')) {
            // Tạo table mới nếu chưa có
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        } else {
            // Nếu table đã có, chỉ thêm các column chưa tồn tại
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'id')) {
                    $table->id();
                }
                if (!Schema::hasColumn('users', 'name')) {
                    $table->string('name');
                }
                if (!Schema::hasColumn('users', 'email')) {
                    $table->string('email')->unique();
                }
                if (!Schema::hasColumn('users', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable();
                }
                if (!Schema::hasColumn('users', 'password')) {
                    $table->string('password');
                }
                if (!Schema::hasColumn('users', 'remember_token')) {
                    $table->rememberToken();
                }
                if (!Schema::hasColumn('users', 'created_at') || !Schema::hasColumn('users', 'updated_at')) {
                    $table->timestamps();
                }
            });
        }

        // Kiểm tra và tạo/cập nhật table password_reset_tokens
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        } else {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('password_reset_tokens', 'email')) {
                    $table->string('email')->primary();
                }
                if (!Schema::hasColumn('password_reset_tokens', 'token')) {
                    $table->string('token');
                }
                if (!Schema::hasColumn('password_reset_tokens', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
            });
        }

        // Kiểm tra và tạo/cập nhật table sessions
        if (!Schema::hasTable('voy_sessions')) {
            Schema::create('voy_sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        } else {
            // Schema::table('voy_sessions', function (Blueprint $table) {
            //     if (!Schema::hasColumn('sessions', 'id')) {
            //         $table->string('id')->primary();
            //     }
            //     if (!Schema::hasColumn('sessions', 'user_id')) {
            //         $table->foreignId('user_id')->nullable()->index();
            //     }
            //     if (!Schema::hasColumn('sessions', 'ip_address')) {
            //         $table->string('ip_address', 45)->nullable();
            //     }
            //     if (!Schema::hasColumn('sessions', 'user_agent')) {
            //         $table->text('user_agent')->nullable();
            //     }
            //     if (!Schema::hasColumn('sessions', 'payload')) {
            //         $table->longText('payload');
            //     }
            //     if (!Schema::hasColumn('sessions', 'last_activity')) {
            //         $table->integer('last_activity')->index();
            //     }
            // });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Chỉ xóa columns được thêm bởi migration này
        // Không xóa toàn bộ table nếu nó đã tồn tại trước đó
        
        // Có thể comment out để tránh xóa nhầm
        // Schema::dropIfExists('sessions');
        // Schema::dropIfExists('password_reset_tokens');  
        // Schema::dropIfExists('users');
        
        // Hoặc chỉ xóa columns cụ thể nếu cần thiết:
        /*
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Chỉ drop những columns mà migration này đã thêm
            });
        }
        */
    }
};