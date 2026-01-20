<?php

namespace App\Jobs;

use App\Models\Fixture;
use App\Services\FixtureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateFixtureEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fixtureId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($fixtureId)
    {
        $this->fixtureId = $fixtureId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(FixtureService $fixtureService)
    {
        try {
            Log::info("Starting event update for fixture ID: {$this->fixtureId}");

            // Check if fixture exists first
            $fixture = Fixture::find($this->fixtureId);
            if (!$fixture) {
                Log::warning("Fixture not found for ID: {$this->fixtureId}");
                return;
            }

            // This method handles fetching from API and saving to DB
            $events = $fixtureService->getEvents($this->fixtureId);
            $fixtureService->getLineups($this->fixtureId);

            Log::info("Finished event and lineup update for fixture ID: {$this->fixtureId}. Events count: " . count($events));
        } catch (\Exception $e) {
            Log::error("Failed to update events for fixture ID {$this->fixtureId}: " . $e->getMessage());
        }
    }
}
