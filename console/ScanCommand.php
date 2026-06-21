<?php

namespace Golem15\Translate\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Golem15\Translate\Classes\ThemeScanner;
use Golem15\Translate\Models\Message;

#[AsCommand(name: 'translate:scan', description: 'Scan theme localization files for new messages.')]
class ScanCommand extends Command
{
    /**
     * @var string The name and signature of this command.
     */
    protected $signature = 'translate:scan
        {--p|purge : Purge existing messages before scanning.}';

    /**
     * @var string The console command description.
     */
    protected $description = 'Scan theme localization files for new messages.';

    public function handle()
    {
        if ($this->option('purge')) {
            $this->output->writeln('Purging messages...');
            Message::truncate();
        }

        ThemeScanner::scan();
        $this->output->success('Messages scanned successfully.');
        $this->output->note('You may need to run cache:clear for updated messages to take effect.');
    }
}
