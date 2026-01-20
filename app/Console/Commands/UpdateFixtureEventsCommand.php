<?php

namespace App\Console\Commands;

use App\Jobs\UpdateFixtureEventsJob;
use App\Models\Fixture;
use Illuminate\Console\Command;

class UpdateFixtureEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fixture:update-events {--id= : The ID of the fixture to update} {--all : Update all recently finished fixtures} {--limit=10 : Limit for batch update} {--since=2025-08-01 : Start date for fixtures (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update events (goals, cards, subs) for finished fixtures using Sofascore API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fixtureId = $this->option('id');
        $all = $this->option('all');
        $limit = $this->option('limit');
        $since = $this->option('since');

        if ($fixtureId) {
            $this->info("Dispatching job for fixture ID: $fixtureId");
            UpdateFixtureEventsJob::dispatch($fixtureId);
            return 0;
        }

        if ($all) {
            $this->info("Finding finished fixtures since $since...");

            // Find finished fixtures that have a Sofascore ID (id_fixture)
            $fixtures = Fixture::where('status', 'FINISHED')
                ->whereNotNull('id_fixture')
                ->where('utc_date', '>=', $since . ' 00:00:00')
                ->orderBy('utc_date', 'desc')
                ->limit($limit)
                ->get();

            $this->info("Found " . $fixtures->count() . " fixtures.");

            foreach ($fixtures as $fixture) {
                $this->info("Dispatching job for fixture ID: {$fixture->id} ({$fixture->home_team_name} vs {$fixture->away_team_name})");
                UpdateFixtureEventsJob::dispatch($fixture->id);
            }
            return 0;
        }

        $this->error("Please specify --id={id} or --all");
        return 1;
    }
}
