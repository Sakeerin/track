<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->text('destination')->change();
            $table->string('destination_hash', 64)->nullable()->after('destination');
            $table->index('destination_hash');
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->text('destination')->change();
            $table->string('destination_hash', 64)->nullable()->after('destination');
            $table->index('destination_hash');
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->text('email')->change();
            $table->string('email_hash', 64)->nullable()->after('email');
            $table->index('email_hash');
        });

        $this->encryptSubscriptionDestinations();
        $this->encryptNotificationLogDestinations();
        $this->encryptSupportTicketEmails();
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['destination_hash']);
            $table->dropColumn('destination_hash');
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropIndex(['destination_hash']);
            $table->dropColumn('destination_hash');
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex(['email_hash']);
            $table->dropColumn('email_hash');
        });
    }

    private function encryptSubscriptionDestinations(): void
    {
        DB::table('subscriptions')
            ->select(['id', 'destination'])
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                if (!is_string($row->destination) || trim($row->destination) === '') {
                    return;
                }

                DB::table('subscriptions')
                    ->where('id', $row->id)
                    ->update([
                        'destination' => Crypt::encryptString($row->destination),
                        'destination_hash' => hash('sha256', strtolower(trim($row->destination))),
                    ]);
            });
    }

    private function encryptNotificationLogDestinations(): void
    {
        DB::table('notification_logs')
            ->select(['id', 'destination'])
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                if (!is_string($row->destination) || trim($row->destination) === '') {
                    return;
                }

                DB::table('notification_logs')
                    ->where('id', $row->id)
                    ->update([
                        'destination' => Crypt::encryptString($row->destination),
                        'destination_hash' => hash('sha256', strtolower(trim($row->destination))),
                    ]);
            });
    }

    private function encryptSupportTicketEmails(): void
    {
        DB::table('support_tickets')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                if (!is_string($row->email) || trim($row->email) === '') {
                    return;
                }

                DB::table('support_tickets')
                    ->where('id', $row->id)
                    ->update([
                        'email' => Crypt::encryptString($row->email),
                        'email_hash' => hash('sha256', strtolower(trim($row->email))),
                    ]);
            });
    }
};
