<?php

declare(strict_types=1);

namespace HtmlShot\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'update',
    description: 'Update the native Takumi library for the installed package version',
)]
final class UpdateCommand extends Command
{
    public function __construct(
        private readonly string $packageRoot,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'Release version to download (defaults to the installed package version)',
            )
            ->setHelp(
                "Re-downloads the compiled Takumi Rust library, replacing any copy already\n".
                "present in the lib/ directory. Run this after upgrading the package to fetch\n".
                'the native library matching the new version.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $version */
        $version = $input->getArgument('version');

        try {
            // Update always overwrites the existing artifact.
            (new NativeLibraryInstaller($this->packageRoot, $this->projectRoot))->install(
                $io,
                force: true,
                version: $version,
            );
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
