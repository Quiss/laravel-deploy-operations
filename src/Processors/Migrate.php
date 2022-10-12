<?php

declare(strict_types=1);

namespace DragonCode\LaravelActions\Processors;

use DragonCode\LaravelActions\Constants\Names;
use DragonCode\LaravelActions\Constants\Options;
use DragonCode\LaravelActions\Events\ActionEnded;
use DragonCode\LaravelActions\Events\ActionFailed;
use DragonCode\LaravelActions\Events\ActionStarted;
use DragonCode\LaravelActions\Events\NoPendingActions;
use DragonCode\Support\Facades\Helpers\Str;
use Throwable;

class Migrate extends Processor
{
    public function handle(): void
    {
        $this->ensureRepository();
        $this->runActions();
    }

    protected function ensureRepository(): void
    {
        $this->artisan(Names::INSTALL, [
            '--' . Options::CONNECTION => $this->options->connection,
            '--' . Options::FORCE      => true,
        ]);
    }

    protected function runActions(): void
    {
        try {
            if ($files = $this->getNewFiles()) {
                $this->fireEvent(ActionStarted::class, 'up');

                $this->runEach($files, $this->getBatch());

                $this->fireEvent(ActionEnded::class, 'up');

                return;
            }

            $this->fireEvent(NoPendingActions::class, 'up');
        }
        catch (Throwable $e) {
            $this->fireEvent(ActionFailed::class, 'up');

            throw $e;
        }
    }

    protected function runEach(array $files, int $batch): void
    {
        foreach ($files as $file) {
            $this->run($file, $batch);
        }
    }

    protected function run(string $file, int $batch): void
    {
        $this->migrator->runUp($file, $batch, $this->options);
    }

    protected function getNewFiles(): array
    {
        $completed = $this->repository->getCompleted()->pluck('action')->toArray();

        return $this->getFiles(
            filter  : fn (string $file) => ! Str::of($file)->replace('\\', '/')->contains($completed),
            path    : $this->options->path,
            fullpath: true
        );
    }

    protected function getBatch(): int
    {
        return $this->repository->getNextBatchNumber();
    }
}
