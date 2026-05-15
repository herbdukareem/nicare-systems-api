<?php

namespace App\Console\Commands;

use App\Models\Admission;
use App\Models\User;
use App\Notifications\ExpiredAdmissionNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class FlagExpiredAdmissionsCommand extends Command
{
    protected $signature   = 'nicare:flag-expired-admissions';
    protected $description = 'Flag admissions whose referral UTN has expired and notify claims officers';

    public function handle(): int
    {
        // Find all active admissions whose referral UTN has expired
        $expired = Admission::with(['referral', 'enrollee', 'facility'])
            ->where('status', 'active')
            ->whereHas('referral', function ($q) {
                $q->whereNotNull('valid_until')
                  ->where('valid_until', '<', now());
            })
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired admissions found.');
            return self::SUCCESS;
        }

        $flagged = 0;

        DB::transaction(function () use ($expired, &$flagged) {
            foreach ($expired as $admission) {
                // Only flag once
                if ($admission->utn_expired_flagged) {
                    continue;
                }

                $admission->update(['utn_expired_flagged' => true]);
                $flagged++;
            }
        });

        // Notify all claims officers
        $claimsOfficers = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['claims_officer', 'super_admin']);
        })->get();

        if ($claimsOfficers->isNotEmpty()) {
            Notification::send($claimsOfficers, new ExpiredAdmissionNotification($expired, $flagged));
        }

        $this->info("Flagged {$flagged} expired admission(s). Notified {$claimsOfficers->count()} officer(s).");

        return self::SUCCESS;
    }
}
