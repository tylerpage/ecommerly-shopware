<?php declare(strict_types=1);

namespace Ecommerly\Connector\Command;

use Ecommerly\Connector\Connection\ConnectionConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Severs the connection from the store's side, which a merchant must
 * be able to do without Ecommerly's cooperation (PRD §6.2).
 *
 * Forgetting the credential is what makes this effective: every
 * subsequent request fails verification because there is no secret to
 * verify against, whatever Ecommerly still believes about the
 * connection.
 */
#[AsCommand(name: 'ecommerly:disconnect', description: 'Forget the Ecommerly connection credentials on this store.')]
class DisconnectCommand extends Command
{
    public function __construct(private readonly ConnectionConfig $config)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (! $this->config->isPaired()) {
            $io->note('This store is not paired with Ecommerly.');

            return Command::SUCCESS;
        }

        $this->config->forget();
        $io->success('Disconnected. Ecommerly can no longer read this store.');

        return Command::SUCCESS;
    }
}
