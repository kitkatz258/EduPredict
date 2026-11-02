<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Questionnaire\ApprovedInstrumentCatalog;
use App\Services\Questionnaire\RevisedInstrumentActivator;
use App\Services\Questionnaire\RevisedInstrumentCatalog;
use Illuminate\Console\Command;

class ActivateRevisedQuestionnaireCommand extends Command
{
    protected $signature = 'edupredict:activate-revised-questionnaire
        {--dry-run : Report what would change without writing}';

    protected $description = 'Publish the existing 60-item ucc-panel-revised-60-v2 catalog locally without wiping history';

    public function handle(RevisedInstrumentActivator $activator): int
    {
        if ($this->runningInProduction()) {
            $this->error('Refusing to change questionnaire definitions in production. Use a local or test database.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $activator->activate($dryRun);

        $this->line($dryRun
            ? 'Dry run. No questionnaire rows were written.'
            : 'Published the existing '.RevisedInstrumentCatalog::VERSION.' catalog in place.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Catalog items', $result['total']],
                ['Created', $result['created']],
                ['Updated (unanswered)', $result['updated']],
                ['Published without rewriting answered wording', $result['published_locked']],
                ['Already current', $result['skipped_unchanged']],
                ['Preserved '.ApprovedInstrumentCatalog::VERSION.' (unchanged by this command)', 'yes'],
            ],
        );

        return self::SUCCESS;
    }

    private function runningInProduction(): bool
    {
        return app()->environment('production') || config('app.env') === 'production';
    }
}
