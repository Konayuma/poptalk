<?php

namespace App\Console\Commands;

use App\Services\WalkieTalkieService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('poptalk:prune')]
#[Description('Drop stale frequency presence, expired PTT locks, and old signaling payloads')]
class PruneStaleWalkieTalkieState extends Command
{
    public function handle(WalkieTalkieService $walkieTalkie): int
    {
        $walkieTalkie->pruneStale();

        $this->info('Stale walkie-talkie state pruned.');

        return self::SUCCESS;
    }
}
