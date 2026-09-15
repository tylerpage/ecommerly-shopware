<?php declare(strict_types=1);

namespace Ecommerly\Connector\Command;

use Ecommerly\Connector\Capability\CapabilityRegistry;
use Ecommerly\Connector\Capability\EditionDetector;
use Ecommerly\Connector\Connection\ConnectionConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What an operator needs to answer "is this connected, and what can
 * it answer?" without reading system_config by hand.
 *
 * The connection secret is never printed, in full or truncated.
 */
#[AsCommand(name: 'ecommerly:status', description: 'Show the current Ecommerly connection state.')]
class StatusCommand extends Command
{
    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly CapabilityRegistry $capabilities,
        private readonly EditionDetector $edition
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $scopes = $this->config->scopes();

        $io->definitionList(
            ['Enabled' => $this->config->isEnabled() ? 'yes' : 'no'],
            ['Paired' => $this->config->isPaired() ? 'yes' : 'no'],
            ['Connection' => $this->config->connectionId() ?? '-'],
            ['Paired at' => $this->config->pairedAt() ?? '-'],
            ['Ecommerly URL' => $this->config->ecommerlyBaseUrl() ?? '-'],
            ['Edition' => $this->edition->edition()],
            ['Shopware' => $this->edition->shopwareVersion()],
            ['Granted scopes' => $scopes === [] ? 'all (no scope negotiation)' : implode(', ', $scopes)],
            ['Capabilities' => implode(', ', $this->capabilities->supported())],
        );

        return Command::SUCCESS;
    }
}
