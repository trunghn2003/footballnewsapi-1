<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Jobs\UpdateTeamSquadJob;
use Illuminate\Console\Command;

class UpdateTeamSquadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teams:update-squads {--team_id= : specific team ID to update} {--limit= : limit number of teams to process} {--all : Process all teams ignoring activity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update squads for teams using Sofascore API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $teamId = $this->option('team_id');
        $limit = $this->option('limit');
        $all = $this->option('all');

        if ($teamId) {
            $this->info("Dispatching job for Team ID: $teamId");
            dispatch(new UpdateTeamSquadJob((int)$teamId));
            return 0;
        }

        // Select teams to update. where id > 79 
        $query = Team::query()->where('id', '>', 79);

        // Filter only active teams unless --all is specified
        if (!$all) {
            $query->where(function ($q) {
                $q->whereHas('homeFixtures', function ($sq) {
                    $sq->where('utc_date', '>=', now()->subMonths(12));
                })->orWhereHas('awayFixtures', function ($sq) {
                    $sq->where('utc_date', '>=', now()->subMonths(12));
                });
            });
        }

        if ($limit) {
            $query->limit((int)$limit);
        }

        $teams = $query->get();
        $this->info("Found " . $teams->count() . " active teams to update.");

        foreach ($teams as $team) {
            $this->info("Dispatching job for Team: {$team->name} (ID: {$team->id})");
            dispatch(new UpdateTeamSquadJob($team->id));
            // Add a small delay if dispatching to sync driver, but for queue it's fine.
        }

        return 0;
    }
}
