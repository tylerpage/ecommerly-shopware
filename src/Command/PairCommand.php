<?php declare(strict_types=1);

namespace Ecommerly\Connector\Command;

use Ecommerly\Connector\Connection\PairingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pairing is a console command rather than an administration screen
 * on purpose: an admin module is the one part of a plugin that cannot
 * be written once for Shopware 6.6 and 6.7, since the Vue 2
 * compatibility layer is gone and the asset build moved from Webpack
 * to Vite. Everything else here is version-agnostic PHP, so keeping
 * the interactive actions on the CLI is what lets one release serve
 * both lines.
 *
 * Running this against an already-paired store rotates the
 * credential, keeping the outgoing secret valid for a short overlap —
 * see ConnectionConfig::storePairing().
 */
#[AsCommand(name: 'ecommerly:pair', description: 'Exchange a one-time Ecommerly pairing token for connection credentials.')]
class PairCommand extends Command
{
    public function __construct(private readonly PairingService $pairing)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('token', InputArgument::REQUIRED, 'The one-time pairing token issued by Ecommerly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->pairing->pair((string) $input->getArgument('token'));

        if (! $result->ok) {
            $io->error($result->message ?? 'Pairing failed.');

            return Command::FAILURE;
        }

        $io->success('Paired with Ecommerly as '.$result->connectionId.'.');

        return Command::SUCCESS;
    }
}
