<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Questionnaire\PopulatedQuestionnaireRelease;
use App\Services\Questionnaire\QuestionnaireReleaseBlocked;
use Illuminate\Console\Command;

class PrepareApprovedQuestionnaireCommand extends Command
{
    protected $signature = 'edupredict:prepare-approved-questionnaire
        {--execute : Write the approved 60-item catalog after the checks pass}
        {--confirm-preserves-history : Required with --execute. Historical submissions stay on their original versions}';

    protected $description = 'Check, and optionally apply, the approved 60-item questionnaire on a database that already has students';

    public function handle(PopulatedQuestionnaireRelease $release): int
    {
        $execute = (bool) $this->option('execute');
        $confirm = (bool) $this->option('confirm-preserves-history');
        if ($execute && ! $confirm) {
            $this->error('Refusing to write. Re-run with --execute --confirm-preserves-history after a dry run and a backup.');

            return self::FAILURE;
        }

        try {
            $result = $release->prepare($execute);
        } catch (QuestionnaireReleaseBlocked $blocked) {
            $this->error($execute
                ? 'The update was rolled back. No questionnaire rows were kept from this attempt.'
                : 'Dry run stopped. No questionnaire rows were written.');
            foreach ($blocked->reasons as $reason) {
                $this->line(' - '.$reason);
            }

            return self::FAILURE;
        }

        $this->line($result['dry_run']
            ? 'Dry run. No questionnaire rows were written.'
            : 'Post-check passed. Historical submissions stayed on their original versions.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Catalog items', $result['total']],
                ['Would create / created', $result['created']],
                ['Would update unanswered / updated', $result['updated']],
                ['Would publish locked wording / published', $result['published_locked']],
                ['Already current', $result['skipped_unchanged']],
                ['Submissions', $result['responses']],
                ['Answers', $result['answers']],
                ['Current version', $result['current'] !== '' ? $result['current'] : '(none)'],
            ],
        );

        return self::SUCCESS;
    }
}
