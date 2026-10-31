<?php

namespace App\Console\Commands;

use App\Models\PendingVoiceAction;
use App\Models\VoiceCommandLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:prune-voice-command-history')]
#[Description('Elimina transcripciones de voz cifradas con más de 90 días')]
class PruneVoiceCommandHistory extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = VoiceCommandLog::query()->where('created_at', '<', now()->subDays(90))->delete();
        $deletedActions = PendingVoiceAction::query()->where(function ($query): void {
            $query->where('expires_at', '<', now())->orWhere('consumed_at', '<', now()->subDay());
        })->delete();
        $this->components->info("Transcripciones antiguas eliminadas: {$deleted}. Confirmaciones temporales eliminadas: {$deletedActions}.");

        return self::SUCCESS;
    }
}
